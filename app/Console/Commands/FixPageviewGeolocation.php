<?php

namespace App\Console\Commands;

use App\Models\IpLookupCache;
use App\Models\Pageview;
use App\Services\IpClassifierService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FixPageviewGeolocation extends Command
{
    protected $signature = 'pageview:geolocation-fix {--limit-ips=500 : Quantidade maxima de IPs para processar} {--dry-run : Apenas mostra o que seria feito}';

    protected $description = 'Reprocessa geolocalizacao para pageviews com campos nulos e IPs sem enriquecimento';

    public function handle(): int
    {
        $limitIps = max((int) $this->option('limit-ips'), 1);
        $dryRun = (bool) $this->option('dry-run');

        $ipSamples = $this->collectCandidateIps($limitIps);
        if ($ipSamples === []) {
            $this->info('Nenhum IP pendente de geolocalizacao encontrado.');

            return self::SUCCESS;
        }

        $this->info(sprintf('IPs candidatos encontrados: %d', count($ipSamples)));
        if ($dryRun) {
            $this->warn('Dry-run ativo: nenhuma escrita sera executada.');
        }

        $classifier = app(IpClassifierService::class);

        $refreshedIps = 0;
        $skippedIps = 0;
        $failedIps = 0;
        $updatedPageviews = 0;

        foreach ($ipSamples as $ip => $userAgent) {
            $cache = IpLookupCache::query()->where('ip', $ip)->first();
            $cacheNeedsRefresh = $this->cacheNeedsGeoRefresh($cache);

            if ($cacheNeedsRefresh) {
                $refreshedIps++;
            } else {
                $skippedIps++;
            }

            if ($dryRun) {
                continue;
            }

            if ($cacheNeedsRefresh) {
                IpLookupCache::query()->where('ip', $ip)->delete();
            }

            try {
                $result = $classifier->classify($ip, $userAgent);
            } catch (\Throwable $e) {
                $failedIps++;
                $this->error(sprintf('Falha ao classificar IP %s: %s', $ip, $e->getMessage()));
                continue;
            }

            $payload = [
                'ip_category_id' => $result['ip_category_id'] ?? null,
                'country_code' => $result['geo']['country_code'] ?? null,
                'country_name' => $result['geo']['country_name'] ?? null,
                'region_name' => $result['geo']['region_name'] ?? null,
                'city' => $result['geo']['city'] ?? null,
                'latitude' => $result['geo']['latitude'] ?? null,
                'longitude' => $result['geo']['longitude'] ?? null,
                'timezone' => $result['geo']['timezone'] ?? null,
            ];

            $affected = $this->buildMissingGeoPageviewsQuery()
                ->where('ip', $ip)
                ->update($payload);

            $updatedPageviews += $affected;
        }

        $this->line('Resumo:');
        $this->line(sprintf('- IPs processados: %d', count($ipSamples)));
        $this->line(sprintf('- IPs com refresh de cache: %d', $refreshedIps));
        $this->line(sprintf('- IPs usando cache existente: %d', $skippedIps));
        $this->line(sprintf('- IPs com falha: %d', $failedIps));
        $this->line(sprintf('- Pageviews atualizadas: %d', $dryRun ? 0 : $updatedPageviews));

        return self::SUCCESS;
    }

    /**
     * @return array<string, ?string>
     */
    protected function collectCandidateIps(int $limitIps): array
    {
        $ips = [];

        $this->buildMissingGeoPageviewsQuery()
            ->select(['id', 'ip', 'user_agent'])
            ->whereNotNull('ip')
            ->orderBy('id')
            ->chunkById(500, function ($rows) use (&$ips, $limitIps) {
                foreach ($rows as $row) {
                    $ip = trim((string) $row->ip);
                    if ($ip === '' || isset($ips[$ip])) {
                        continue;
                    }

                    $ips[$ip] = $row->user_agent;

                    if (count($ips) >= $limitIps) {
                        return false;
                    }
                }

                return true;
            });

        return $ips;
    }

    protected function cacheNeedsGeoRefresh(?IpLookupCache $cache): bool
    {
        if (!$cache) {
            return true;
        }

        return $cache->country_code === null
            && $cache->country_name === null
            && $cache->latitude === null
            && $cache->longitude === null
            && $cache->timezone === null;
    }

    protected function buildMissingGeoPageviewsQuery(): Builder
    {
        return Pageview::query()
            ->where(function (Builder $query) {
                $query->whereNull('country_code')
                    ->orWhereNull('country_name')
                    ->orWhereNull('latitude')
                    ->orWhereNull('longitude')
                    ->orWhereNull('timezone');
            });
    }
}

