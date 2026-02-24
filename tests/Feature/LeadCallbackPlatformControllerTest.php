<?php

namespace Tests\Feature;

use App\Models\AdsConversion;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Notification;
use App\Models\Pageview;
use App\Models\User;
use App\Services\HashidService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestCampaignSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeadCallbackPlatformControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_lead_callback_creates_lead_and_conversion_when_status_is_approved(): void
    {
        User::factory()->create([
            'id' => 1,
            'email_verified_at' => now(),
        ]);

        $this->seed(DatabaseSeeder::class);

        $user = User::query()->findOrFail(1);
        $campaign = Campaign::query()
            ->where('user_id', $user->id)
            ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
            ->firstOrFail();

        $seedLeadWithoutConversion = Lead::query()
            ->where('campaign_id', $campaign->id)
            ->whereIn('lead_status', [Lead::STATUS_PROCESSING, Lead::STATUS_REJECTED, Lead::STATUS_TRASH])
            ->orderBy('id')
            ->firstOrFail();

        $pageview = Pageview::query()->findOrFail((int) $seedLeadWithoutConversion->pageview_id);

        $hashid = app(HashidService::class);
        $userCode = $hashid->encode($user->id);
        $pageviewCode = $hashid->encode($pageview->id);
        $composedCode = $userCode . '-' . $campaign->code . '-' . $pageviewCode;

        $externalUuid = 'it-callback-uuid-001';
        $response = $this->get(route('api.get.platform-lead.handle', [
            'platformSlug' => 'dr_cash',
            'userCode' => $userCode,
        ]) . '?' . http_build_query([
            'subid1' => $composedCode,
            'payment' => '9.50',
            'currency' => 'USD',
            'status' => 'approved',
            'uuid' => $externalUuid,
            'date' => (string) now()->timestamp,
            'offer' => '99999',
            'stream' => 'test_stream',
        ]));

        $response->assertOk();
        $response->assertJsonPath('ok', true);
        $response->assertJsonPath('resolved.status_mapped', 'approved');

        $lead = Lead::query()
            ->where('campaign_id', $campaign->id)
            ->where('platform_lead_id', $externalUuid)
            ->first();

        $this->assertNotNull($lead);
        $this->assertSame('approved', $lead->lead_status);
        $this->assertSame('approved', $lead->status_raw);
        $this->assertSame('9.50', number_format((float) $lead->payout_amount, 2, '.', ''));
        $this->assertSame('USD', $lead->currency_code);
        $this->assertSame(99999, (int) $lead->offer_id);

        $conversion = AdsConversion::query()
            ->where('lead_id', $lead->id)
            ->first();

        $this->assertNotNull($conversion);
        $this->assertSame((int) $campaign->id, (int) $conversion->campaign_id);
        $this->assertSame((int) $lead->pageview_id, (int) $conversion->pageview_id);

        $notification = Notification::query()
            ->where('user_id', (int) $user->id)
            ->where('source_type', 'lead')
            ->where('source_id', (int) $lead->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($notification);
        $this->assertSame(Notification::STATUS_UNREAD, $notification->status);
        $this->assertNotEmpty($notification->title);
        $this->assertNotEmpty($notification->message);
    }
}
