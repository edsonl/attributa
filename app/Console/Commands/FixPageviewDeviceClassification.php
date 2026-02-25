<?php

namespace App\Console\Commands;

use App\Models\Pageview;
use App\Services\DeviceClassificationService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

class FixPageviewDeviceClassification extends Command
{
    protected $signature = 'pageview:device-fix {--limit=1000 : Quantidade maxima de pageviews para processar} {--dry-run : Apenas mostra o que seria feito}';

    protected $description = 'Reprocessa classificacao de dispositivo/navegador para pageviews com campos pendentes';

    public function handle(): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $dryRun = (bool) $this->option('dry-run');

        $query = $this->buildPendingDeviceQuery();
        $totalPending = (clone $query)->count();

        if ($totalPending === 0) {
            $this->info('Nenhuma pageview com classificacao de dispositivo pendente.');

            return self::SUCCESS;
        }

        $rows = $query
            ->select(['id', 'user_agent'])
            ->orderBy('id')
            ->limit($limit)
            ->get();

        $this->info(sprintf('Pageviews pendentes encontradas: %d (processando ate %d)', $totalPending, $rows->count()));
        if ($dryRun) {
            $this->warn('Dry-run ativo: nenhuma escrita sera executada.');
        }

        $deviceClassifier = app(DeviceClassificationService::class);
        $processed = 0;
        $updated = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $processed++;

            try {
                $device = $deviceClassifier->classify($row->user_agent);
            } catch (\Throwable $e) {
                $failed++;
                $this->error(sprintf('Falha ao classificar pageview #%d: %s', (int) $row->id, $e->getMessage()));
                continue;
            }

            if ($dryRun) {
                continue;
            }

            $affected = Pageview::query()
                ->whereKey($row->id)
                ->update([
                    'device_category_id' => $device['device_category_id'] ?? null,
                    'browser_id' => $device['browser_id'] ?? null,
                    'device_type' => $device['device_type'] ?? null,
                    'device_brand' => $device['device_brand'] ?? null,
                    'device_model' => $device['device_model'] ?? null,
                    'os_name' => $device['os_name'] ?? null,
                    'os_version' => $device['os_version'] ?? null,
                    'browser_name' => $device['browser_name'] ?? null,
                    'browser_version' => $device['browser_version'] ?? null,
                ]);

            $updated += $affected;
        }

        $this->line('Resumo:');
        $this->line(sprintf('- Processadas: %d', $processed));
        $this->line(sprintf('- Atualizadas: %d', $dryRun ? 0 : $updated));
        $this->line(sprintf('- Falhas: %d', $failed));

        return self::SUCCESS;
    }

    protected function buildPendingDeviceQuery(): Builder
    {
        return Pageview::query()
            ->where(function (Builder $query) {
                $query->whereNull('device_category_id')
                    ->orWhereNull('browser_id')
                    ->orWhereNull('device_type')
                    ->orWhereNull('browser_name');
            });
    }
}

