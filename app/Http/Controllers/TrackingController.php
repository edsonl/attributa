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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class TrackingController extends Controller
{
    protected array $trafficSourceIdBySlug = [];
    protected array $deviceCategoryIdBySlug = [];
    protected array $browserIdBySlug = [];

    public function collect(Request $request)
    {
        $log = Log::channel('tracking_collect');

        // validação mínima (não bloqueante demais)
        $data = $request->validate([
            'campaign_code' => 'required|string|exists:campaigns,code',
            'url'           => 'required|string|max:500',
            'landing_url'   => 'nullable|string|max:500',
            'referrer'      => 'nullable|string',
            'user_agent'    => 'nullable|string',
            'timestamp'     => 'nullable|integer',
            'gclid'         => 'nullable|string|max:255',
            'gad_campaignid'=> 'nullable|string',
            'utm_source'    => 'nullable|string|max:255',
            'utm_medium'    => 'nullable|string|max:255',
            'utm_campaign'  => 'nullable|string|max:255',
            'utm_term'      => 'nullable|string|max:255',
            'utm_content'   => 'nullable|string|max:255',
            'fbclid'        => 'nullable|string|max:255',
            'ttclid'        => 'nullable|string|max:255',
            'msclkid'       => 'nullable|string|max:255',
            'wbraid'        => 'nullable|string|max:255',
            'gbraid'        => 'nullable|string|max:255',
            'screen_width'  => 'nullable|integer|min:1|max:20000',
            'screen_height' => 'nullable|integer|min:1|max:20000',
            'viewport_width' => 'nullable|integer|min:1|max:20000',
            'viewport_height' => 'nullable|integer|min:1|max:20000',
            'device_pixel_ratio' => 'nullable|numeric|min:0|max:20',
            'platform'      => 'nullable|string|max:255',
            'language'      => 'nullable|string|max:20',
        ]);
        
        //Obter campanha
        $campaign = Campaign::where('code', $data['campaign_code'])->first();
        if (!$campaign) {
            $log->warning('Tracking collect rejeitado: campanha inválida.', [
                'campaign_code' => (string) ($data['campaign_code'] ?? ''),
                'ip' => $request->ip(),
                'origin' => $request->headers->get('Origin'),
                'referer' => $request->headers->get('Referer'),
            ]);
            return response()->json([
                'message' => 'Campanha inválida.',
            ], 422);
        }

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

        $gclid = isset($data['gclid']) ? trim((string) $data['gclid']) : null;
        if ($gclid === '') {
            $gclid = null;
        } elseif (mb_strlen($gclid) > 255) {
            $gclid = mb_substr($gclid, 0, 255);
        }

        $url = mb_substr(trim((string) $data['url']), 0, 500);
        $landingUrl = isset($data['landing_url']) ? mb_substr(trim((string) $data['landing_url']), 0, 500) : null;
        if ($landingUrl === '') {
            $landingUrl = null;
        }

        $classification = app(PageviewClassificationService::class)->classify($data, $request->ip());
        $trafficSourceCategoryId = $this->resolveTrafficSourceCategoryId((string) ($classification['traffic_source_slug'] ?? 'unknown'));
        $deviceCategoryId = $this->resolveDeviceCategoryId('unknown');
        $browserId = $this->resolveBrowserId('unknown');

        $pageview = Pageview::create([
            'user_id'      => $campaign->user_id,
            'campaign_id'   => $campaign->id,
            'campaign_code' => $data['campaign_code'],
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
            'user_agent'    => $data['user_agent'] ?? $request->userAgent(),
            'ip'            => $request->ip(),
            'timestamp_ms'  => $data['timestamp'] ?? null,
            'conversion'    => 0,
            'traffic_source_category_id' => $trafficSourceCategoryId,
            'traffic_source_reason' => mb_substr((string) ($classification['traffic_source_reason'] ?? ''), 0, 255),
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

       // 🔹 Retorna o código hash da visita (pageview)
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
            // 🔹 Código da campanha vindo da URL (?c=...)
            $code = $request->query('c');

            // Código inválido
            if ($code && !preg_match('/^CMP-[A-Z]{2}-[A-Z0-9]+$/', $code)) {
                return response()->make(
                    'console.error("[Attributa] Código de campanha inválido");',
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
            $replacements = [
                "'{ENDPOINT}'"       => json_encode($endpoint),
                "'{CAMPAIGN_CODE}'"  => json_encode($code),
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
}
