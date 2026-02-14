<?php

namespace Database\Seeders;

use App\Models\AdsConversion;
use App\Models\Campaign;
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

        $pageviews = Pageview::query()
            ->where('campaign_id', $campaign->id)
            ->where('conversion', true)
            ->orderBy('id')
            ->get();

        if ($pageviews->isEmpty()) {
            $this->command?->warn('TestAdsConversionsSeeder: nenhuma pageview convertida encontrada.');
            return;
        }

        // Recria um conjunto limpo e previsível de conversões da campanha de teste.
        AdsConversion::query()
            ->where('campaign_id', $campaign->id)
            ->delete();

        $rows = [];
        foreach ($pageviews as $index => $pageview) {
            $rows[] = [
                'user_id' => $campaign->user_id,
                'campaign_id' => $campaign->id,
                'pageview_id' => $pageview->id,
                'gclid' => $pageview->gclid,
                'conversion_name' => $campaign->conversionGoal?->goal_code ?? 'PX-TEST-001',
                'conversion_value' => 1.00 + ($index * 0.25),
                'currency_code' => 'USD',
                'conversion_event_time' => optional($pageview->created_at)->addSeconds(30) ?? now(),
                'google_upload_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        AdsConversion::query()->insert($rows);
    }
}
