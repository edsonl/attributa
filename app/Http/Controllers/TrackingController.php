<?php

namespace App\Http\Controllers;

use App\Models\Pageview;
use App\Models\Campaign;
use App\Models\Browser;
use App\Models\DeviceCategory;
use App\Models\TrafficSourceCategory;
use App\Models\PageviewEvent;
use App\Services\HashidService;
use App\Services\IpClassifierService;
use App\Services\DeviceClassificationService;
use App\Services\PageviewClassificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    protected array $trafficSourceIdBySlug = [];
    protected array $deviceCategoryIdBySlug = [];
    protected array $browserIdBySlug = [];

    /**
     * Recebe e persiste o pageview inicial de tracking enviado pelo snippet.
     *
     * Fluxo geral:
     * 1) valida estrutura do payload (identificadores, contexto da navegação e parâmetros de campanha);
     * 2) valida os códigos hashid de usuário/campanha e confere consistência com a campanha no banco;
     * 3) aplica validações de segurança antes de gravar (assinatura, replay e origem autorizada);
     * 4) normaliza/sanitiza campos e registra o pageview para uso posterior em eventos e conversões.
     *
     * Autenticação/segurança aplicada no collect:
     * - assinatura HMAC (`auth_sig`) baseada em `user_code`, `campaign_code`, `auth_ts` e `auth_nonce`;
     * - janela de validade curta via `auth_ts` para rejeitar assinaturas expiradas;
     * - proteção anti-replay com cache do `auth_nonce` (nonce único por janela de tempo);
     * - validação de origem da requisição comparando a origem enviada com a origem cadastrada na campanha.
     */
    public function collect(Request $request)
    {
        $log = Log::channel('tracking_collect');
        $gclidAlertLog = Log::channel('tracking_gclid_alert');

        // Validação estrutural do payload recebido pelo snippet.
        $data = $request->validate([
            'user_code'    => 'required|string|max:32',
            'campaign_code' => 'required|string|max:32',
            'visitor_code' => 'nullable|string|max:32',
            'auth_ts'      => 'required|integer',
            'auth_nonce'   => 'required|string|min:16|max:64|regex:/^[A-Za-z0-9]+$/',
            'auth_sig'     => 'required|string|size:64|regex:/^[a-f0-9]+$/',
            'url'           => 'required|string|max:500',
            'landing_url'   => 'nullable|string|max:500',
            'referrer'      => 'nullable|string',
            'user_agent'    => 'nullable|string',
            'timestamp'     => 'nullable|integer',
            'gclid'         => 'nullable|string',
            'gad_campaignid'=> 'nullable|string',
            'utm_source'    => 'nullable|string',
            'utm_medium'    => 'nullable|string',
            'utm_campaign'  => 'nullable|string',
            'utm_term'      => 'nullable|string',
            'utm_content'   => 'nullable|string',
            'fbclid'        => 'nullable|string',
            'ttclid'        => 'nullable|string',
            'msclkid'       => 'nullable|string',
            'wbraid'        => 'nullable|string',
            'gbraid'        => 'nullable|string',
            'screen_width'  => 'nullable|integer|min:1|max:20000',
            'screen_height' => 'nullable|integer|min:1|max:20000',
            'viewport_width' => 'nullable|integer|min:1|max:20000',
            'viewport_height' => 'nullable|integer|min:1|max:20000',
            'device_pixel_ratio' => 'nullable|numeric|min:0|max:20',
            'platform'      => 'nullable|string',
            'language'      => 'nullable|string',
        ]);
        
        // Decodifica hashids enviados no front para validar formato antes de consultar o banco.
        // Se qualquer token for inválido, bloqueia cedo para evitar lookup desnecessário.
        $userIdFromCode = app(HashidService::class)->decode((string) $data['user_code']);
        $campaignIdFromCode = app(HashidService::class)->decode((string) $data['campaign_code']);

        if (!$userIdFromCode || !$campaignIdFromCode) {
            $log->warning('Tracking collect rejeitado: tokens inválidos.', [
                'user_code' => (string) ($data['user_code'] ?? ''),
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'decoded_user_id' => $userIdFromCode,
                'decoded_campaign_id' => $campaignIdFromCode,
                'ip' => $request->ip(),
                'origin' => $request->headers->get('Origin'),
                'referer' => $request->headers->get('Referer'),
            ]);

            return response()->json([
                'message' => 'Tokens de tracking inválidos.',
            ], 422);
        }

        // Anti-tampering: valida assinatura com janela de tempo curta.
        // Sem isso, um payload antigo poderia ser reaproveitado por terceiros.
        if (!$this->isTrackingTimestampValid((int) $data['auth_ts'])) {
            $log->warning('Tracking collect rejeitado: assinatura expirada.', [
                'auth_ts' => (int) $data['auth_ts'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Assinatura expirada.',
            ], 422);
        }

        // Assinatura HMAC do collect. Qualquer alteração em campos assinados invalida o request.
        $expectedSig = $this->buildTrackingSignature(
            (string) $data['user_code'],
            (string) $data['campaign_code'],
            (int) $data['auth_ts'],
            (string) $data['auth_nonce']
        );

        if (!hash_equals($expectedSig, strtolower((string) $data['auth_sig']))) {
            $log->warning('Tracking collect rejeitado: assinatura inválida.', [
                'user_code' => (string) ($data['user_code'] ?? ''),
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'auth_nonce' => (string) ($data['auth_nonce'] ?? ''),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Assinatura inválida.',
            ], 422);
        }

        // Anti-replay: o mesmo nonce não pode ser aceito duas vezes na janela de validade.
        // Usa cache distribuído para funcionar mesmo com múltiplas instâncias.
        $nonceTtlSeconds = (int) config('app.tracking_nonce_ttl_seconds', 300);
        $nonceAdded = Cache::add(
            $this->trackingNonceCacheKey((string) $data['auth_nonce']),
            1,
            now()->addSeconds($nonceTtlSeconds)
        );

        if (!$nonceAdded) {
            $log->warning('Tracking collect rejeitado: nonce reaproveitado.', [
                'auth_nonce' => (string) ($data['auth_nonce'] ?? ''),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Requisição repetida.',
            ], 422);
        }

        // Obtém campanha apenas quando os tokens são consistentes com banco.
        // Aqui também valida vínculo forte user_id + campaign_id + campaign_code.
        $campaign = Campaign::query()
            ->where('id', $campaignIdFromCode)
            ->where('user_id', $userIdFromCode)
            ->where('code', $data['campaign_code'])
            ->first();

        if (!$campaign) {
            $log->warning('Tracking collect rejeitado: campanha inválida.', [
                'user_code' => (string) ($data['user_code'] ?? ''),
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'decoded_user_id' => $userIdFromCode,
                'decoded_campaign_id' => $campaignIdFromCode,
                'ip' => $request->ip(),
                'origin' => $request->headers->get('Origin'),
                'referer' => $request->headers->get('Referer'),
            ]);
            return response()->json([
                'message' => 'Campanha inválida.',
            ], 422);
        }

        // Segurança de origem: coleta apenas da origem cadastrada na campanha.
        // Evita que um snippet copiado seja usado em domínio não autorizado.
        $allowedOrigin = Campaign::normalizeProductUrl($campaign->product_url);
        if (!$allowedOrigin) {
            $log->warning('Tracking collect rejeitado: campanha sem origem configurada.', [
                'campaign_id' => $campaign->id,
                'campaign_code' => $campaign->code,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Origem de tracking não configurada para a campanha.',
            ], 403);
        }

        $requestOrigin = $this->extractRequestOrigin($request);
        if (!$requestOrigin || !$this->originsMatch($allowedOrigin, $requestOrigin)) {
            $log->warning('Tracking collect rejeitado: origem não autorizada.', [
                'campaign_id' => $campaign->id,
                'campaign_code' => $campaign->code,
                'allowed_origin' => $allowedOrigin,
                'request_origin' => $requestOrigin,
                'origin_header' => $request->headers->get('Origin'),
                'referer_header' => $request->headers->get('Referer'),
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Origem não autorizada para esta campanha.',
            ], 403);
        }

        // GCLID é crítico para atribuição no Google Ads.
        // Se vier fora do padrão esperado, salva como null para evitar lixo no banco.
        $rawGclidInput = array_key_exists('gclid', $data) ? (string) $data['gclid'] : null;
        $gclid = isset($data['gclid']) ? trim((string) $data['gclid']) : null;
        if ($gclid === null || $gclid === '') {
            $gclid = null;
        } elseif (mb_strlen($gclid) > 150) {
            $gclidAlertLog->warning('GCLID acima do limite recebido no collect; valor truncado para persistencia.', [
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'received_length' => mb_strlen($gclid),
                'stored_length' => 150,
                'gclid_prefix' => mb_substr($gclid, 0, 24),
                'gclid_suffix' => mb_substr($gclid, -12),
                'gclid_raw_input' => $rawGclidInput,
                'ip' => $request->ip(),
                'origin' => $request->headers->get('Origin'),
                'referer' => $request->headers->get('Referer'),
            ]);
            $gclid = mb_substr($gclid, 0, 150);
        }

        if ($gclid !== null) {
            $looksLikeGclid = (bool) preg_match('/^[A-Za-z0-9_-]{10,150}$/', $gclid)
                && (bool) preg_match('/[A-Za-z]/', $gclid)
                && (bool) preg_match('/\d/', $gclid);

            if (!$looksLikeGclid) {
                $gclidAlertLog->warning('GCLID fora do padrão esperado no collect; valor descartado.', [
                    'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                    'received_length' => mb_strlen($gclid),
                    'gclid_prefix' => mb_substr($gclid, 0, 24),
                    'gclid_suffix' => mb_substr($gclid, -12),
                    'gclid_raw_input' => $rawGclidInput,
                    'ip' => $request->ip(),
                    'origin' => $request->headers->get('Origin'),
                    'referer' => $request->headers->get('Referer'),
                ]);
                $gclid = null;
            }
        }

        $url = mb_substr(trim((string) $data['url']), 0, 500);
        $landingUrl = isset($data['landing_url']) ? mb_substr(trim((string) $data['landing_url']), 0, 500) : null;
        if ($landingUrl === '') {
            $landingUrl = null;
        }

        // Truncamento defensivo para campos varchar, evitando erro de insert.
        $limitedFields = [
            'gad_campaignid' => 191,
            'utm_source' => 191,
            'utm_medium' => 191,
            'utm_campaign' => 191,
            'utm_term' => 191,
            'utm_content' => 191,
            'fbclid' => 191,
            'ttclid' => 191,
            'msclkid' => 191,
            'wbraid' => 191,
            'gbraid' => 191,
            'platform' => 191,
            'language' => 20,
        ];

        foreach ($limitedFields as $field => $limit) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field];
            if ($value === null) {
                continue;
            }

            $normalized = trim((string) $value);
            $data[$field] = $normalized === '' ? null : mb_substr($normalized, 0, $limit);
        }
        $rawUserAgent = $data['user_agent'] ?? $request->userAgent();
        $userAgent = $rawUserAgent !== null ? trim((string) $rawUserAgent) : null;
        if ($userAgent === '') {
            $userAgent = null;
        } elseif (mb_strlen($userAgent) > 500) {
            $log->warning('Tracking collect recebeu user_agent acima do limite; valor truncado para persistencia.', [
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'received_length' => mb_strlen($userAgent),
                'stored_length' => 500,
                'ip' => $request->ip(),
            ]);
            $userAgent = mb_substr($userAgent, 0, 500);
        }

        // Classificação técnica da visita (origem, device, browser) antes da persistência.
        $classification = app(PageviewClassificationService::class)->classify($data, $request->ip());
        $trafficSourceCategoryId = $this->resolveTrafficSourceCategoryId((string) ($classification['traffic_source_slug'] ?? 'unknown'));
        $trafficSourceReason = mb_substr((string) ($classification['traffic_source_reason'] ?? ''), 0, 191);
        $timestampMs = $this->normalizeTimestampMs($data['timestamp'] ?? null);
        $visitorId = $this->resolveVisitorIdFromCode($data['visitor_code'] ?? null);
        $deviceClassification = [
            'device_category_id' => $this->resolveDeviceCategoryId('unknown'),
            'browser_id' => $this->resolveBrowserId('unknown'),
            'device_type' => null,
            'device_brand' => null,
            'device_model' => null,
            'os_name' => null,
            'os_version' => null,
            'browser_name' => null,
            'browser_version' => null,
        ];

        try {
            $deviceClassification = app(DeviceClassificationService::class)->classify($userAgent);
        } catch (\Throwable $e) {
            $log->warning('Tracking collect: falha na classificacao de dispositivo.', [
                'ip' => $request->ip(),
                'error' => $e->getMessage(),
            ]);
        }

        // Enriquecimento imediato apenas com MaxMind habilitado.
        // Para outros drivers, o fluxo segue assíncrono pelo job existente.
        $ipClassification = null;
        $geoDriver = strtolower(trim((string) config('pageview.geolocation.driver', 'api')));
        $maxmindCityDbPath = (string) config('pageview.geolocation.maxmind.city_db_path', '');
        if ($geoDriver === 'maxmind' && $maxmindCityDbPath !== '' && is_file($maxmindCityDbPath)) {
            try {
                $ipClassification = app(IpClassifierService::class)->classify(
                    (string) $request->ip(),
                    $userAgent
                );
            } catch (\Throwable $e) {
                $log->warning('Tracking collect: falha no enriquecimento síncrono via MaxMind.', [
                    'ip' => $request->ip(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Transação para manter consistência entre pageview e agregação do visitante por campanha.
        [$pageview, $visitorId] = DB::transaction(function () use (
            $campaign,
            $visitorId,
            $url,
            $landingUrl,
            $data,
            $gclid,
            $userAgent,
            $request,
            $timestampMs,
            $trafficSourceCategoryId,
            $trafficSourceReason,
            $deviceClassification,
            $ipClassification
        ) {
            // Persistencia principal da pageview.
            $pageview = Pageview::create([
                'user_id'      => $campaign->user_id,
                'campaign_id'   => $campaign->id,
                'visitor_id'    => $visitorId,
                'url'           => $url,
                'landing_url'   => $landingUrl,
                'referrer'      => $data['referrer'] ?? null,
                'gclid'         => $gclid,
                'gad_campaignid'=> $data['gad_campaignid'] ?? null,
                'utm_source'    => $data['utm_source'] ?? null,
                'utm_medium'    => $data['utm_medium'] ?? null,
                'utm_campaign'  => $data['utm_campaign'] ?? null,
                'utm_term'      => $data['utm_term'] ?? null,
                'utm_content'   => $data['utm_content'] ?? null,
                'fbclid'        => $data['fbclid'] ?? null,
                'ttclid'        => $data['ttclid'] ?? null,
                'msclkid'       => $data['msclkid'] ?? null,
                'wbraid'        => $data['wbraid'] ?? null,
                'gbraid'        => $data['gbraid'] ?? null,
                'user_agent'    => $userAgent,
                'ip'            => $request->ip(),
                'timestamp_ms'  => $timestampMs,
                'conversion'    => 0,
                'ip_category_id' => $ipClassification['ip_category_id'] ?? null,
                'traffic_source_category_id' => $trafficSourceCategoryId,
                'traffic_source_reason' => $trafficSourceReason === '' ? null : $trafficSourceReason,
                'device_category_id' => $deviceClassification['device_category_id'] ?? null,
                'browser_id' => $deviceClassification['browser_id'] ?? null,
                'device_type' => $deviceClassification['device_type'] ?? null,
                'device_brand' => $deviceClassification['device_brand'] ?? null,
                'device_model' => $deviceClassification['device_model'] ?? null,
                'os_name' => $deviceClassification['os_name'] ?? null,
                'os_version' => $deviceClassification['os_version'] ?? null,
                'browser_name' => $deviceClassification['browser_name'] ?? null,
                'browser_version' => $deviceClassification['browser_version'] ?? null,
                'screen_width' => $data['screen_width'] ?? null,
                'screen_height' => $data['screen_height'] ?? null,
                'viewport_width' => $data['viewport_width'] ?? null,
                'viewport_height' => $data['viewport_height'] ?? null,
                'device_pixel_ratio' => isset($data['device_pixel_ratio']) ? (float) $data['device_pixel_ratio'] : null,
                'platform' => $data['platform'] ?? null,
                'language' => $data['language'] ?? null,
                'country_code' => $ipClassification['geo']['country_code'] ?? null,
                'country_name' => $ipClassification['geo']['country_name'] ?? null,
                'region_name' => $ipClassification['geo']['region_name'] ?? null,
                'city' => $ipClassification['geo']['city'] ?? null,
                'latitude' => $ipClassification['geo']['latitude'] ?? null,
                'longitude' => $ipClassification['geo']['longitude'] ?? null,
                'timezone' => $ipClassification['geo']['timezone'] ?? null,
            ]);

            // Quando o front ainda nao tem visitor_code, fixa um id estavel para reutilizar nas proximas visitas.
            if (!$visitorId) {
                $visitorId = (int) $pageview->id;
                $pageview->visitor_id = $visitorId;
                $pageview->save();
            }

            $now = now();
            $nowMs = $now->valueOf();
            // Atualiza agregação de visitantes únicos por campanha:
            // - primeira ocorrência cria linha com hits=1
            // - recorrência atualiza last_seen_at e incrementa hits
            DB::statement(
                'INSERT INTO campaign_visitors
                    (campaign_id, visitor_id, first_seen_at, last_seen_at, hits, created_at, updated_at)
                 VALUES (?, ?, ?, ?, 1, ?, ?)
                 ON DUPLICATE KEY UPDATE
                    last_seen_at = VALUES(last_seen_at),
                    hits = hits + 1,
                    updated_at = VALUES(updated_at)',
                [
                    (int) $campaign->id,
                    (int) $visitorId,
                    $nowMs,
                    $nowMs,
                    $now,
                    $now,
                ]
            );

            return [$pageview, (int) $visitorId];
        });

        $log->info('Tracking collect salvo com sucesso.', [
            'pageview_id' => $pageview->id,
            'visitor_id' => $visitorId,
            'campaign_id' => $campaign->id,
            'campaign_code' => $campaign->code,
            'request_origin' => $requestOrigin,
            'ip' => $request->ip(),
        ]);

        // Retorna hashid da pageview para compor subids/código composto de callback.
        $pageviewCode = app(HashidService::class)->encode((int) $pageview->id);

        return response()->json([
            'pageview_code' => $pageviewCode,
            'visitor_code' => app(HashidService::class)->encode((int) $visitorId),
            'event_sig' => $this->buildEventSignature(
                (string) $data['user_code'],
                (string) $data['campaign_code'],
                $pageviewCode
            ),
        ]);

    }

    /**
     * Recebe e persiste eventos de interação vinculados a uma pageview já coletada.
     *
     * Fluxo geral:
     * 1) aceita payload do navegador (incluindo envio como text/plain via sendBeacon/no-cors) e valida campos;
     * 2) normaliza dados do evento (tipo, alvo e metadados de formulário);
     * 3) autentica o vínculo entre usuário, campanha e pageview antes de gravar;
     * 4) registra o evento apenas quando existir pageview compatível com os identificadores informados.
     *
     * Autenticação/segurança aplicada no event:
     * - assinatura HMAC (`event_sig`) calculada a partir de `user_code`, `campaign_code` e `pageview_code`;
     * - comparação segura de assinatura para evitar adulteração do vínculo do evento;
     * - validação de integridade no banco para garantir que a pageview pertence à campanha/usuário informados.
     */
    public function event(Request $request)
    {
        $log = Log::channel('tracking_collect');

        // Aceita payload JSON mesmo quando o navegador envia como text/plain
        // (ex.: sendBeacon/fetch no-cors para evitar preflight CORS).
        if (empty($request->all())) {
            $rawBody = trim((string) $request->getContent());
            if ($rawBody !== '') {
                $decodedBody = json_decode($rawBody, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedBody)) {
                    $request->merge($decodedBody);
                }
            }
        }

        $data = $request->validate([
            'user_code' => 'required|string|max:32',
            'campaign_code' => 'required|string|max:32',
            'pageview_code' => 'required|string|max:32',
            'event_sig' => 'required|string|size:64|regex:/^[a-f0-9]+$/',
            'event_type' => 'required|string|in:link_click,form_submit,page_engaged',
            'event_ts' => 'nullable|integer|min:1',
            'target_url' => 'nullable|string',
            'element_id' => 'nullable|string',
            'element_name' => 'nullable|string',
            'element_classes' => 'nullable|string',
            'form_fields_checked' => 'nullable|integer|min:0|max:500',
            'form_fields_filled' => 'nullable|integer|min:0|max:500',
            'form_has_user_data' => 'nullable|boolean',
        ]);

        $truncate = static function ($value, int $limit): ?string {
            if ($value === null) {
                return null;
            }

            $normalized = trim((string) $value);
            if ($normalized === '') {
                return null;
            }

            return mb_substr($normalized, 0, $limit);
        };

        $userCode = (string) ($truncate($data['user_code'] ?? '', 32) ?? '');
        $campaignCode = (string) ($truncate($data['campaign_code'] ?? '', 32) ?? '');
        $pageviewCode = (string) ($truncate($data['pageview_code'] ?? '', 32) ?? '');
        $eventType = (string) ($truncate($data['event_type'] ?? '', 30) ?? '');
        $targetUrl = $truncate($data['target_url'] ?? null, 2000);
        $elementId = $truncate($data['element_id'] ?? null, 191);
        $elementName = $truncate($data['element_name'] ?? null, 191);
        $elementClasses = $truncate($data['element_classes'] ?? null, 500);

        $expectedEventSig = $this->buildEventSignature(
            $userCode,
            $campaignCode,
            $pageviewCode
        );

        if (!hash_equals($expectedEventSig, strtolower((string) $data['event_sig']))) {
            $log->warning('Tracking event rejeitado: assinatura inválida.', [
                'event_type' => (string) ($data['event_type'] ?? ''),
                'user_code' => $userCode,
                'campaign_code' => $campaignCode,
                'pageview_code' => $pageviewCode,
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Assinatura do evento inválida.',
            ], 422);
        }

        $userIdFromCode = app(HashidService::class)->decode($userCode);
        $campaignIdFromCode = app(HashidService::class)->decode($campaignCode);
        $pageviewIdFromCode = app(HashidService::class)->decode($pageviewCode);

        if (!$userIdFromCode || !$campaignIdFromCode || !$pageviewIdFromCode) {
            return response()->json([
                'message' => 'Tokens do evento inválidos.',
            ], 422);
        }

        $campaign = Campaign::query()
            ->where('id', $campaignIdFromCode)
            ->where('user_id', $userIdFromCode)
            ->where('code', $campaignCode)
            ->first();

        if (!$campaign) {
            return response()->json([
                'message' => 'Campanha inválida.',
            ], 422);
        }

        $allowedOrigin = Campaign::normalizeProductUrl($campaign->product_url);
        if (!$allowedOrigin) {
            return response()->json([
                'message' => 'Origem de tracking não configurada para a campanha.',
            ], 403);
        }

        $requestOrigin = $this->extractRequestOrigin($request);
        if (!$requestOrigin || !$this->originsMatch($allowedOrigin, $requestOrigin)) {
            return response()->json([
                'message' => 'Origem não autorizada para esta campanha.',
            ], 403);
        }

        $pageview = Pageview::query()
            ->where('id', $pageviewIdFromCode)
            ->where('user_id', $campaign->user_id)
            ->where('campaign_id', $campaign->id)
            ->first();

        if (!$pageview) {
            return response()->json([
                'message' => 'Pageview inválida para esta campanha.',
            ], 422);
        }

        $event = PageviewEvent::create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'pageview_id' => $pageview->id,
            'event_type' => $eventType,
            'target_url' => $targetUrl,
            'element_id' => $elementId,
            'element_name' => $elementName,
            'element_classes' => $elementClasses,
            'form_fields_checked' => $data['form_fields_checked'] ?? null,
            'form_fields_filled' => $data['form_fields_filled'] ?? null,
            'form_has_user_data' => array_key_exists('form_has_user_data', $data)
                ? (bool) $data['form_has_user_data']
                : null,
            'event_ts_ms' => $this->normalizeTimestampMs($data['event_ts'] ?? null),
        ]);

        return response()->json([
            'ok' => true,
            'event_id' => $event->id,
        ]);
    }

    protected function resolveTrafficSourceCategoryId(string $slug): ?int
    {
        if ($this->trafficSourceIdBySlug === []) {
            $this->trafficSourceIdBySlug = TrafficSourceCategory::query()
                ->pluck('id', 'slug')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        return $this->trafficSourceIdBySlug[$slug]
            ?? $this->trafficSourceIdBySlug['unknown']
            ?? null;
    }

    protected function resolveDeviceCategoryId(string $slug): ?int
    {
        if ($this->deviceCategoryIdBySlug === []) {
            $this->deviceCategoryIdBySlug = DeviceCategory::query()
                ->pluck('id', 'slug')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        return $this->deviceCategoryIdBySlug[$slug]
            ?? $this->deviceCategoryIdBySlug['unknown']
            ?? null;
    }

    protected function resolveBrowserId(string $slug): ?int
    {
        if ($this->browserIdBySlug === []) {
            $this->browserIdBySlug = Browser::query()
                ->pluck('id', 'slug')
                ->map(fn ($id) => (int) $id)
                ->toArray();
        }

        return $this->browserIdBySlug[$slug]
            ?? $this->browserIdBySlug['unknown']
            ?? null;
    }

    protected function extractRequestOrigin(Request $request): ?string
    {
        $origin = $request->headers->get('Origin');
        if ($origin) {
            return Campaign::normalizeProductUrl($origin);
        }

        $referer = $request->headers->get('Referer');
        if ($referer) {
            return Campaign::normalizeProductUrl($referer);
        }

        return null;
    }

    protected function originsMatch(string $allowedOrigin, string $requestOrigin): bool
    {
        return hash_equals($allowedOrigin, $requestOrigin);
    }

    //Retorna o script de acompanhamento
    public function script(Request $request)
    {
            // Token composto vindo da URL (?c={user_code}-{campaign_code}).
            $composedCode = trim((string) $request->query('c'));
            [$userCode, $campaignCode] = $this->parseComposedTrackingCode($composedCode);

            $decodedUserId = app(HashidService::class)->decode($userCode);
            $decodedCampaignId = app(HashidService::class)->decode($campaignCode);

            $campaign = null;
            if ($decodedUserId && $decodedCampaignId) {
                $campaign = Campaign::query()
                    ->with('affiliatePlatform:id,tracking_param_mapping')
                    ->where('id', $decodedCampaignId)
                    ->where('user_id', $decodedUserId)
                    ->where('code', $campaignCode)
                    ->first();
            }

            // Só entrega o JS quando os tokens batem com uma campanha válida do usuário.
            if (!$campaign) {
                return response()->make(
                    'console.error("[Attributa] Tokens de tracking inválidos");',
                    200,
                    [
                        'Content-Type'  => 'application/javascript',
                        'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                        'Pragma'        => 'no-cache',
                        'Expires'       => '0',
                    ]
                );
            }

            // 🔹 Caminho do arquivo JS base
            $path = resource_path('views/tracking/script.js');

            if (!File::exists($path)) {
                return response()->make(
                    'console.error("[Attributa] Script base não encontrado");',
                    500,
                    ['Content-Type' => 'application/javascript']
                );
            }

            // 🔹 Lê o JS base
            $js = File::get($path);

            // 🔹 Valores dinâmicos
            $endpoint = rtrim(config('app.url'), '/') . '/api/tracking/collect';
            $eventEndpoint = rtrim(config('app.url'), '/') . '/api/tracking/event';

            // 🔹 Replace seguro (JS válido)
            $authTs = time();
            $authNonce = Str::random(24);
            $authSig = $this->buildTrackingSignature($userCode, $campaignCode, $authTs, $authNonce);
            $trackingParamMapping = $campaign->affiliatePlatform?->tracking_param_mapping;
            $trackingParamKeys = is_array($trackingParamMapping)
                ? array_values(array_filter(array_map(
                    fn ($k) => trim((string) $k),
                    array_keys($trackingParamMapping)
                )))
                : [];

            $replacements = [
                "'{ENDPOINT}'"       => json_encode($endpoint),
                "'{EVENT_ENDPOINT}'" => json_encode($eventEndpoint),
                "'{USER_CODE}'"      => json_encode($userCode),
                "'{CAMPAIGN_CODE}'"  => json_encode($campaignCode),
                "'{AUTH_TS}'"        => json_encode($authTs),
                "'{AUTH_NONCE}'"     => json_encode($authNonce),
                "'{AUTH_SIG}'"       => json_encode($authSig),
                "'{TRACKING_PARAM_KEYS}'" => json_encode($trackingParamKeys),
            ];

            $js = str_replace(
                array_keys($replacements),
                array_values($replacements),
                $js
            );

            // 🔹 Retorna JS puro (stateless)
            return response()->make(
                $js,
                200,
                [
                    'Content-Type'  => 'application/javascript',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma'        => 'no-cache',
                    'Expires'       => '0',
                ]
            );
    }

    protected function parseComposedTrackingCode(string $composedCode): array
    {
        if ($composedCode === '') {
            return ['', ''];
        }

        $parts = explode('-', $composedCode, 2);
        if (count($parts) !== 2) {
            return ['', ''];
        }

        $userCode = trim((string) ($parts[0] ?? ''));
        $campaignCode = trim((string) ($parts[1] ?? ''));
        if ($userCode === '' || $campaignCode === '') {
            return ['', ''];
        }

        return [$userCode, $campaignCode];
    }

    protected function buildTrackingSignature(string $userCode, string $campaignCode, int $authTs, string $authNonce): string
    {
        $payload = implode('|', [$userCode, $campaignCode, $authTs, $authNonce]);
        $secret = (string) config('app.tracking_signature_secret', '');

        return hash_hmac('sha256', $payload, $secret);
    }

    protected function buildEventSignature(string $userCode, string $campaignCode, string $pageviewCode): string
    {
        $payload = implode('|', [$userCode, $campaignCode, $pageviewCode]);
        $secret = (string) config('app.tracking_signature_secret', '');

        return hash_hmac('sha256', $payload, $secret);
    }

    protected function isTrackingTimestampValid(int $authTs): bool
    {
        $window = (int) config('app.tracking_signature_window_seconds', 300);
        return abs(time() - $authTs) <= $window;
    }

    protected function trackingNonceCacheKey(string $nonce): string
    {
        return 'tracking:nonce:' . $nonce;
    }

    protected function normalizeTimestampMs(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $normalized = (int) $value;
        if ($normalized < 0) {
            return null;
        }

        return $normalized;
    }

    protected function resolveVisitorIdFromCode(mixed $value): ?int
    {
        $visitorCode = trim((string) $value);
        if ($visitorCode === '') {
            return null;
        }

        $decoded = app(HashidService::class)->decode($visitorCode);
        if (!$decoded || $decoded < 1) {
            return null;
        }

        return (int) $decoded;
    }
}
