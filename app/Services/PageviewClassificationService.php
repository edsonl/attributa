<?php

namespace App\Services;

use App\Models\IpLookupCache;

class PageviewClassificationService
{
    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function classify(array $payload, ?string $ip = null): array
    {
        $userAgent = strtolower((string) ($payload['user_agent'] ?? ''));
        $referrer = (string) ($payload['referrer'] ?? '');

        $traffic = $this->classifyTraffic($payload, $referrer, (string) ($payload['url'] ?? ''));
        $device = $this->classifyDevice($userAgent, $ip);

        return array_merge($traffic, $device);
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    protected function classifyTraffic(array $payload, string $referrer, string $url): array
    {
        $clickIds = [
            'gclid',
            'gad_campaignid',
            'fbclid',
            'ttclid',
            'msclkid',
            'wbraid',
            'gbraid',
        ];

        foreach ($clickIds as $key) {
            $value = trim((string) ($payload[$key] ?? ''));
            if ($value !== '') {
                return [
                    'traffic_source_slug' => 'paid',
                    'traffic_source_reason' => "click_id:$key",
                ];
            }
        }

        $utmMedium = strtolower(trim((string) ($payload['utm_medium'] ?? '')));
        if ($utmMedium !== '') {
            $paidMediums = [
                'cpc',
                'ppc',
                'paid',
                'paid_social',
                'display',
                'affiliate',
                'retargeting',
                'cpv',
                'cpm',
                'ads',
            ];

            if (in_array($utmMedium, $paidMediums, true)) {
                return [
                    'traffic_source_slug' => 'paid',
                    'traffic_source_reason' => "utm_medium:$utmMedium",
                ];
            }
        }

        $refHost = $this->extractHost($referrer);
        if ($refHost !== null) {
            $currentHost = $this->extractHost($url);
            if ($currentHost !== null && $currentHost === $refHost) {
                return [
                    'traffic_source_slug' => 'direct',
                    'traffic_source_reason' => 'internal_referrer',
                ];
            }

            if ($this->isSearchEngineHost($refHost)) {
                return [
                    'traffic_source_slug' => 'organic',
                    'traffic_source_reason' => "referrer_search:$refHost",
                ];
            }

            if ($this->isSocialHost($refHost)) {
                return [
                    'traffic_source_slug' => 'social',
                    'traffic_source_reason' => "referrer_social:$refHost",
                ];
            }

            return [
                'traffic_source_slug' => 'referral',
                'traffic_source_reason' => "referrer:$refHost",
            ];
        }

        $utmSource = strtolower(trim((string) ($payload['utm_source'] ?? '')));
        if ($utmSource !== '') {
            return [
                'traffic_source_slug' => 'unknown',
                'traffic_source_reason' => "utm_source_only:$utmSource",
            ];
        }

        return [
            'traffic_source_slug' => 'direct',
            'traffic_source_reason' => 'no_referrer_no_utm_no_click_id',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    protected function classifyDevice(string $userAgent, ?string $ip = null): array
    {
        if ($this->isBotByUserAgent($userAgent) || $this->isBotByIpCache($ip)) {
            return [
                'device_category_slug' => 'bot',
                'device_type' => 'bot',
                'device_brand' => null,
                'device_model' => null,
                'os_name' => null,
                'os_version' => null,
                'browser_name' => null,
                'browser_version' => null,
            ];
        }

        $deviceType = $this->detectDeviceType($userAgent);
        $brandModel = $this->detectBrandModel($userAgent);
        $os = $this->detectOs($userAgent);
        $browser = $this->detectBrowser($userAgent);

        return [
            'device_category_slug' => $deviceType,
            'device_type' => $deviceType,
            'device_brand' => $brandModel['brand'],
            'device_model' => $brandModel['model'],
            'os_name' => $os['name'],
            'os_version' => $os['version'],
            'browser_slug' => $browser['slug'],
            'browser_name' => $browser['name'],
            'browser_version' => $browser['version'],
        ];
    }

    protected function isBotByUserAgent(string $ua): bool
    {
        if ($ua === '') {
            return false;
        }

        $keywords = [
            'bot',
            'crawler',
            'spider',
            'headless',
            'slurp',
            'bingpreview',
            'facebookexternalhit',
            'python-requests',
            'curl/',
            'wget',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($ua, $keyword)) {
                return true;
            }
        }

        return false;
    }

    protected function isBotByIpCache(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        $cache = IpLookupCache::query()
            ->where('ip', $ip)
            ->first(['is_bot']);

        return (bool) ($cache?->is_bot ?? false);
    }

    protected function detectDeviceType(string $ua): string
    {
        if ($ua === '') {
            return 'unknown';
        }

        if (str_contains($ua, 'smart-tv') || str_contains($ua, 'smarttv') || str_contains($ua, 'hbbtv')) {
            return 'smart_tv';
        }

        if (str_contains($ua, 'ipad') || str_contains($ua, 'tablet')) {
            return 'tablet';
        }

        if (str_contains($ua, 'mobi') || str_contains($ua, 'iphone') || str_contains($ua, 'android')) {
            return 'mobile';
        }

        if (str_contains($ua, 'windows') || str_contains($ua, 'macintosh') || str_contains($ua, 'linux')) {
            return 'desktop';
        }

        return 'unknown';
    }

    /**
     * @return array{brand:?string,model:?string}
     */
    protected function detectBrandModel(string $ua): array
    {
        $brand = null;
        $model = null;

        if (str_contains($ua, 'iphone')) {
            $brand = 'Apple';
            $model = 'iPhone';
        } elseif (str_contains($ua, 'ipad')) {
            $brand = 'Apple';
            $model = 'iPad';
        } elseif (str_contains($ua, 'samsung')) {
            $brand = 'Samsung';
        } elseif (str_contains($ua, 'huawei')) {
            $brand = 'Huawei';
        } elseif (str_contains($ua, 'xiaomi') || str_contains($ua, 'redmi')) {
            $brand = 'Xiaomi';
        } elseif (str_contains($ua, 'motorola')) {
            $brand = 'Motorola';
        }

        return [
            'brand' => $brand,
            'model' => $model,
        ];
    }

    /**
     * @return array{name:?string,version:?string}
     */
    protected function detectOs(string $ua): array
    {
        if (preg_match('/windows nt ([0-9.]+)/', $ua, $m)) {
            return ['name' => 'Windows', 'version' => $m[1]];
        }

        if (preg_match('/android ([0-9.]+)/', $ua, $m)) {
            return ['name' => 'Android', 'version' => $m[1]];
        }

        if (preg_match('/iphone os ([0-9_]+)/', $ua, $m)) {
            return ['name' => 'iOS', 'version' => str_replace('_', '.', $m[1])];
        }

        if (preg_match('/cpu os ([0-9_]+)/', $ua, $m)) {
            return ['name' => 'iOS', 'version' => str_replace('_', '.', $m[1])];
        }

        if (preg_match('/mac os x ([0-9_]+)/', $ua, $m)) {
            return ['name' => 'macOS', 'version' => str_replace('_', '.', $m[1])];
        }

        if (str_contains($ua, 'linux')) {
            return ['name' => 'Linux', 'version' => null];
        }

        return ['name' => null, 'version' => null];
    }

    /**
     * @return array{slug:string,name:?string,version:?string}
     */
    protected function detectBrowser(string $ua): array
    {
        if (preg_match('/edg\/([0-9.]+)/', $ua, $m)) {
            return ['slug' => 'edge', 'name' => 'Edge', 'version' => $m[1]];
        }

        if (preg_match('/opr\/([0-9.]+)/', $ua, $m)) {
            return ['slug' => 'opera', 'name' => 'Opera', 'version' => $m[1]];
        }

        if (preg_match('/firefox\/([0-9.]+)/', $ua, $m)) {
            return ['slug' => 'firefox', 'name' => 'Firefox', 'version' => $m[1]];
        }

        if (preg_match('/samsungbrowser\/([0-9.]+)/', $ua, $m)) {
            return ['slug' => 'samsung_internet', 'name' => 'Samsung Internet', 'version' => $m[1]];
        }

        if (preg_match('/chrome\/([0-9.]+)/', $ua, $m)) {
            return ['slug' => 'chrome', 'name' => 'Chrome', 'version' => $m[1]];
        }

        if (preg_match('/version\/([0-9.]+).*safari/', $ua, $m)) {
            return ['slug' => 'safari', 'name' => 'Safari', 'version' => $m[1]];
        }

        return ['slug' => 'unknown', 'name' => null, 'version' => null];
    }

    protected function extractHost(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '') {
            return null;
        }

        return preg_replace('/^www\./', '', $host) ?: $host;
    }

    protected function isSearchEngineHost(string $host): bool
    {
        $list = [
            'google.',
            'bing.com',
            'yahoo.',
            'duckduckgo.com',
            'yandex.',
            'baidu.com',
            'ecosia.org',
        ];

        foreach ($list as $item) {
            if (str_contains($host, $item)) {
                return true;
            }
        }

        return false;
    }

    protected function isSocialHost(string $host): bool
    {
        $list = [
            'facebook.com',
            'instagram.com',
            't.co',
            'twitter.com',
            'x.com',
            'linkedin.com',
            'youtube.com',
            'tiktok.com',
            'pinterest.',
        ];

        foreach ($list as $item) {
            if (str_contains($host, $item)) {
                return true;
            }
        }

        return false;
    }
}
