<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Pageview;
use Illuminate\Database\Seeder;

class TestLeadsSeeder extends Seeder
{
    public function run(): void
    {
        $campaign = Campaign::query()
            ->where('user_id', 1)
            ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
            ->first();

        if (!$campaign) {
            $this->call(TestCampaignSeeder::class);
            $campaign = Campaign::query()
                ->where('user_id', 1)
                ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
                ->first();
        }

        if (!$campaign) {
            $this->command?->warn('TestLeadsSeeder: campanha de teste nao encontrada.');
            return;
        }

        $pageviews = Pageview::query()
            ->where('campaign_id', $campaign->id)
            ->orderBy('id')
            ->get(['id', 'user_id', 'campaign_id', 'created_at']);

        if ($pageviews->isEmpty()) {
            $this->call(TestPageviewsSeeder::class);
            $pageviews = Pageview::query()
                ->where('campaign_id', $campaign->id)
                ->orderBy('id')
                ->get(['id', 'user_id', 'campaign_id', 'created_at']);
        }

        if ($pageviews->isEmpty()) {
            $this->command?->warn('TestLeadsSeeder: nenhuma pageview encontrada para campanha de teste.');
            return;
        }

        Lead::query()
            ->where('campaign_id', $campaign->id)
            ->delete();

        $rows = [];
        foreach ($pageviews as $index => $pageview) {
            $step = $index + 1;
            $status = match ($step % 5) {
                0 => Lead::STATUS_TRASH,
                1 => Lead::STATUS_APPROVED,
                2 => Lead::STATUS_PROCESSING,
                3 => Lead::STATUS_REJECTED,
                default => Lead::STATUS_APPROVED,
            };

            $rows[] = [
                'user_id' => $campaign->user_id,
                'campaign_id' => $campaign->id,
                'pageview_id' => $pageview->id,
                'affiliate_platform_id' => $campaign->affiliate_platform_id,
                'platform_lead_id' => 'seed-uuid-' . str_pad((string) $step, 4, '0', STR_PAD_LEFT),
                'lead_status' => $status,
                'status_raw' => $status,
                'payout_amount' => number_format(0.80 + ($step * 0.15), 2, '.', ''),
                'currency_code' => 'USD',
                'offer_id' => 28000 + $step,
                'occurred_at' => optional($pageview->created_at)->copy()->addSeconds(20) ?? now(),
                'payload_json' => [
                    'source' => 'test_seed',
                    'status' => $status,
                    'uuid' => 'seed-uuid-' . str_pad((string) $step, 4, '0', STR_PAD_LEFT),
                    'offer' => 28000 + $step,
                ],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach ($rows as &$row) {
            $row['payload_json'] = json_encode($row['payload_json'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        unset($row);

        Lead::query()->insert($rows);
    }
}
