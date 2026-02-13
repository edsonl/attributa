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
        $this->apiKey = config('services.ipgeolocation.key');
        $this->apiUrl = 'https://api.ipgeolocation.io/v3/ipgeo';
    }

    /**
     * Classifica IP
     */
    public function classify(string $ip, ?string $userAgent = null): array
    {
        // 1️⃣ Cache
        $cached = IpLookupCache::where('ip', $ip)->first();
        if ($cached) {
            return $this->formatCacheResult($cached);
        }

        // 2️⃣ Googlebot
        if ($this->isGooglebot($ip, $userAgent)) {
            return $this->storeGooglebot($ip);
        }

        // 3️⃣ Consulta API
        return $this->queryApiAndStore($ip);
    }

    /**
     * Consulta ipgeolocation
     */
    protected function queryApiAndStore(string $ip): array
    {
        try {

            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get($this->apiUrl, [
                    'apiKey' => $this->apiKey,
                    'ip'     => $ip,
                ])
                ->json();

            if (!isset($response['ip'])) {
                throw new \Exception('Invalid API response');
            }

            $location = $response['location'] ?? [];
            $asn      = $response['asn'] ?? [];
            $tz       = $response['time_zone'] ?? [];

            $categorySlug = $this->determineCategory($asn);
            $category = IpCategory::where('slug', $categorySlug)->first();

            $cache = IpLookupCache::create([
                'ip' => $ip,
                'ip_category_id' => $category?->id,

                // ⚠️ API não fornece esses dados → boolean como false
                'is_proxy'      => false,
                'is_vpn'        => false,
                'is_tor'        => false,
                'is_bot'        => false,

                // Heurística simples para datacenter
                'is_datacenter' => $this->isDatacenter($asn),

                // Não existe fraud_score → numérico null
                'fraud_score'   => null,

                // Geo
                'country_code'  => $location['country_code2'] ?? null,
                'country_name'  => $location['country_name'] ?? null,
                'region_name'   => $location['state_prov'] ?? null,
                'city'          => $location['city'] ?? null,
                'latitude'      => isset($location['latitude'])
                    ? (float) $location['latitude']
                    : null,
                'longitude'     => isset($location['longitude'])
                    ? (float) $location['longitude']
                    : null,
                'timezone'      => $tz['name'] ?? null,

                // ASN / ISP
                'isp'           => $asn['organization'] ?? null,
                'organization'  => $asn['organization'] ?? null,

                'api_response'  => $response,
                'last_checked_at' => now(),
            ]);

            return $this->formatCacheResult($cache);

        } catch (\Throwable $e) {

            Log::error('IPGeolocation API error', [
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
     * Determina categoria
     */
    protected function determineCategory(array $asn): string
    {
        if ($this->isDatacenter($asn)) {
            return 'datacenter';
        }

        return 'real';
    }

    /**
     * Heurística para detectar datacenter
     */
    protected function isDatacenter(array $asn): bool
    {
        $org = strtolower($asn['organization'] ?? '');

        $keywords = [
            'google cloud',
            'amazon',
            'aws',
            'digitalocean',
            'ovh',
            'microsoft',
            'azure',
            'vultr',
            'linode',
            'cloudflare',
            'oracle'
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($org, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detecta Googlebot real via DNS reverso
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
     * Salva Googlebot
     */
    protected function storeGooglebot(string $ip): array
    {
        $category = IpCategory::where('slug', 'googlebot')->first();

        $cache = IpLookupCache::create([
            'ip' => $ip,
            'ip_category_id' => $category?->id,
            'is_bot' => true,
            'is_proxy' => false,
            'is_vpn' => false,
            'is_tor' => false,
            'is_datacenter' => false,
            'fraud_score' => null,
            'last_checked_at' => now(),
        ]);

        return $this->formatCacheResult($cache);
    }

    /**
     * Retorno padrão
     */
    protected function formatCacheResult(IpLookupCache $cache): array
    {
        return [
            'ip_category_id' => $cache->ip_category_id,
            'geo' => [
                'country_code' => $cache->country_code,
                'country_name' => $cache->country_name,
                'region_name'  => $cache->region_name,
                'city'         => $cache->city,
                'latitude'     => $cache->latitude,
                'longitude'    => $cache->longitude,
                'timezone'     => $cache->timezone,
            ],
        ];
    }
}
