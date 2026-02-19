<?php

namespace App\Http\Controllers;

use App\Models\Pageview;
use App\Models\Campaign;
use App\Models\Browser;
use App\Models\DeviceCategory;
use App\Models\TrafficSourceCategory;
use App\Services\HashidService;
use App\Services\PageviewClassificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TrackingController extends Controller
{
    protected array $trafficSourceIdBySlug = [];
    protected array $deviceCategoryIdBySlug = [];
    protected array $browserIdBySlug = [];

    public function collect(Request $request)
    {
        $log = Log::channel('tracking_collect');
        $gclidAlertLog = Log::channel('tracking_gclid_alert');

        // Validação estrutural do payload recebido pelo snippet.
        $data = $request->validate([
            'user_code'    => 'required|string|max:191',
            'campaign_code' => 'required|string|max:191',
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
        if (!$this->isTrackingTimestampValid((int) $data['auth_ts'])) {
            $log->warning('Tracking collect rejeitado: assinatura expirada.', [
                'auth_ts' => (int) $data['auth_ts'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'message' => 'Assinatura expirada.',
            ], 422);
        }

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
        $gclid = isset($data['gclid']) ? trim((string) $data['gclid']) : null;
        if ($gclid === '') {
            $gclid = null;
        } elseif (mb_strlen($gclid) > 150) {
            $gclidAlertLog->warning('GCLID acima do limite recebido no collect; valor truncado para persistencia.', [
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'received_length' => mb_strlen($gclid),
                'stored_length' => 150,
                'gclid_prefix' => mb_substr($gclid, 0, 24),
                'gclid_suffix' => mb_substr($gclid, -12),
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
        $deviceCategoryId = $this->resolveDeviceCategoryId('unknown');
        $browserId = $this->resolveBrowserId('unknown');
        $trafficSourceReason = mb_substr((string) ($classification['traffic_source_reason'] ?? ''), 0, 191);

        // Persistência final com payload já saneado.
        $pageview = Pageview::create([
            'user_id'      => $campaign->user_id,
            'campaign_id'   => $campaign->id,
            // Usa o código canônico da campanha encontrada no banco.
            'campaign_code' => $campaign->code,
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
            'timestamp_ms'  => $data['timestamp'] ?? null,
            'conversion'    => 0,
            'traffic_source_category_id' => $trafficSourceCategoryId,
            'traffic_source_reason' => $trafficSourceReason === '' ? null : $trafficSourceReason,
            'device_category_id' => $deviceCategoryId,
            'browser_id' => $browserId,
            'device_type' => null,
            'device_brand' => null,
            'device_model' => null,
            'os_name' => null,
            'os_version' => null,
            'browser_name' => null,
            'browser_version' => null,
            'screen_width' => $data['screen_width'] ?? null,
            'screen_height' => $data['screen_height'] ?? null,
            'viewport_width' => $data['viewport_width'] ?? null,
            'viewport_height' => $data['viewport_height'] ?? null,
            'device_pixel_ratio' => isset($data['device_pixel_ratio']) ? (float) $data['device_pixel_ratio'] : null,
            'platform' => $data['platform'] ?? null,
            'language' => $data['language'] ?? null,
        ]);

        $log->info('Tracking collect salvo com sucesso.', [
            'pageview_id' => $pageview->id,
            'campaign_id' => $campaign->id,
            'campaign_code' => $campaign->code,
            'request_origin' => $requestOrigin,
            'ip' => $request->ip(),
        ]);

        // Retorna hashid da pageview para compor subids/código composto de callback.
        $pageviewCode = app(HashidService::class)->encode((int) $pageview->id);

        return response()->json([
            'pageview_code' => $pageviewCode,
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

    protected function isTrackingTimestampValid(int $authTs): bool
    {
        $window = (int) config('app.tracking_signature_window_seconds', 300);
        return abs(time() - $authTs) <= $window;
    }

    protected function trackingNonceCacheKey(string $nonce): string
    {
        return 'tracking:nonce:' . $nonce;
    }
}
