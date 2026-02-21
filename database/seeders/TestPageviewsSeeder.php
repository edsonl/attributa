<?php

namespace Database\Seeders;

use App\Models\Browser;
use App\Models\Campaign;
use App\Models\DeviceCategory;
use App\Models\IpCategory;
use App\Models\Pageview;
use App\Models\PageviewEvent;
use App\Models\TrafficSourceCategory;
use Illuminate\Database\Seeder;

class TestPageviewsSeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::query()
            ->where('user_id', 1)
            ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
            ->first();

        if (!$campaign) {
            $this->call(TestCampaignSeeder::class);
            $campaign = Campaign::query()
                ->where('user_id', 1)
                ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
                ->first();
        }

        if (!$campaign) {
            $this->command?->warn('TestPageviewsSeeder: campanha de teste nao encontrada.');
            return;
        }

        $ipCategoryId = (int) IpCategory::query()
            ->where('slug', 'real')
            ->value('id');

        $trafficSourceIdBySlug = TrafficSourceCategory::query()
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $deviceCategoryIdBySlug = DeviceCategory::query()
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        $browserIdBySlug = Browser::query()
            ->pluck('id', 'slug')
            ->map(fn ($id) => (int) $id)
            ->toArray();

        // Recria um conjunto limpo e previsível de visitas da campanha de teste.
        Pageview::query()
            ->where('campaign_id', $campaign->id)
            ->delete();

        $locations = [
            ['US', 'United States', 'California', 'Mountain View', 37.4220, -122.0841, 'America/Los_Angeles'],
            ['IT', 'Italy', 'Lombardy', 'Milano', 45.4642, 9.1900, 'Europe/Rome'],
            ['BR', 'Brazil', 'Sao Paulo', 'Sao Paulo', -23.5505, -46.6333, 'America/Sao_Paulo'],
            ['AR', 'Argentina', 'Buenos Aires', 'Buenos Aires', -34.6037, -58.3816, 'America/Argentina/Buenos_Aires'],
            ['FR', 'France', 'Ile-de-France', 'Paris', 48.8566, 2.3522, 'Europe/Paris'],
        ];

        $scenarios = [
            [
                'traffic_slug' => 'paid',
                'traffic_reason' => 'click_id:gclid',
                'query' => 'utm_source=google&utm_medium=cpc&utm_campaign=search_brand&gclid=seed-gclid-001',
                'referrer' => 'https://www.google.com/',
                'gclid' => 'seed-gclid-001',
                'device_slug' => 'desktop',
                'device_type' => 'desktop',
                'browser_slug' => 'chrome',
                'browser_name' => 'Chrome',
                'browser_version' => '121.0',
                'os_name' => 'Windows',
                'os_version' => '10.0',
                'platform' => 'Win32',
                'screen' => [1920, 1080],
                'viewport' => [1536, 730],
                'dpr' => 1.25,
                'language' => 'en-US',
            ],
            [
                'traffic_slug' => 'paid',
                'traffic_reason' => 'utm_medium:cpc',
                'query' => 'utm_source=bing&utm_medium=cpc&utm_campaign=search_non_brand&msclkid=seed-msclkid-002',
                'referrer' => 'https://www.bing.com/',
                'msclkid' => 'seed-msclkid-002',
                'device_slug' => 'desktop',
                'device_type' => 'desktop',
                'browser_slug' => 'edge',
                'browser_name' => 'Edge',
                'browser_version' => '121.0',
                'os_name' => 'Windows',
                'os_version' => '11.0',
                'platform' => 'Win32',
                'screen' => [1920, 1080],
                'viewport' => [1502, 720],
                'dpr' => 1.25,
                'language' => 'en-US',
            ],
            [
                'traffic_slug' => 'organic',
                'traffic_reason' => 'referrer_search:google.com',
                'query' => 'utm_source=google&utm_medium=organic',
                'referrer' => 'https://www.google.com/search?q=produto+teste',
                'device_slug' => 'mobile',
                'device_type' => 'mobile',
                'browser_slug' => 'chrome',
                'browser_name' => 'Chrome',
                'browser_version' => '120.0',
                'os_name' => 'Android',
                'os_version' => '14',
                'device_brand' => 'Samsung',
                'device_model' => 'Galaxy S23',
                'platform' => 'Linux armv8l',
                'screen' => [1080, 2340],
                'viewport' => [393, 851],
                'dpr' => 2.75,
                'language' => 'pt-BR',
            ],
            [
                'traffic_slug' => 'social',
                'traffic_reason' => 'referrer_social:instagram.com',
                'query' => 'utm_source=instagram&utm_medium=social&utm_campaign=influencer_a',
                'referrer' => 'https://www.instagram.com/',
                'device_slug' => 'mobile',
                'device_type' => 'mobile',
                'browser_slug' => 'safari',
                'browser_name' => 'Safari',
                'browser_version' => '17.3',
                'os_name' => 'iOS',
                'os_version' => '17.3',
                'device_brand' => 'Apple',
                'device_model' => 'iPhone',
                'platform' => 'iPhone',
                'screen' => [1179, 2556],
                'viewport' => [393, 748],
                'dpr' => 3.0,
                'language' => 'en-US',
            ],
            [
                'traffic_slug' => 'social',
                'traffic_reason' => 'click_id:fbclid',
                'query' => 'utm_source=facebook&utm_medium=paid_social&utm_campaign=remarketing&fbclid=seed-fbclid-005',
                'referrer' => 'https://www.facebook.com/',
                'fbclid' => 'seed-fbclid-005',
                'device_slug' => 'mobile',
                'device_type' => 'mobile',
                'browser_slug' => 'samsung_internet',
                'browser_name' => 'Samsung Internet',
                'browser_version' => '24.0',
                'os_name' => 'Android',
                'os_version' => '13',
                'device_brand' => 'Samsung',
                'device_model' => 'Galaxy S22',
                'platform' => 'Linux armv8l',
                'screen' => [1080, 2340],
                'viewport' => [360, 780],
                'dpr' => 3.0,
                'language' => 'pt-BR',
            ],
            [
                'traffic_slug' => 'referral',
                'traffic_reason' => 'referrer:partner-blog.com',
                'query' => 'utm_source=partner_blog&utm_medium=referral&utm_campaign=review_post',
                'referrer' => 'https://partner-blog.com/post/produto-teste',
                'device_slug' => 'desktop',
                'device_type' => 'desktop',
                'browser_slug' => 'firefox',
                'browser_name' => 'Firefox',
                'browser_version' => '122.0',
                'os_name' => 'Linux',
                'device_brand' => null,
                'device_model' => null,
                'platform' => 'Linux x86_64',
                'screen' => [1366, 768],
                'viewport' => [1280, 640],
                'dpr' => 1.0,
                'language' => 'pt-BR',
            ],
            [
                'traffic_slug' => 'direct',
                'traffic_reason' => 'no_referrer_no_utm_no_click_id',
                'query' => '',
                'referrer' => null,
                'device_slug' => 'desktop',
                'device_type' => 'desktop',
                'browser_slug' => 'chrome',
                'browser_name' => 'Chrome',
                'browser_version' => '121.0',
                'os_name' => 'macOS',
                'os_version' => '14.2',
                'device_brand' => 'Apple',
                'device_model' => 'Mac',
                'platform' => 'MacIntel',
                'screen' => [2560, 1600],
                'viewport' => [1512, 860],
                'dpr' => 2.0,
                'language' => 'en-US',
            ],
            [
                'traffic_slug' => 'direct',
                'traffic_reason' => 'internal_referrer',
                'query' => 'utm_source=newsletter&utm_medium=email&utm_campaign=retencao_fev',
                'referrer' => 'https://teste.com/blog/artigo-1',
                'device_slug' => 'tablet',
                'device_type' => 'tablet',
                'browser_slug' => 'safari',
                'browser_name' => 'Safari',
                'browser_version' => '17.2',
                'os_name' => 'iOS',
                'os_version' => '17.2',
                'device_brand' => 'Apple',
                'device_model' => 'iPad',
                'platform' => 'iPad',
                'screen' => [2048, 2732],
                'viewport' => [1024, 1366],
                'dpr' => 2.0,
                'language' => 'en-US',
            ],
            [
                'traffic_slug' => 'unknown',
                'traffic_reason' => 'utm_source_only:seed',
                'query' => 'utm_source=seed',
                'referrer' => null,
                'device_slug' => 'desktop',
                'device_type' => 'desktop',
                'browser_slug' => 'unknown',
                'browser_name' => null,
                'browser_version' => null,
                'os_name' => 'Windows',
                'os_version' => '10.0',
                'platform' => 'Win32',
                'screen' => [1440, 900],
                'viewport' => [1280, 720],
                'dpr' => 1.0,
                'language' => 'pt-BR',
            ],
            [
                'traffic_slug' => 'paid',
                'traffic_reason' => 'click_id:ttclid',
                'query' => 'utm_source=tiktok&utm_medium=paid_social&utm_campaign=video_test&ttclid=seed-ttclid-010',
                'referrer' => 'https://www.tiktok.com/',
                'ttclid' => 'seed-ttclid-010',
                'device_slug' => 'mobile',
                'device_type' => 'mobile',
                'browser_slug' => 'chrome',
                'browser_name' => 'Chrome',
                'browser_version' => '120.0',
                'os_name' => 'Android',
                'os_version' => '13',
                'device_brand' => 'Xiaomi',
                'device_model' => 'Redmi Note',
                'platform' => 'Linux armv8l',
                'screen' => [1080, 2400],
                'viewport' => [393, 780],
                'dpr' => 2.75,
                'language' => 'pt-BR',
            ],
        ];

        $now = now();
        $nowMs = $now->valueOf();
        $rows = [];

        foreach ($scenarios as $index => $scenario) {
            $i = $index + 1;
            $loc = $locations[$index % count($locations)];
            $query = trim((string) ($scenario['query'] ?? ''));
            $urlBase = "https://teste.com/produto-{$i}";

            $rows[] = [
                'user_id' => 1,
                'campaign_id' => $campaign->id,
                'url' => $query !== '' ? "{$urlBase}?{$query}" : $urlBase,
                'landing_url' => $urlBase,
                'referrer' => $scenario['referrer'] ?? null,
                'user_agent' => 'Mozilla/5.0 (Seeder Test)',
                'utm_source' => $this->extractQueryValue($query, 'utm_source'),
                'utm_medium' => $this->extractQueryValue($query, 'utm_medium'),
                'utm_campaign' => $this->extractQueryValue($query, 'utm_campaign'),
                'utm_term' => $this->extractQueryValue($query, 'utm_term'),
                'utm_content' => $this->extractQueryValue($query, 'utm_content'),
                'gclid' => $scenario['gclid'] ?? $this->extractQueryValue($query, 'gclid'),
                'gad_campaignid' => $scenario['gad_campaignid'] ?? null,
                'fbclid' => $scenario['fbclid'] ?? $this->extractQueryValue($query, 'fbclid'),
                'ttclid' => $scenario['ttclid'] ?? $this->extractQueryValue($query, 'ttclid'),
                'msclkid' => $scenario['msclkid'] ?? $this->extractQueryValue($query, 'msclkid'),
                'wbraid' => $scenario['wbraid'] ?? $this->extractQueryValue($query, 'wbraid'),
                'gbraid' => $scenario['gbraid'] ?? $this->extractQueryValue($query, 'gbraid'),
                'ip' => "142.250.191.{$i}",
                'ip_category_id' => $ipCategoryId,
                'traffic_source_category_id' => $trafficSourceIdBySlug[$scenario['traffic_slug']] ?? null,
                'traffic_source_reason' => $scenario['traffic_reason'] ?? null,
                'device_category_id' => $deviceCategoryIdBySlug[$scenario['device_slug']] ?? null,
                'browser_id' => $browserIdBySlug[$scenario['browser_slug']] ?? null,
                'device_type' => $scenario['device_type'] ?? null,
                'device_brand' => $scenario['device_brand'] ?? null,
                'device_model' => $scenario['device_model'] ?? null,
                'os_name' => $scenario['os_name'] ?? null,
                'os_version' => $scenario['os_version'] ?? null,
                'browser_name' => $scenario['browser_name'] ?? null,
                'browser_version' => $scenario['browser_version'] ?? null,
                'screen_width' => $scenario['screen'][0] ?? null,
                'screen_height' => $scenario['screen'][1] ?? null,
                'viewport_width' => $scenario['viewport'][0] ?? null,
                'viewport_height' => $scenario['viewport'][1] ?? null,
                'device_pixel_ratio' => $scenario['dpr'] ?? null,
                'platform' => $scenario['platform'] ?? null,
                'language' => $scenario['language'] ?? null,
                'country_code' => $loc[0],
                'country_name' => $loc[1],
                'region_name' => $loc[2],
                'city' => $loc[3],
                'latitude' => $loc[4],
                'longitude' => $loc[5],
                'timezone' => $loc[6],
                'timestamp_ms' => $nowMs - ($i * 60000),
                'conversion' => $i % 3 === 0,
                'created_at' => $now->copy()->subMinutes($i),
                'updated_at' => $now->copy()->subMinutes($i),
            ];
        }

        Pageview::query()->insert($rows);

        $seededPageviews = Pageview::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->get(['id', 'user_id', 'campaign_id', 'conversion', 'created_at']);

        $eventRows = [];
        foreach ($seededPageviews as $index => $pageview) {
            $step = $index + 1;
            $baseTime = $pageview->created_at ?? now();

            // Parte das visitas recebe engajamento para simular comportamento real.
            if ($step % 4 !== 0) {
                $eventRows[] = [
                    'user_id' => $pageview->user_id,
                    'campaign_id' => $pageview->campaign_id,
                    'pageview_id' => $pageview->id,
                    'event_type' => 'page_engaged',
                    'target_url' => "https://teste.com/produto-{$step}",
                    'element_id' => null,
                    'element_name' => 'Page engaged (time_10s)',
                    'element_classes' => null,
                    'form_fields_checked' => null,
                    'form_fields_filled' => null,
                    'form_has_user_data' => null,
                    'event_ts_ms' => optional($baseTime)->copy()->addSeconds(10)?->valueOf(),
                    'created_at' => optional($baseTime)->copy()->addSeconds(10) ?? now(),
                    'updated_at' => optional($baseTime)->copy()->addSeconds(10) ?? now(),
                ];
            }

            // Simula interação principal: alterna entre clique e formulário,
            // garantindo exemplos claros dos dois tipos no conjunto de teste.
            if ($step % 2 === 0) {
                $eventRows[] = [
                    'user_id' => $pageview->user_id,
                    'campaign_id' => $pageview->campaign_id,
                    'pageview_id' => $pageview->id,
                    'event_type' => 'link_click',
                    'target_url' => "https://teste.com/produto-{$step}/checkout",
                    'element_id' => null,
                    'element_name' => "Link {$step} - Comprar agora",
                    'element_classes' => 'btn btn-primary cta-checkout',
                    'form_fields_checked' => null,
                    'form_fields_filled' => null,
                    'form_has_user_data' => null,
                    'event_ts_ms' => optional($baseTime)->copy()->addSeconds(16)?->valueOf(),
                    'created_at' => optional($baseTime)->copy()->addSeconds(16) ?? now(),
                    'updated_at' => optional($baseTime)->copy()->addSeconds(16) ?? now(),
                ];
            } else {
                $eventRows[] = [
                    'user_id' => $pageview->user_id,
                    'campaign_id' => $pageview->campaign_id,
                    'pageview_id' => $pageview->id,
                    'event_type' => 'form_submit',
                    'target_url' => "https://teste.com/produto-{$step}/lead",
                    'element_id' => null,
                    'element_name' => "Formulário {$step}",
                    'element_classes' => 'lead-form checkout-step',
                    'form_fields_checked' => 2,
                    'form_fields_filled' => 2,
                    'form_has_user_data' => true,
                    'event_ts_ms' => optional($baseTime)->copy()->addSeconds(18)?->valueOf(),
                    'created_at' => optional($baseTime)->copy()->addSeconds(18) ?? now(),
                    'updated_at' => optional($baseTime)->copy()->addSeconds(18) ?? now(),
                ];
            }

            // Reforço determinístico para teste manual rápido:
            // primeira visita sempre tem clique;
            // segunda visita sempre tem submit de formulário.
            if ($step === 1) {
                $eventRows[] = [
                    'user_id' => $pageview->user_id,
                    'campaign_id' => $pageview->campaign_id,
                    'pageview_id' => $pageview->id,
                    'event_type' => 'link_click',
                    'target_url' => "https://teste.com/produto-{$step}/detalhes",
                    'element_id' => null,
                    'element_name' => "Link {$step} - Ver detalhes",
                    'element_classes' => 'btn btn-secondary cta-details',
                    'form_fields_checked' => null,
                    'form_fields_filled' => null,
                    'form_has_user_data' => null,
                    'event_ts_ms' => optional($baseTime)->copy()->addSeconds(14)?->valueOf(),
                    'created_at' => optional($baseTime)->copy()->addSeconds(14) ?? now(),
                    'updated_at' => optional($baseTime)->copy()->addSeconds(14) ?? now(),
                ];
            }

            if ($step === 2) {
                $eventRows[] = [
                    'user_id' => $pageview->user_id,
                    'campaign_id' => $pageview->campaign_id,
                    'pageview_id' => $pageview->id,
                    'event_type' => 'form_submit',
                    'target_url' => "https://teste.com/produto-{$step}/cadastro",
                    'element_id' => null,
                    'element_name' => "Formulário {$step} - Cadastro",
                    'element_classes' => 'lead-form profile-step',
                    'form_fields_checked' => 2,
                    'form_fields_filled' => 2,
                    'form_has_user_data' => true,
                    'event_ts_ms' => optional($baseTime)->copy()->addSeconds(20)?->valueOf(),
                    'created_at' => optional($baseTime)->copy()->addSeconds(20) ?? now(),
                    'updated_at' => optional($baseTime)->copy()->addSeconds(20) ?? now(),
                ];
            }

            // Para visitas convertidas, garante um fluxo mais "completo".
            if ((bool) $pageview->conversion) {
                $eventRows[] = [
                    'user_id' => $pageview->user_id,
                    'campaign_id' => $pageview->campaign_id,
                    'pageview_id' => $pageview->id,
                    'event_type' => 'form_submit',
                    'target_url' => "https://teste.com/produto-{$step}/checkout/submit",
                    'element_id' => null,
                    'element_name' => "Formulário {$step} - Checkout",
                    'element_classes' => 'checkout-form final-step',
                    'form_fields_checked' => 3,
                    'form_fields_filled' => 3,
                    'form_has_user_data' => true,
                    'event_ts_ms' => optional($baseTime)->copy()->addSeconds(24)?->valueOf(),
                    'created_at' => optional($baseTime)->copy()->addSeconds(24) ?? now(),
                    'updated_at' => optional($baseTime)->copy()->addSeconds(24) ?? now(),
                ];
            }
        }

        if (!empty($eventRows)) {
            PageviewEvent::query()->insert($eventRows);
        }
    }

    protected function extractQueryValue(string $query, string $key): ?string
    {
        if ($query === '') {
            return null;
        }

        parse_str($query, $params);
        $value = $params[$key] ?? null;

        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);
        return $text === '' ? null : $text;
    }
}
