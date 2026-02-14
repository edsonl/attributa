<?php

namespace Database\Seeders;

use App\Models\Campaign;
use App\Models\Channel;
use App\Models\AffiliatePlatform;
use App\Models\ConversionGoal;
use App\Models\Timezone;
use App\Services\GenerateCampaignCode;
use Illuminate\Database\Seeder;

class TestCampaignSeeder extends Seeder
{
    public const TEST_EXTERNAL_CAMPAIGN_ID = 'EXT-TEST-001';

    public function run(): void
    {
        $channel = Channel::query()
            ->where('slug', 'google_ads')
            ->first() ?? Channel::query()->first();

        $platform = AffiliatePlatform::query()
            ->where('slug', 'dr_cash')
            ->first() ?? AffiliatePlatform::query()->first();

        if (!$channel || !$platform) {
            $this->command?->warn(
                'TestCampaignSeeder: canais/plataformas nao encontrados. Rode ChannelsSeeder e AffiliatePlatformsSeeder primeiro.'
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

        $campaign->user_id = 1;
        $campaign->name = 'Campanha Teste Pageviews';
        $campaign->status = 'active';
        $campaign->conversion_goal_id = $conversionGoal->id;
        $campaign->channel_id = $channel->id;
        $campaign->affiliate_platform_id = $platform->id;
        $campaign->external_campaign_id = self::TEST_EXTERNAL_CAMPAIGN_ID;
        $campaign->commission_value = 1.00;
        $campaign->timezone = 'America/Sao_Paulo';

        // Garante padrão CMP-XX-... mesmo quando o registro já existe de execuções antigas.
        if (!preg_match('/^CMP-[A-Z]{2}-[A-Z0-9]+$/', (string) $campaign->code)) {
            $normalized = trim((string) preg_replace('/[^A-Za-z]+/', ' ', (string) $channel->name));
            $channelCode = 'XX';
            if ($normalized !== '') {
                $words = preg_split('/\s+/', $normalized) ?: [];
                if (count($words) >= 2) {
                    $channelCode = strtoupper(substr((string) $words[0], 0, 1) . substr((string) $words[1], 0, 1));
                } else {
                    $channelCode = strtoupper(substr((string) ($words[0] ?? ''), 0, 2));
                }

                $channelCode = preg_replace('/[^A-Z]/', '', (string) $channelCode) ?? 'XX';
                if (strlen($channelCode) < 2) {
                    $channelCode = str_pad($channelCode, 2, 'X');
                }

                $channelCode = substr($channelCode, 0, 2);
            }

            $campaign->code = app(GenerateCampaignCode::class)->generate($channelCode);
        }

        $campaign->save();
    }
}
