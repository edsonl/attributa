<?php

namespace App\Http\Controllers;

use App\Models\Pageview;
use App\Models\Campaign;
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
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use JShrink\Minifier;
use Throwable;

class TrackingController extends Controller
{
    protected array $trafficSourceIdBySlug = [];

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
        $log = Log::channel($this->trackingLogChannel('tracking_collect'));
        $gclidAlertLog = Log::channel($this->trackingLogChannel('tracking_gclid_alert'));

        // Validação estrutural do payload recebido pelo snippet.
        $data = $request->validate([
            'user_code'    => 'required|string|max:32',
            'campaign_code' => 'required|string|max:32',
            'visitor_code' => 'nullable|string|max:32',
            'user_session' => 'nullable|string|max:64|regex:/^[A-Za-z0-9]+$/',
            'navigation_type' => 'nullable|string|in:navigate,reload,back_forward,prerender,unknown',
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

        // Resolve contexto da campanha preferindo Redis:
        // - chave tracking:campaign:{campaign_id} (TTL longo);
        // - fallback em banco quando cache miss;
        // - valida consistência forte user_id + campaign_id + campaign_code.
        $campaign = $this->resolveCampaignContext(
            (int) $userIdFromCode,
            (int) $campaignIdFromCode,
            (string) $data['campaign_code']
        );

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
        $allowedOrigin = (string) ($campaign['allowed_origin'] ?? '');
        if (!$allowedOrigin) {
            $log->warning('Tracking collect rejeitado: campanha sem origem configurada.', [
                'campaign_id' => $campaign['id'] ?? null,
                'campaign_code' => $campaign['code'] ?? null,
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'message' => 'Origem de tracking não configurada para a campanha.',
            ], 403);
        }

        $requestOrigin = $this->extractRequestOrigin($request);
        if (!$requestOrigin || !$this->originsMatch($allowedOrigin, $requestOrigin)) {
            $log->warning('Tracking collect rejeitado: origem não autorizada.', [
                'campaign_id' => $campaign['id'] ?? null,
                'campaign_code' => $campaign['code'] ?? null,
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

        /*
         |----------------------------------------------------------------------
         | Fluxo Redis no collect (chaves e estratégia)
         |----------------------------------------------------------------------
         | 1) tracking:campaign:{campaign_id}
         |    - cache de metadados da campanha para evitar SELECT repetido.
         |    - guarda id, user_id, code, nome e allowed_origin.
         |
         | 2) tracking:pv:{user_code}:{campaign_code}:{pageview_code}
         |    - contexto resolvido da pageview.
         |    - estrutura principal para debug e reuso de sessão.
         |    - contém:
         |      * campanha (id, nome, user_id, code)
         |      * pageview (campos atuais da linha salva em banco)
         |      * timing (last_collect_at_ms, last_hit_at_ms)
         |
         | 3) tracking:last:{user_code}:{campaign_code}:{visitor_code}
         |    - ponte para o último contexto da combinação visitante+campanha.
         |    - permite detectar segundo collect sem tocar no banco.
         |
         | 4) tracking:hit_gate:{campaign_id}:{visitor_id}
         |    - anti-refresh/F5: só permite incrementar hit após intervalo mínimo.
         |
         | Decisão no collect:
         | - Se tracking:last existir e estiver dentro da janela de deduplicação:
         |   * não cria nova pageview;
         |   * opcionalmente incrementa campaign_visitors se passou o intervalo mínimo;
         |   * retorna o mesmo pageview_code + visitor_code + event_sig.
         | - Fora da janela (ou cache miss):
         |   * segue fluxo normal: classifica, cria pageview e atualiza Redis.
         */
        $now = now();
        $nowMs = $now->valueOf();
        $visitorCode = trim((string) ($data['visitor_code'] ?? ''));
        $userSession = trim((string) ($data['user_session'] ?? ''));
        $visitorId = $this->resolveVisitorIdFromCode($visitorCode);

        $reuseContext = $this->resolveReusableCollectContext(
            userCode: (string) $data['user_code'],
            campaignCode: (string) $data['campaign_code'],
            visitorCode: $visitorCode,
            nowMs: $nowMs
        );

        if ($reuseContext !== null) {
            $shouldIncrementHit = $this->shouldIncrementHitByThrottle(
                campaignId: (int) $campaign['id'],
                visitorId: (int) $reuseContext['visitor_id'],
                nowMs: $nowMs
            );

            if ($shouldIncrementHit) {
                $this->upsertCampaignVisitorHit(
                    campaignId: (int) $campaign['id'],
                    visitorId: (int) $reuseContext['visitor_id'],
                    now: $now,
                    nowMs: $nowMs
                );
                $this->touchHitGate(
                    campaignId: (int) $campaign['id'],
                    visitorId: (int) $reuseContext['visitor_id'],
                    nowMs: $nowMs
                );
            }

            $this->touchReusableCollectContext(
                userCode: (string) $data['user_code'],
                campaignCode: (string) $data['campaign_code'],
                visitorCode: $reuseContext['visitor_code'],
                pageviewCode: $reuseContext['pageview_code'],
                userSession: $userSession,
                nowMs: $nowMs,
                hitUpdated: $shouldIncrementHit
            );

            return response()->json([
                'pageview_code' => $reuseContext['pageview_code'],
                'visitor_code' => $reuseContext['visitor_code'],
                'event_sig' => $this->buildEventSignature(
                    (string) $data['user_code'],
                    (string) $data['campaign_code'],
                    $reuseContext['pageview_code']
                ),
            ]);
        }

        // Classificação técnica da visita (origem, device, browser) apenas quando houver necessidade de nova pageview.
        $classification = app(PageviewClassificationService::class)->classify($data, $request->ip());
        $trafficSourceCategoryId = $this->resolveTrafficSourceCategoryId((string) ($classification['traffic_source_slug'] ?? 'unknown'));
        $trafficSourceReason = mb_substr((string) ($classification['traffic_source_reason'] ?? ''), 0, 191);
        $timestampMs = $this->normalizeTimestampMs($data['timestamp'] ?? null);
        $deviceClassification = [
            'device_category_id' => null,
            'browser_id' => null,
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
                'user_id'      => (int) $campaign['user_id'],
                'campaign_id'   => (int) $campaign['id'],
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
            $this->upsertCampaignVisitorHit(
                campaignId: (int) $campaign['id'],
                visitorId: (int) $visitorId,
                now: $now,
                nowMs: $nowMs
            );

            return [$pageview, (int) $visitorId];
        });

        $log->info('Tracking collect salvo com sucesso.', [
            'pageview_id' => $pageview->id,
            'visitor_id' => $visitorId,
            'campaign_id' => $campaign['id'],
            'campaign_code' => $campaign['code'],
            'request_origin' => $requestOrigin,
            'ip' => $request->ip(),
        ]);

        // Retorna hashid da pageview para compor subids/código composto de callback.
        $pageviewCode = app(HashidService::class)->encode((int) $pageview->id);
        $visitorCode = app(HashidService::class)->encode((int) $visitorId);

        $this->persistCollectContext(
            campaign: $campaign,
            pageview: $pageview,
            userCode: (string) $data['user_code'],
            campaignCode: (string) $data['campaign_code'],
            pageviewCode: $pageviewCode,
            visitorCode: $visitorCode,
            visitorId: (int) $visitorId,
            userSession: $userSession,
            nowMs: now()->valueOf()
        );

        return response()->json([
            'pageview_code' => $pageviewCode,
            'visitor_code' => $visitorCode,
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
     * 3) valida assinatura e recupera contexto exclusivamente do Redis;
     * 4) se contexto Redis existir e for consistente, grava o evento;
     * 5) se contexto Redis não existir (campanha/pageview), ignora sem fallback em banco.
     *
     * Autenticação/segurança aplicada no event:
     * - assinatura HMAC (`event_sig`) calculada a partir de `user_code`, `campaign_code` e `pageview_code`;
     * - comparação segura de assinatura para evitar adulteração do vínculo do evento;
     * - validação de integridade no banco para garantir que a pageview pertence à campanha/usuário informados.
     */
    public function event(Request $request)
    {
        $log = Log::channel($this->trackingLogChannel('tracking_collect'));
        $ignoredLog = Log::channel($this->trackingLogChannel('tracking_event_ignored'));
        $this->trackingRedisTempLog('event.redis.connection.info', $this->trackingRedisConnectionInfo());

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
            'event_type' => 'required|string|in:link_click,form_submit,page_engaged,navigation_reload',
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

        $eventContext = $this->resolveEventContextFromRedis(
            $userCode,
            $campaignCode,
            $pageviewCode,
            (int) $userIdFromCode,
            (int) $campaignIdFromCode,
            (int) $pageviewIdFromCode
        );

        if (!$eventContext) {
            $ignoredLog->info('Tracking event ignorado: contexto Redis ausente/inconsistente.', [
                'user_code' => $userCode,
                'campaign_code' => $campaignCode,
                'pageview_code' => $pageviewCode,
                'ip' => $request->ip(),
                'reason' => 'redis_context_missing',
            ]);

            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'redis_context_missing',
            ]);
        }

        $allowedOrigin = (string) ($eventContext['allowed_origin'] ?? '');
        if ($allowedOrigin === '') {
            $ignoredLog->info('Tracking event ignorado: campanha sem origem no Redis.', [
                'campaign_id' => $eventContext['campaign_id'] ?? null,
                'pageview_id' => $eventContext['pageview_id'] ?? null,
                'reason' => 'redis_campaign_origin_missing',
            ]);

            return response()->json([
                'ok' => true,
                'ignored' => true,
                'reason' => 'redis_campaign_origin_missing',
            ]);
        }

        $requestOrigin = $this->extractRequestOrigin($request);
        if (!$requestOrigin || !$this->originsMatch($allowedOrigin, $requestOrigin)) {
            return response()->json([
                'message' => 'Origem não autorizada para esta campanha.',
            ], 403);
        }

        $event = PageviewEvent::create([
            'user_id' => (int) $eventContext['user_id'],
            'campaign_id' => (int) $eventContext['campaign_id'],
            'pageview_id' => (int) $eventContext['pageview_id'],
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

    /**
     * @return array{id:int,user_id:int,code:string,name:?string,allowed_origin:string}|null
     */
    protected function resolveCampaignContext(int $userId, int $campaignId, string $campaignCode): ?array
    {
        $redis = $this->trackingRedis();
        $campaignKey = $this->trackingCampaignKey($campaignId);
        $this->trackingRedisTempLog('collect.redis.get.campaign.start', [
            'key' => $campaignKey,
            'campaign_id' => $campaignId,
            'user_id' => $userId,
        ]);
        $cached = $this->decodeRedisJson($redis->get($campaignKey));

        if (is_array($cached)) {
            $cachedUserId = (int) ($cached['user_id'] ?? 0);
            $cachedCode = (string) ($cached['code'] ?? '');

            if ($cachedUserId === $userId && $cachedCode !== '' && hash_equals($cachedCode, $campaignCode)) {
                $this->trackingRedisTempLog('collect.redis.get.campaign.hit', [
                    'key' => $campaignKey,
                    'campaign_id' => (int) ($cached['id'] ?? $campaignId),
                    'user_id' => $cachedUserId,
                ]);
                return [
                    'id' => (int) ($cached['id'] ?? $campaignId),
                    'user_id' => $cachedUserId,
                    'code' => $cachedCode,
                    'name' => isset($cached['name']) ? (string) $cached['name'] : null,
                    'allowed_origin' => (string) ($cached['allowed_origin'] ?? ''),
                ];
            }

            $this->trackingRedisTempLog('collect.redis.get.campaign.mismatch', [
                'key' => $campaignKey,
                'expected_user_id' => $userId,
                'cached_user_id' => $cachedUserId,
            ], 'warning');
        } else {
            $this->trackingRedisTempLog('collect.redis.get.campaign.miss', [
                'key' => $campaignKey,
            ]);
        }

        $campaign = Campaign::query()
            ->where('id', $campaignId)
            ->where('user_id', $userId)
            ->where('code', $campaignCode)
            ->first(['id', 'user_id', 'code', 'name', 'product_url']);

        if (!$campaign) {
            return null;
        }

        $payload = [
            'id' => (int) $campaign->id,
            'user_id' => (int) $campaign->user_id,
            'code' => (string) $campaign->code,
            'name' => $campaign->name !== null ? (string) $campaign->name : null,
            'allowed_origin' => (string) Campaign::normalizeProductUrl((string) $campaign->product_url),
        ];

        try {
            $saved = (bool) $redis->setex(
                $campaignKey,
                $this->trackingCampaignTtlSeconds(),
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $this->trackingRedisTempLog('collect.redis.set.campaign', [
                'key' => $campaignKey,
                'campaign_id' => (int) $campaign->id,
                'saved' => $saved,
                'ttl' => $this->trackingCampaignTtlSeconds(),
            ], $saved ? 'info' : 'warning');

            Log::channel($this->trackingLogChannel('tracking_collect'))->info(
                $saved
                    ? 'Tracking Redis: campanha salva com sucesso.'
                    : 'Tracking Redis: campanha nao foi salva.',
                [
                    'redis_key' => $campaignKey,
                    'campaign_id' => (int) $campaign->id,
                    'campaign_code' => (string) $campaign->code,
                    'saved' => $saved,
                ]
            );
        } catch (\Throwable $e) {
            $this->trackingRedisTempLog('collect.redis.set.campaign.error', [
                'key' => $campaignKey,
                'campaign_id' => (int) $campaign->id,
                'error' => $e->getMessage(),
            ], 'warning');
            Log::channel($this->trackingLogChannel('tracking_collect'))->warning(
                'Tracking Redis: falha ao salvar campanha.',
                [
                    'redis_key' => $campaignKey,
                    'campaign_id' => (int) $campaign->id,
                    'campaign_code' => (string) $campaign->code,
                    'saved' => false,
                    'error' => $e->getMessage(),
                ]
            );
        }

        return $payload;
    }

    /**
     * @return array{pageview_code:string,visitor_code:string,visitor_id:int,last_collect_at_ms:int}|null
     */
    protected function resolveReusableCollectContext(
        string $userCode,
        string $campaignCode,
        string $visitorCode,
        int $nowMs
    ): ?array {
        if ($visitorCode === '') {
            return null;
        }

        $redis = $this->trackingRedis();
        $lastKey = $this->trackingLastCollectKey($userCode, $campaignCode, $visitorCode);
        $this->trackingRedisTempLog('collect.redis.get.last.start', [
            'key' => $lastKey,
            'visitor_code' => $visitorCode,
        ]);
        $payload = $this->decodeRedisJson($redis->get($lastKey));
        if (!is_array($payload)) {
            $this->trackingRedisTempLog('collect.redis.get.last.miss', [
                'key' => $lastKey,
            ]);
            return null;
        }

        $pageviewCode = trim((string) ($payload['pageview_code'] ?? ''));
        $cachedVisitorCode = trim((string) ($payload['visitor_code'] ?? ''));
        $visitorId = (int) ($payload['visitor_id'] ?? 0);
        $lastCollectAtMs = (int) ($payload['last_collect_at_ms'] ?? 0);
        if ($pageviewCode === '' || $cachedVisitorCode === '' || $visitorId < 1 || $lastCollectAtMs < 1) {
            $this->trackingRedisTempLog('collect.redis.get.last.invalid_payload', [
                'key' => $lastKey,
            ], 'warning');
            return null;
        }

        $elapsedMs = $nowMs - $lastCollectAtMs;
        if ($elapsedMs < 0 || $elapsedMs > ($this->trackingDedupWindowSeconds() * 1000)) {
            $this->trackingRedisTempLog('collect.redis.dedup.outside_window', [
                'key' => $lastKey,
                'elapsed_ms' => $elapsedMs,
                'dedup_window_seconds' => $this->trackingDedupWindowSeconds(),
            ]);
            return null;
        }

        $pvKey = $this->trackingPageviewKey($userCode, $campaignCode, $pageviewCode);
        $hasPvContext = $redis->get($pvKey) !== null;
        if (!$hasPvContext) {
            $this->trackingRedisTempLog('collect.redis.get.pv.miss', [
                'key' => $pvKey,
                'pageview_code' => $pageviewCode,
            ]);
            return null;
        }
        $this->trackingRedisTempLog('collect.redis.dedup.reuse_hit', [
            'last_key' => $lastKey,
            'pv_key' => $pvKey,
            'pageview_code' => $pageviewCode,
            'visitor_id' => $visitorId,
        ]);

        return [
            'pageview_code' => $pageviewCode,
            'visitor_code' => $cachedVisitorCode,
            'visitor_id' => $visitorId,
            'last_collect_at_ms' => $lastCollectAtMs,
        ];
    }

    protected function touchReusableCollectContext(
        string $userCode,
        string $campaignCode,
        string $visitorCode,
        string $pageviewCode,
        string $userSession,
        int $nowMs,
        bool $hitUpdated
    ): void {
        $redis = $this->trackingRedis();
        $ttl = $this->trackingPageviewTtlSeconds();
        $lastKey = $this->trackingLastCollectKey($userCode, $campaignCode, $visitorCode);
        $this->trackingRedisTempLog('collect.redis.touch.last.start', [
            'key' => $lastKey,
            'ttl' => $ttl,
            'hit_updated' => $hitUpdated,
        ]);
        $lastPayload = $this->decodeRedisJson($redis->get($lastKey));

        if (!is_array($lastPayload)) {
            $lastPayload = [
                'pageview_code' => $pageviewCode,
                'visitor_code' => $visitorCode,
                'user_session' => $userSession,
                'visitor_id' => 0,
                'last_collect_at_ms' => $nowMs,
                'last_hit_at_ms' => $hitUpdated ? $nowMs : 0,
            ];
        }

        $lastPayload['last_collect_at_ms'] = $nowMs;
        if ($userSession !== '') {
            $lastPayload['user_session'] = $userSession;
        }
        if ($hitUpdated) {
            $lastPayload['last_hit_at_ms'] = $nowMs;
        }

        $lastSaved = (bool) $redis->setex(
            $lastKey,
            $ttl,
            json_encode($lastPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->trackingRedisTempLog('collect.redis.touch.last.saved', [
            'key' => $lastKey,
            'saved' => $lastSaved,
        ], $lastSaved ? 'info' : 'warning');

        $pvKey = $this->trackingPageviewKey($userCode, $campaignCode, $pageviewCode);
        $this->trackingRedisTempLog('collect.redis.touch.pv.start', [
            'key' => $pvKey,
            'ttl' => $ttl,
            'hit_updated' => $hitUpdated,
        ]);
        $pvPayload = $this->decodeRedisJson($redis->get($pvKey));
        if (!is_array($pvPayload)) {
            $this->trackingRedisTempLog('collect.redis.touch.pv.miss', [
                'key' => $pvKey,
            ]);
            return;
        }

        $timing = is_array($pvPayload['timing'] ?? null) ? $pvPayload['timing'] : [];
        $timing['last_collect_at_ms'] = $nowMs;
        if ($hitUpdated) {
            $timing['last_hit_at_ms'] = $nowMs;
        }
        $pvPayload['timing'] = $timing;

        $pvSaved = (bool) $redis->setex(
            $pvKey,
            $ttl,
            json_encode($pvPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $this->trackingRedisTempLog('collect.redis.touch.pv.saved', [
            'key' => $pvKey,
            'saved' => $pvSaved,
        ], $pvSaved ? 'info' : 'warning');
    }

    /**
     * Persiste contexto resolvido do collect para reuso em próximos requests.
     *
     * @param array{id:int,user_id:int,code:string,name:?string,allowed_origin:string} $campaign
     */
    protected function persistCollectContext(
        array $campaign,
        Pageview $pageview,
        string $userCode,
        string $campaignCode,
        string $pageviewCode,
        string $visitorCode,
        int $visitorId,
        string $userSession,
        int $nowMs
    ): void {
        $redis = $this->trackingRedis();
        $ttl = $this->trackingPageviewTtlSeconds();
        $pvKey = $this->trackingPageviewKey($userCode, $campaignCode, $pageviewCode);
        $lastKey = $this->trackingLastCollectKey($userCode, $campaignCode, $visitorCode);

        $payload = [
            'v' => 1,
            'campanha' => [
                'id' => (int) $campaign['id'],
                'nome' => $campaign['name'],
                'user_id' => (int) $campaign['user_id'],
                'code' => (string) $campaign['code'],
            ],
            'pageview' => $pageview->toArray(),
            'timing' => [
                'last_collect_at_ms' => $nowMs,
                'last_hit_at_ms' => $nowMs,
            ],
        ];

        $lastPayload = [
            'pageview_code' => $pageviewCode,
            'visitor_code' => $visitorCode,
            'user_session' => $userSession,
            'visitor_id' => $visitorId,
            'last_collect_at_ms' => $nowMs,
            'last_hit_at_ms' => $nowMs,
        ];

        $pvSaved = false;
        $lastSaved = false;

        try {
            $pvSaved = (bool) $redis->setex(
                $pvKey,
                $ttl,
                json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $lastSaved = (bool) $redis->setex(
                $lastKey,
                $ttl,
                json_encode($lastPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
            $this->trackingRedisTempLog('collect.redis.set.context', [
                'pageview_id' => (int) $pageview->id,
                'campaign_id' => (int) $campaign['id'],
                'keys' => [
                    'pv' => $pvKey,
                    'last' => $lastKey,
                ],
                'saved' => [
                    'pv' => $pvSaved,
                    'last' => $lastSaved,
                ],
                'ttl' => $ttl,
            ], ($pvSaved && $lastSaved) ? 'info' : 'warning');

            Log::channel($this->trackingLogChannel('tracking_collect'))->info(
                ($pvSaved && $lastSaved)
                    ? 'Tracking Redis: pageview e ponte de ultimo collect salvos com sucesso.'
                    : 'Tracking Redis: salvamento parcial/sem sucesso para pageview e/ou ultimo collect.',
                [
                    'pageview_id' => (int) $pageview->id,
                    'campaign_id' => (int) $campaign['id'],
                    'redis_keys' => [
                        'pageview' => $pvKey,
                        'last' => $lastKey,
                    ],
                    'saved' => [
                        'pageview' => $pvSaved,
                        'last' => $lastSaved,
                    ],
                ]
            );
        } catch (\Throwable $e) {
            $this->trackingRedisTempLog('collect.redis.set.context.error', [
                'pageview_id' => (int) $pageview->id,
                'campaign_id' => (int) $campaign['id'],
                'keys' => [
                    'pv' => $pvKey,
                    'last' => $lastKey,
                ],
                'error' => $e->getMessage(),
            ], 'warning');
            Log::channel($this->trackingLogChannel('tracking_collect'))->warning(
                'Tracking Redis: falha ao salvar pageview e/ou ultimo collect.',
                [
                    'pageview_id' => (int) $pageview->id,
                    'campaign_id' => (int) $campaign['id'],
                    'redis_keys' => [
                        'pageview' => $pvKey,
                        'last' => $lastKey,
                    ],
                    'saved' => [
                        'pageview' => $pvSaved,
                        'last' => $lastSaved,
                    ],
                    'error' => $e->getMessage(),
                ]
            );
        }

        $this->touchHitGate((int) $campaign['id'], $visitorId, $nowMs);
    }

    protected function shouldIncrementHitByThrottle(int $campaignId, int $visitorId, int $nowMs): bool
    {
        $redis = $this->trackingRedis();
        $hitGateKey = $this->trackingHitGateKey($campaignId, $visitorId);
        $this->trackingRedisTempLog('collect.redis.get.hit_gate.start', [
            'key' => $hitGateKey,
            'campaign_id' => $campaignId,
            'visitor_id' => $visitorId,
        ]);
        $lastHitMs = (int) $redis->get($hitGateKey);
        if ($lastHitMs < 1) {
            $this->trackingRedisTempLog('collect.redis.get.hit_gate.miss', [
                'key' => $hitGateKey,
            ]);
            return true;
        }

        $elapsedMs = $nowMs - $lastHitMs;
        $shouldIncrement = $elapsedMs >= ($this->trackingMinHitIntervalSeconds() * 1000);
        $this->trackingRedisTempLog('collect.redis.get.hit_gate.hit', [
            'key' => $hitGateKey,
            'last_hit_ms' => $lastHitMs,
            'elapsed_ms' => $elapsedMs,
            'min_hit_interval_seconds' => $this->trackingMinHitIntervalSeconds(),
            'should_increment' => $shouldIncrement,
        ]);

        return $shouldIncrement;
    }

    protected function touchHitGate(int $campaignId, int $visitorId, int $nowMs): void
    {
        $redis = $this->trackingRedis();
        $ttl = $this->trackingHitGateTtlSeconds();

        $key = $this->trackingHitGateKey($campaignId, $visitorId);
        $saved = (bool) $redis->setex(
            $key,
            $ttl,
            (string) $nowMs
        );
        $this->trackingRedisTempLog('collect.redis.set.hit_gate', [
            'key' => $key,
            'campaign_id' => $campaignId,
            'visitor_id' => $visitorId,
            'saved' => $saved,
            'ttl' => $ttl,
            'now_ms' => $nowMs,
        ], $saved ? 'info' : 'warning');
    }

    protected function upsertCampaignVisitorHit(int $campaignId, int $visitorId, \Illuminate\Support\Carbon $now, int $nowMs): void
    {
        DB::statement(
            'INSERT INTO campaign_visitors
                (campaign_id, visitor_id, first_seen_at, last_seen_at, hits, created_at, updated_at)
             VALUES (?, ?, ?, ?, 1, ?, ?)
             ON DUPLICATE KEY UPDATE
                last_seen_at = VALUES(last_seen_at),
                hits = hits + 1,
                updated_at = VALUES(updated_at)',
            [
                $campaignId,
                $visitorId,
                $nowMs,
                $nowMs,
                $now,
                $now,
            ]
        );
    }

    protected function trackingRedis(): \Illuminate\Redis\Connections\Connection
    {
        return Redis::connection((string) config('tracking.redis.connection', 'tracking'));
    }

    protected function trackingPrefix(): string
    {
        return trim((string) config('tracking.redis.prefix', 'tracking'));
    }

    protected function trackingCampaignTtlSeconds(): int
    {
        return max((int) config('tracking.redis.campaign_ttl_seconds', 86400), 60);
    }

    protected function trackingPageviewTtlSeconds(): int
    {
        return max((int) config('tracking.redis.pageview_ttl_seconds', 3600), 60);
    }

    protected function trackingDedupWindowSeconds(): int
    {
        return max((int) config('tracking.collect.dedup_window_seconds', 300), 1);
    }

    protected function trackingMinHitIntervalSeconds(): int
    {
        return max((int) config('tracking.collect.min_hit_interval_seconds', 30), 1);
    }

    protected function trackingHitGateTtlSeconds(): int
    {
        return max((int) config('tracking.collect.hit_gate_ttl_seconds', 90), 10);
    }

    protected function trackingLogsEnabled(): bool
    {
        return (bool) config('tracking.logs.enabled', true);
    }

    protected function trackingLogChannel(string $channel): string
    {
        return $this->trackingLogsEnabled() ? $channel : 'null';
    }

    /**
     * Log temporario Redis: habilita investigacao detalhada de leituras/escritas no collect/event.
     */
    protected function trackingRedisTempLog(string $message, array $context = [], string $level = 'info'): void
    {
        if (str_starts_with($message, 'collect.redis.')) {
            return;
        }

        $logger = Log::channel($this->trackingLogChannel('tracking_redis_temp'));

        if ($level === 'warning') {
            $logger->warning($message, $context);
            return;
        }

        $logger->info($message, $context);
    }

    /**
     * Log temporario Redis: snapshot de conexao para diagnostico (connection, host, porta, db e prefixos).
     */
    protected function trackingRedisConnectionInfo(): array
    {
        $connectionName = (string) config('tracking.redis.connection', 'tracking');
        $connectionConfig = config('database.redis.' . $connectionName);

        return [
            'connection' => $connectionName,
            'client' => (string) config('database.redis.client', 'phpredis'),
            'host' => is_array($connectionConfig) ? (string) ($connectionConfig['host'] ?? '') : '',
            'port' => is_array($connectionConfig) ? (string) ($connectionConfig['port'] ?? '') : '',
            'database' => is_array($connectionConfig) ? (string) ($connectionConfig['database'] ?? '') : '',
            'username' => is_array($connectionConfig) ? (string) ($connectionConfig['username'] ?? '') : '',
            'password_configured' => is_array($connectionConfig) ? !empty($connectionConfig['password']) : false,
            'tracking_prefix' => $this->trackingPrefix(),
            'global_prefix' => (string) config('database.redis.options.prefix', ''),
        ];
    }

    protected function trackingCampaignKey(int $campaignId): string
    {
        return $this->trackingPrefix() . ':campaign:' . $campaignId;
    }

    protected function trackingPageviewKey(string $userCode, string $campaignCode, string $pageviewCode): string
    {
        return $this->trackingPrefix() . ':pv:' . $userCode . ':' . $campaignCode . ':' . $pageviewCode;
    }

    protected function trackingLastCollectKey(string $userCode, string $campaignCode, string $visitorCode): string
    {
        return $this->trackingPrefix() . ':last:' . $userCode . ':' . $campaignCode . ':' . $visitorCode;
    }

    protected function trackingHitGateKey(int $campaignId, int $visitorId): string
    {
        return $this->trackingPrefix() . ':hit_gate:' . $campaignId . ':' . $visitorId;
    }

    protected function decodeRedisJson(mixed $payload): ?array
    {
        if (!is_string($payload) || trim($payload) === '') {
            return null;
        }

        $decoded = json_decode($payload, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve contexto do endpoint event exclusivamente via Redis.
     *
     * @return array{user_id:int,campaign_id:int,pageview_id:int,allowed_origin:string}|null
     */
    protected function resolveEventContextFromRedis(
        string $userCode,
        string $campaignCode,
        string $pageviewCode,
        int $userIdFromCode,
        int $campaignIdFromCode,
        int $pageviewIdFromCode
    ): ?array {
        $redis = $this->trackingRedis();

        $pvKey = $this->trackingPageviewKey($userCode, $campaignCode, $pageviewCode);
        $this->trackingRedisTempLog('event.redis.get.pv.start', [
            'key' => $pvKey,
            'campaign_id_from_code' => $campaignIdFromCode,
            'pageview_id_from_code' => $pageviewIdFromCode,
        ]);
        $pvPayload = $this->decodeRedisJson($redis->get($pvKey));
        if (!is_array($pvPayload)) {
            $this->trackingRedisTempLog('event.redis.get.pv.miss', [
                'key' => $pvKey,
            ]);
            return null;
        }
        $this->trackingRedisTempLog('event.redis.get.pv.hit', [
            'key' => $pvKey,
        ]);

        $campaignFromPv = is_array($pvPayload['campanha'] ?? null) ? $pvPayload['campanha'] : [];
        $pageviewFromPv = is_array($pvPayload['pageview'] ?? null) ? $pvPayload['pageview'] : [];

        $campaignId = (int) ($campaignFromPv['id'] ?? 0);
        $campaignUserId = (int) ($campaignFromPv['user_id'] ?? 0);
        $campaignCodeFromPv = trim((string) ($campaignFromPv['code'] ?? ''));
        $pageviewId = (int) ($pageviewFromPv['id'] ?? 0);

        if (
            $campaignId < 1
            || $campaignUserId < 1
            || $pageviewId < 1
            || $campaignId !== $campaignIdFromCode
            || $campaignUserId !== $userIdFromCode
            || $pageviewId !== $pageviewIdFromCode
            || $campaignCodeFromPv === ''
            || !hash_equals($campaignCodeFromPv, $campaignCode)
        ) {
            $this->trackingRedisTempLog('event.redis.get.pv.invalid_payload', [
                'key' => $pvKey,
                'campaign_id' => $campaignId,
                'campaign_user_id' => $campaignUserId,
                'pageview_id' => $pageviewId,
            ], 'warning');
            return null;
        }

        $campaignKey = $this->trackingCampaignKey($campaignId);
        $this->trackingRedisTempLog('event.redis.get.campaign.start', [
            'key' => $campaignKey,
            'campaign_id' => $campaignId,
        ]);
        $campaignPayload = $this->decodeRedisJson($redis->get($campaignKey));
        if (!is_array($campaignPayload)) {
            $this->trackingRedisTempLog('event.redis.get.campaign.miss', [
                'key' => $campaignKey,
            ]);
            return null;
        }
        $this->trackingRedisTempLog('event.redis.get.campaign.hit', [
            'key' => $campaignKey,
        ]);

        $allowedOrigin = trim((string) ($campaignPayload['allowed_origin'] ?? ''));
        $campaignCodeCached = trim((string) ($campaignPayload['code'] ?? ''));
        $campaignUserCached = (int) ($campaignPayload['user_id'] ?? 0);

        if (
            $campaignCodeCached === ''
            || $campaignUserCached !== $userIdFromCode
            || !hash_equals($campaignCodeCached, $campaignCode)
        ) {
            $this->trackingRedisTempLog('event.redis.get.campaign.invalid_payload', [
                'key' => $campaignKey,
                'campaign_user_cached' => $campaignUserCached,
                'user_id_from_code' => $userIdFromCode,
            ], 'warning');
            return null;
        }

        $this->trackingRedisTempLog('event.redis.context.resolved', [
            'campaign_id' => $campaignId,
            'pageview_id' => $pageviewId,
            'user_id' => $campaignUserId,
        ]);

        return [
            'user_id' => $campaignUserId,
            'campaign_id' => $campaignId,
            'pageview_id' => $pageviewId,
            'allowed_origin' => $allowedOrigin,
        ];
    }

    //Retorna o script de acompanhamento
    public function script(Request $request)
    {
        $log = Log::channel($this->trackingLogChannel('tracking_collect'));

        // Token composto vindo da URL (?c={user_code}-{campaign_code}).
        $composedCode = trim((string) $request->query('c'));
        [$userCode, $campaignCode] = $this->parseComposedTrackingCode($composedCode);

        $decodedUserId = app(HashidService::class)->decode($userCode);
        $decodedCampaignId = app(HashidService::class)->decode($campaignCode);

        $campaign = null;
        if ($decodedUserId && $decodedCampaignId && $composedCode !== '') {
            $campaign = Campaign::query()
                ->with('affiliatePlatform:id,tracking_param_mapping')
                ->where('id', $decodedCampaignId)
                ->where('user_id', $decodedUserId)
                ->where('code', $campaignCode)
                ->first();
        }

        if ($composedCode === '') {
            $log->warning('Composed code not found in request');
        }

        // Só entrega o JS quando os tokens batem com uma campanha válida do usuário.
        if (!$campaign) {
            return response()->make(
                'console.error("[Leadnode] Tokens de tracking inválidos");',
                200,
                [
                    'Content-Type'  => 'application/javascript',
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                    'Pragma'        => 'no-cache',
                    'Expires'       => '0',
                ]
            );
        }

        $jsTemplate = $this->resolveTrackingScriptTemplate();
        if ($jsTemplate === null) {
            return response()->make(
                'console.error("[Leadnode] Script base não encontrado");',
                500,
                ['Content-Type' => 'application/javascript']
            );
        }

        // Valores dinâmicos.
        $endpoint = rtrim(config('app.url'), '/') . '/api/tracking/collect';
        $eventEndpoint = rtrim(config('app.url'), '/') . '/api/tracking/event';

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
            $jsTemplate
        );

        // Retorna JS puro (stateless).
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

    protected function resolveTrackingScriptTemplate(): ?string
    {
        $cacheKey = $this->trackingScriptTemplateCacheKey();
        $redis = $this->trackingRedis();

        try {
            $cached = $redis->get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $template = $this->loadAndMinifyTrackingScriptTemplate();
            if ($template !== null && $template !== '') {
                $redis->setex($cacheKey, $this->trackingScriptTemplateTtlSeconds(), $template);
            }

            return $template;
        } catch (Throwable $e) {
            Log::channel($this->trackingLogChannel('tracking_collect'))->warning(
                'Unable to load tracking script template from tracking Redis.',
                ['error' => $e->getMessage()]
            );
        }

        return $this->loadAndMinifyTrackingScriptTemplate();
    }

    protected function loadAndMinifyTrackingScriptTemplate(): ?string
    {
        $path = resource_path('views/tracking/script.js');

        if (!File::exists($path)) {
            return null;
        }

        $js = File::get($path);

        try {
            return Minifier::minify($js, ['flaggedComments' => false]);
        } catch (Throwable $e) {
            Log::channel($this->trackingLogChannel('tracking_collect'))->warning(
                'Unable to minify tracking script template. Falling back to the original asset.',
                ['error' => $e->getMessage()]
            );

            return $js;
        }
    }

    protected function trackingScriptTemplateCacheKey(): string
    {
        return $this->trackingPrefix() . ':script:template:v1';
    }

    protected function trackingScriptTemplateTtlSeconds(): int
    {
        return max((int) config('tracking.redis.script_template_ttl_seconds', 2592000), 60);
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
