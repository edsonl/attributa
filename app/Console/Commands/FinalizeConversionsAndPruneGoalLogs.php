<?php

namespace App\Console\Commands;

use App\Models\AdsConversion;
use App\Models\ConversionGoalLog;
use Illuminate\Console\Command;

class FinalizeConversionsAndPruneGoalLogs extends Command
{
    protected $signature = 'conversions:flush';

    protected $description = 'Finaliza conversões processing_export antigas e remove logs antigos de metas.';

    public function handle(): int
    {
        // Janela fixa de segurança antes de mover processing -> exported.
        $processingHours = 2;
        
        $processingCutoff = now()->subHours($processingHours);

        $updated = AdsConversion::query()
            ->whereIn('google_upload_status', [
                AdsConversion::STATUS_PROCESSING,
                AdsConversion::STATUS_PROCESSING_EXPORT,
            ])
            ->where('google_uploaded_at', '<=', $processingCutoff)
            ->update([
                'google_upload_status' => AdsConversion::STATUS_EXPORTED
            ]);
            //'google_uploaded_at' => now(),

        $retentionDays = (int) config('app.conversion_goal_logs_retention_days', 10);
        $cutoff = now()->subDays($retentionDays);

        $deletedLogs = ConversionGoalLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        $this->info("Conversões atualizadas para exported (mín. {$processingHours}h em processing/processing_export): {$updated}");
        $this->info("Logs removidos (mais de {$retentionDays} dias): {$deletedLogs}");

        return self::SUCCESS;
    }
}
