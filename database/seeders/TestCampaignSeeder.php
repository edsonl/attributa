<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\AffiliatePlatform;
use App\Models\CampaignStatus;
use App\Models\ConversionGoal;
use App\Models\Timezone;
use Illuminate\Database\Seeder;

class TestCampaignSeeder extends Seeder
{
    public const TEST_EXTERNAL_CAMPAIGN_ID = 'EXT-TEST-001';

    public function run(): void
    {
        $channel = Channel::query()->firstOrCreate(
            ['slug' => 'google_ads'],
            [
                'name' => 'Google Ads',
                'active' => true,
            ]
        );

        $platform = AffiliatePlatform::query()
            ->where('slug', 'dr_cash')
            ->first() ?? AffiliatePlatform::query()->first();

        if (!$platform) {
            $this->command?->warn(
                'TestCampaignSeeder: plataforma de afiliado não encontrada. Rode AffiliatePlatformsSeeder primeiro.'
            );
            return;
        }

        $campaign = Campaign::query()->firstOrNew([
            'user_id' => 1,
            'external_campaign_id' => self::TEST_EXTERNAL_CAMPAIGN_ID,
        ]);
        $timezoneId = Timezone::query()
            ->where('identifier', 'America/Sao_Paulo')
            ->value('id');

        if (!$timezoneId) {
            $timezoneId = Timezone::query()->value('id');
        }

        if (!$timezoneId) {
            $this->command?->warn('TestCampaignSeeder: timezones nao encontradas. Rode TimezonesSeeder primeiro.');
            return;
        }

        $conversionGoal = ConversionGoal::query()->firstOrCreate(
            ['goal_code' => 'PX-TEST-001', 'user_id' => 1],
            ['user_id' => 1, 'timezone_id' => $timezoneId, 'active' => true]
        );

        if ((int) $conversionGoal->user_id !== 1) {
            $conversionGoal->user_id = 1;
        }

        if ((int) $conversionGoal->timezone_id !== (int) $timezoneId) {
            $conversionGoal->timezone_id = $timezoneId;
        }

        $conversionGoal->save();

        $activeStatusId = CampaignStatus::query()
            ->where('slug', 'active')
            ->value('id');

        if (!$activeStatusId) {
            $this->command?->warn('TestCampaignSeeder: status "active" não encontrado. Rode as migrations novamente.');
            return;
        }

        $campaign->user_id = 1;
        $campaign->name = 'Campanha Teste Pageviews';
        $campaign->product_url = 'http://attributa.site/produto-teste';
        $campaign->campaign_status_id = $activeStatusId;
        $campaign->conversion_goal_id = $conversionGoal->id;
        $campaign->channel_id = $channel->id;
        $campaign->affiliate_platform_id = $platform->id;
        $campaign->external_campaign_id = self::TEST_EXTERNAL_CAMPAIGN_ID;
        $campaign->commission_value = 1.00;
        $campaign->timezone = 'America/Sao_Paulo';

        $campaign->save();
    }
}
