<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\IpCategory;
use App\Models\Pageview;
use Illuminate\Database\Seeder;

class TestPageviewsSeeder extends Seeder
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
            $this->command?->warn('TestPageviewsSeeder: campanha de teste nao encontrada.');
            return;
        }

        $ipCategoryId = IpCategory::query()
            ->where('slug', 'real')
            ->value('id');

        // Recria um conjunto limpo e previsivel de 10 visitas para a campanha de teste.
        Pageview::query()
            ->where('campaign_id', $campaign->id)
            ->where('campaign_code', $campaign->code)
            ->delete();

        $locations = [
            ['US', 'United States', 'California', 'Mountain View', 37.4220, -122.0841, 'America/Los_Angeles'],
            ['IT', 'Italy', 'Lombardy', 'Milano', 45.4642, 9.1900, 'Europe/Rome'],
            ['BR', 'Brazil', 'Sao Paulo', 'Sao Paulo', -23.5505, -46.6333, 'America/Sao_Paulo'],
            ['AR', 'Argentina', 'Buenos Aires', 'Buenos Aires', -34.6037, -58.3816, 'America/Argentina/Buenos_Aires'],
            ['FR', 'France', 'Ile-de-France', 'Paris', 48.8566, 2.3522, 'Europe/Paris'],
        ];

        $nowMs = now()->valueOf();
        $rows = [];

        for ($i = 1; $i <= 10; $i++) {
            $loc = $locations[($i - 1) % count($locations)];
            $hasGclid = $i % 2 === 0;

            $rows[] = [
                'user_id' => 1,
                'campaign_id' => $campaign->id,
                'campaign_code' => $campaign->code,
                'url' => "https://teste.com/produto-{$i}?utm_source=seed&utm_medium=cpc",
                'referrer' => 'https://google.com/',
                'user_agent' => 'Mozilla/5.0 (Seeder Test)',
                'gclid' => $hasGclid ? "test-gclid-{$i}-abcdefghijklmnopqrstuvwxyz" : null,
                'gad_campaignid' => '1234567890',
                'ip' => "142.250.191.{$i}",
                'ip_category_id' => $ipCategoryId,
                'country_code' => $loc[0],
                'country_name' => $loc[1],
                'region_name' => $loc[2],
                'city' => $loc[3],
                'latitude' => $loc[4],
                'longitude' => $loc[5],
                'timezone' => $loc[6],
                'timestamp_ms' => $nowMs - ($i * 60000),
                'conversion' => $i % 3 === 0,
                'created_at' => now()->subMinutes($i),
                'updated_at' => now()->subMinutes($i),
            ];
        }

        Pageview::query()->insert($rows);
    }
}
