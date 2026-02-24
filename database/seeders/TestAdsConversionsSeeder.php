<?php

namespace Database\Seeders;

use App\Models\AdsConversion;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Pageview;
use Illuminate\Database\Seeder;

class TestAdsConversionsSeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::query()
            ->with('conversionGoal:id,goal_code')
            ->where('user_id', 1)
            ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
            ->first();

        if (!$campaign) {
            $this->call(TestCampaignSeeder::class);
            $campaign = Campaign::query()
                ->with('conversionGoal:id,goal_code')
                ->where('user_id', 1)
                ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
                ->first();
        }

        if (!$campaign) {
            $this->command?->warn('TestAdsConversionsSeeder: campanha de teste nao encontrada.');
            return;
        }

        $approvedLeads = Lead::query()
            ->where('campaign_id', $campaign->id)
            ->where('lead_status', Lead::STATUS_APPROVED)
            ->orderBy('id')
            ->get();

        if ($approvedLeads->isEmpty()) {
            $this->call(TestLeadsSeeder::class);
            $approvedLeads = Lead::query()
                ->where('campaign_id', $campaign->id)
                ->where('lead_status', Lead::STATUS_APPROVED)
                ->orderBy('id')
                ->get();
        }

        if ($approvedLeads->isEmpty()) {
            $this->command?->warn('TestAdsConversionsSeeder: nenhum lead aprovado encontrado.');
            return;
        }

        // Recria um conjunto limpo e previsível de conversões da campanha de teste.
        AdsConversion::query()
            ->where('campaign_id', $campaign->id)
            ->delete();

        $rows = [];
        foreach ($approvedLeads as $index => $lead) {
            $pageview = null;
            if (!empty($lead->pageview_id)) {
                $pageview = Pageview::query()->find($lead->pageview_id);
            }

            $rows[] = [
                'user_id' => $campaign->user_id,
                'campaign_id' => $campaign->id,
                'lead_id' => $lead->id,
                'pageview_id' => $lead->pageview_id,
                'gclid' => $pageview?->gclid,
                'gbraid' => $pageview?->gbraid,
                'wbraid' => $pageview?->wbraid,
                'user_agent' => $pageview?->user_agent,
                'ip_address' => $pageview?->ip,
                'conversion_name' => $campaign->conversionGoal?->goal_code ?? 'PX-TEST-001',
                'conversion_value' => (float) ($lead->payout_amount ?? (1.00 + ($index * 0.25))),
                'currency_code' => $lead->currency_code ?: 'USD',
                'conversion_event_time' => $lead->occurred_at ?? now(),
                'google_upload_status' => AdsConversion::STATUS_PENDING,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AdsConversion::query()->insert($rows);
    }
}
