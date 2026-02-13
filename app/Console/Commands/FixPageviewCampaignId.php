<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Pageview;
use App\Models\Campaign;
use Illuminate\Support\Facades\DB;

class FixPageviewCampaignId extends Command
{
    protected $signature = 'pageviews:fix-campaign-id {--dry-run}';
    protected $description = 'Atualiza campaign_id nas pageviews antigas baseado no campaign_code';

    public function handle()
    {
        $this->info('Iniciando correção de campaign_id...');

        $dryRun = $this->option('dry-run');

        $updated = 0;

        Pageview::whereNull('campaign_id')
            ->whereNotNull('campaign_code')
            ->chunkById(500, function ($pageviews) use (&$updated, $dryRun) {

                foreach ($pageviews as $pageview) {

                    $campaign = Campaign::where('code', $pageview->campaign_code)->first();

                    if ($campaign) {

                        if (!$dryRun) {
                            $pageview->update([
                                'campaign_id' => $campaign->id
                            ]);
                        }

                        $updated++;

                        $this->line("✔ Pageview {$pageview->id} atualizada");
                    }
                }

            });

        if ($dryRun) {
            $this->warn("Dry run finalizado. {$updated} registros seriam atualizados.");
        } else {
            $this->info("Processo finalizado. {$updated} registros atualizados.");
        }

        return Command::SUCCESS;
    }
}
