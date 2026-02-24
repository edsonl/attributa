<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\TestCampaignSeeder;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleCsvIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_google_csv_export_flow_is_consistent(): void
    {
        User::factory()->create([
            'id' => 1,
            'email_verified_at' => now(),
        ]);

        $this->seed(DatabaseSeeder::class);
        $this->withoutMiddleware(EnsureEmailIsVerified::class);

        $user = User::query()->findOrFail(1);
        $campaign = Campaign::query()
            ->where('user_id', $user->id)
            ->where('external_campaign_id', TestCampaignSeeder::TEST_EXTERNAL_CAMPAIGN_ID)
            ->firstOrFail();

        $rangeResponse = $this->actingAs($user)->get(route('panel.conversoes.export-range', [
            'campaign_id' => $campaign->id,
        ]));

        $rangeResponse->assertOk();
        $rangeResponse->assertJson([
            'campaign_id' => $campaign->id,
            'has_rows' => true,
        ]);

        $range = $rangeResponse->json();
        $this->assertNotEmpty($range['min_datetime_local'] ?? null);
        $this->assertNotEmpty($range['max_datetime_local'] ?? null);

        $csvResponse = $this->actingAs($user)->get(route('panel.conversoes.export-csv', [
            'campaign_id' => $campaign->id,
            'date_from' => $range['min_datetime_local'],
            'date_to' => $range['max_datetime_local'],
            'include_manual' => true,
            'include_automatic' => true,
        ]));

        $csvResponse->assertOk();
        $csvResponse->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $body = $csvResponse->getContent();
        $this->assertIsString($body);
        $this->assertStringContainsString('Google Click ID', $body);
        $this->assertStringContainsString('Conversion Name', $body);
        $this->assertStringContainsString('Currency Code', $body);
    }
}
