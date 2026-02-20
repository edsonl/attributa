<?php

namespace Tests\Feature;

use App\Models\AdsConversion;
use App\Models\AffiliatePlatform;
use App\Models\Campaign;
use App\Models\Pageview;
use App\Models\User;
use App\Services\HashidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConversionCallbackPlatformControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_ignores_callback_when_pageview_is_already_converted(): void
    {
        $user = User::factory()->create();

        $platform = AffiliatePlatform::create([
            'name' => 'DrCash',
            'slug' => 'dr_cash',
            'active' => true,
            'tracking_param_mapping' => [
                'sub1' => 'sub1',
                'sub2' => 'sub2',
            ],
            'conversion_param_mapping' => [
                'conversion_value' => 'payment',
                'currency_code' => 'currency',
            ],
        ]);

        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Campanha teste',
            'channel_id' => 1,
            'affiliate_platform_id' => $platform->id,
        ]);

        $pageview = Pageview::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'campaign_code' => $campaign->code,
            'url' => 'https://example.com',
            'conversion' => true,
        ]);

        $hashid = app(HashidService::class);
        $userCode = $hashid->encode($user->id);
        $pageviewCode = $hashid->encode($pageview->id);
        $composedCode = "{$userCode}-{$campaign->code}-{$pageviewCode}";

        $response = $this->get(route('api.callback-platform.handle', [
            'platformSlug' => $platform->slug,
            'userCode' => $userCode,
        ]) . '?status=approved&subid1=' . $composedCode);

        $response->assertOk();
        $response->assertSeeText('ignored');

        $this->assertDatabaseCount((new AdsConversion())->getTable(), 0);
    }
}

