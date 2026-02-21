<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class FixPageviewCampaignId extends Command
{
    protected $signature = 'pageviews:fix-campaign-id {--dry-run}';
    protected $description = 'Comando legado: manutenção de campaign_id em pageviews';

    public function handle()
    {
        if (!Schema::hasColumn('pageviews', 'campaign_code')) {
            $this->info('Nenhuma ação: coluna pageviews.campaign_code não existe mais.');
            return Command::SUCCESS;
        }

        $this->warn('Comando legado: remova campaign_code antes de executar qualquer rotina de backfill.');
        return Command::SUCCESS;
    }
}
