<?php

namespace App\Services;

use App\Models\IpLookupCache;
use App\Models\IpCategory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpClassifierService
{
    protected string $apiKey;
    protected string $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.ipqualityscore.key');
        $this->apiUrl = 'https://ipqualityscore.com/api/json/ip/';
    }

    /**
     * Classifica IP
     */
    public function classify(string $ip, ?string $userAgent = null): array
    {
        // 1️⃣ Verifica cache
        $cached = IpLookupCache::where('ip', $ip)->first();

        if ($cached) {
            return $this->formatCacheResult($cached);
        }

        // 2️⃣ Verifica Googlebot
        if ($this->isGooglebot($ip, $userAgent)) {
            return $this->storeGooglebot($ip);
        }

        // 3️⃣ Consulta API
        return $this->queryApiAndStore($ip);
    }

    /**
     * Consulta API externa
     */
    protected function queryApiAndStore(string $ip): array
    {
        try {

            $response = Http::timeout(10)
                ->when(app()->environment('local'), fn($http) => $http->withoutVerifying())
                ->get("{$this->apiUrl}{$this->apiKey}/{$ip}")
                ->json();

            if (!isset($response['success']) || $response['success'] !== true) {
                throw new \Exception('API returned unsuccessful response');
            }

            $categorySlug = $this->determineCategory($response);
            $category = IpCategory::where('slug', $categorySlug)->first();

            $cache = IpLookupCache::create([
                'ip' => $ip,
                'ip_category_id' => $category?->id,

                'is_proxy' => $response['proxy'] ?? false,
                'is_vpn' => $response['vpn'] ?? false,
                'is_tor' => $response['tor'] ?? false,
                'is_datacenter' => $response['hosting'] ?? false,
                'is_bot' => $response['bot_status'] ?? false,
                'fraud_score' => $response['fraud_score'] ?? null,

                'country_code' => $response['country_code'] ?? null,
                'country_name' => $response['country'] ?? null,
                'region_name' => $response['region'] ?? null,
                'city' => $response['city'] ?? null,
                'latitude' => $response['latitude'] ?? null,
                'longitude' => $response['longitude'] ?? null,
                'timezone' => $response['timezone'] ?? null,

                'isp' => $response['ISP'] ?? null,
                'organization' => $response['organization'] ?? null,

                'api_response' => $response,
                'last_checked_at' => now(),
            ]);

            return $this->formatCacheResult($cache);

        } catch (\Throwable $e) {

            Log::error('IP classification API error', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            $unknown = IpCategory::where('slug', 'unknown')->first();

            return [
                'ip_category_id' => $unknown?->id,
                'geo' => [],
            ];
        }
    }

    /**
     * Determina categoria com base na resposta da API
     */
    protected function determineCategory(array $response): string
    {
        if (($response['tor'] ?? false) === true) {
            return 'tor';
        }

        if (($response['vpn'] ?? false) === true) {
            return 'vpn';
        }

        if (($response['proxy'] ?? false) === true) {
            return 'proxy';
        }

        if (($response['hosting'] ?? false) === true) {
            return 'datacenter';
        }

        if (($response['bot_status'] ?? false) === true) {
            return 'bot';
        }

        if (($response['is_crawler'] ?? false) === true) {
            return 'bot';
        }

        return 'real';
    }

    /**
     * Detecta Googlebot via DNS reverso
     */
    protected function isGooglebot(string $ip, ?string $userAgent): bool
    {
        if (!$userAgent || stripos($userAgent, 'Googlebot') === false) {
            return false;
        }

        $host = gethostbyaddr($ip);

        if (!$host || !preg_match('/\.google(bot)?\.com$/', $host)) {
            return false;
        }

        $forward = gethostbyname($host);

        return $forward === $ip;
    }

    /**
     * Salva Googlebot no cache
     */
    protected function storeGooglebot(string $ip): array
    {
        $category = IpCategory::where('slug', 'googlebot')->first();

        $cache = IpLookupCache::create([
            'ip' => $ip,
            'ip_category_id' => $category?->id,
            'is_bot' => true,
            'last_checked_at' => now(),
        ]);

        return $this->formatCacheResult($cache);
    }

    /**
     * Formata retorno padrão
     */
    protected function formatCacheResult(IpLookupCache $cache): array
    {
        return [
            'ip_category_id' => $cache->ip_category_id,
            'geo' => [
                'country_code' => $cache->country_code,
                'country_name' => $cache->country_name,
                'region_name' => $cache->region_name,
                'city' => $cache->city,
                'latitude' => $cache->latitude,
                'longitude' => $cache->longitude,
                'timezone' => $cache->timezone,
            ],
        ];
    }
}
