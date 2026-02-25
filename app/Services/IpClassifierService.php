<?php

namespace App\Services;

use App\Models\IpLookupCache;
use App\Models\IpCategory;
use GeoIp2\Database\Reader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class IpClassifierService
{
    protected const IPGEOLOCATION_API_URL = 'https://api.ipgeolocation.io/v3/ipgeo';

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

        // 2.1️⃣ Bot genérico por user-agent (não Googlebot validado)
        if ($this->isGenericBotUserAgent($userAgent)) {
            return $this->storeGenericBot($ip);
        }

        // 3️⃣ Geolocalização via driver configurado
        return $this->queryGeolocationAndStore($ip);
    }

    /**
     * Geolocalização via driver configurado
     */
    protected function queryGeolocationAndStore(string $ip): array
    {
        $driver = strtolower(trim((string) config('pageview.geolocation.driver', 'api')));
        $fallback = strtolower(trim((string) config('pageview.geolocation.fallback', 'api')));

        if ($driver === 'maxmind') {
            $maxmindResult = $this->queryMaxMindAndStore($ip);
            if ($maxmindResult !== null) {
                return $maxmindResult;
            }

            if ($fallback === 'api') {
                return $this->queryApiAndStore($ip);
            }

            return $this->unknownResult();
        }

        return $this->queryApiAndStore($ip);
    }

    /**
     * Consulta MaxMind (GeoLite2)
     */
    protected function queryMaxMindAndStore(string $ip): ?array
    {
        $cityDbPath = (string) config('pageview.geolocation.maxmind.city_db_path', '');
        if ($cityDbPath === '' || !is_file($cityDbPath)) {
            Log::warning('MaxMind city database not found', [
                'ip' => $ip,
                'path' => $cityDbPath,
            ]);

            return null;
        }

        try {
            $cityReader = new Reader($cityDbPath);
            $cityRecord = $cityReader->city($ip);

            $asnPayload = [];
            $organization = null;
            $isp = null;
            $asnDbPath = (string) config('pageview.geolocation.maxmind.asn_db_path', '');

            if ($asnDbPath !== '' && is_file($asnDbPath)) {
                try {
                    $asnReader = new Reader($asnDbPath);
                    $asnRecord = $asnReader->asn($ip);
                    $organization = $asnRecord->autonomousSystemOrganization ?? null;
                    $isp = $organization;
                    $asnPayload = [
                        'organization' => $organization,
                        'asn' => $asnRecord->autonomousSystemNumber ?? null,
                    ];
                } catch (\Throwable $asnException) {
                    Log::warning('MaxMind ASN lookup failed', [
                        'ip' => $ip,
                        'error' => $asnException->getMessage(),
                    ]);
                }
            }

            $regionName = $cityRecord->mostSpecificSubdivision->name ?? null;
            if ($regionName === '' || $regionName === null) {
                $regionName = $cityRecord->leastSpecificSubdivision->name ?? null;
            }

            $categorySlug = $this->determineCategory($asnPayload);
            $category = IpCategory::where('slug', $categorySlug)->first();

            $cache = IpLookupCache::updateOrCreate([
                'ip' => $ip,
            ], [
                'ip_category_id' => $category?->id,
                'is_proxy' => false,
                'is_vpn' => false,
                'is_tor' => false,
                'is_bot' => false,
                'is_datacenter' => $this->isDatacenter($asnPayload),
                'fraud_score' => null,
                'country_code' => $cityRecord->country->isoCode ?? null,
                'country_name' => $cityRecord->country->name ?? null,
                'region_name' => $regionName,
                'city' => $cityRecord->city->name ?? null,
                'latitude' => $cityRecord->location->latitude ?? null,
                'longitude' => $cityRecord->location->longitude ?? null,
                'timezone' => $cityRecord->location->timeZone ?? null,
                'isp' => $isp,
                'organization' => $organization,
                'api_response' => [
                    'provider' => 'maxmind',
                    'city_db_path' => $cityDbPath,
                    'asn_db_path' => $asnDbPath,
                ],
                'last_checked_at' => now(),
            ]);

            return $this->formatCacheResult($cache);
        } catch (\Throwable $e) {
            Log::error('MaxMind lookup error', [
                'ip' => $ip,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Consulta ipgeolocation
     */
    protected function queryApiAndStore(string $ip): array
    {
        try {
            $apiKey = (string) config('services.ipgeolocation.key');

            $response = Http::timeout(10)
                ->withoutVerifying()
                ->get(self::IPGEOLOCATION_API_URL, [
                    'apiKey' => $apiKey,
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

            $cache = IpLookupCache::updateOrCreate([
                'ip' => $ip,
            ], [
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

            return $this->unknownResult();
        }
    }

    protected function unknownResult(): array
    {
        $unknown = IpCategory::where('slug', 'unknown')->first();

        return [
            'ip_category_id' => $unknown?->id,
            'geo' => [],
        ];
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
        $ua = strtolower(trim((string) $userAgent));

        $host = strtolower((string) gethostbyaddr($ip));
        if ($host === '' || $host === $ip) {
            return false;
        }

        $isAllowedGoogleHost = preg_match('/\.(googlebot\.com|google\.com|googleusercontent\.com)$/', $host) === 1;
        if ($isAllowedGoogleHost) {
            // Se o domínio reverso é Google, considera Googlebot/proxy do Google.
            return true;
        }

        if ($ua === '') {
            return false;
        }

        $isGoogleCrawlerUa = str_contains($ua, 'googlebot')
            || str_contains($ua, 'google-inspectiontool')
            || str_contains($ua, 'adsbot-google')
            || str_contains($ua, 'proxy');

        if (!$isGoogleCrawlerUa) {
            return false;
        }

        return false;
    }

    protected function isGenericBotUserAgent(?string $userAgent): bool
    {
        $ua = strtolower(trim((string) $userAgent));
        if ($ua === '') {
            return false;
        }

        $keywords = [
            'bot',
            'crawler',
            'spider',
            'slurp',
            'bingpreview',
            'facebookexternalhit',
            'python-requests',
            'curl/',
            'wget',
            'headless',
        ];

        foreach ($keywords as $keyword) {
            if (str_contains($ua, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Salva Googlebot
     */
    protected function storeGooglebot(string $ip): array
    {
        $category = IpCategory::where('slug', 'googlebot')->first();

        $cache = IpLookupCache::updateOrCreate([
            'ip' => $ip,
        ], [
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

    protected function storeGenericBot(string $ip): array
    {
        $category = IpCategory::where('slug', 'bot')->first();

        $cache = IpLookupCache::updateOrCreate([
            'ip' => $ip,
        ], [
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
