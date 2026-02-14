<?php

namespace App\Console\Commands;

use App\Models\AdsConversion;
use App\Models\ConversionGoalLog;
use Illuminate\Console\Command;

class FinalizeConversionsAndPruneGoalLogs extends Command
{
    protected $signature = 'conversions:flush';

    protected $description = 'Finaliza conversões prossecing antigas e remove logs antigos de metas.';

    public function handle(): int
    {
        $processingHours = (int) config('app.conversions_processing_to_exported_hours', 1);
        $processingCutoff = now()->subHours($processingHours);

        $updated = AdsConversion::query()
            ->where('google_upload_status', 'prossecing')
            ->where('google_uploaded_at', '<=', $processingCutoff)
            ->update([
                'google_upload_status' => 'exported',
                'google_uploaded_at' => now(),
            ]);

        $retentionDays = (int) config('app.conversion_goal_logs_retention_days', 10);
        $cutoff = now()->subDays($retentionDays);

        $deletedLogs = ConversionGoalLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Conversões atualizadas para exported (mín. {$processingHours}h em prossecing): {$updated}");
        $this->info("Logs removidos (mais de {$retentionDays} dias): {$deletedLogs}");

        return self::SUCCESS;
    }
}
