<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\Pageview;
use App\Models\PageviewEvent;
use App\Models\User;
use App\Services\HashidService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TrackingControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_collect_rejects_when_campaign_context_is_invalid(): void
    {
        config([
            'tracking.logs.enabled' => false,
            'tracking.redis.prefix' => 'tracking',
        ]);
        $redis = $this->trackingRedisConnectionOrSkip();
        $this->clearTrackingRedisNamespace($redis);

        $user = User::factory()->create();
        $hashid = app(HashidService::class);
        $userCode = $hashid->encode((int) $user->id);
        $campaignCodeInvalido = $hashid->encode(999999);
        $response = $this->postJson(route('tracking.collect'), $this->buildCollectPayload(
            userCode: $userCode,
            campaignCode: $campaignCodeInvalido,
            nonce: 'nonceinvalidcampaign123456',
            authTs: time(),
            visitorCode: null
        ), [
            'Origin' => 'https://produto.example.com',
            'Referer' => 'https://produto.example.com/pagina',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Campanha inválida.');
    }

    public function test_event_registers_when_redis_context_exists(): void
    {
        config([
            'tracking.logs.enabled' => false,
            'tracking.redis.prefix' => 'tracking',
        ]);
        $redis = $this->trackingRedisConnectionOrSkip();
        $this->clearTrackingRedisNamespace($redis);

        $user = User::factory()->create();
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Campanha Event',
            'product_url' => 'https://produto.example.com/oferta',
            'channel_id' => 1,
            'affiliate_platform_id' => 1,
        ]);

        $hashid = app(HashidService::class);
        $userCode = $hashid->encode((int) $user->id);
        $campaignCode = (string) $campaign->code;

        $pageview = Pageview::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'url' => 'https://produto.example.com/pagina',
            'conversion' => false,
        ]);
        $pageviewCode = $hashid->encode((int) $pageview->id);

        $redis->setex(
            'tracking:campaign:' . $campaign->id,
            86400,
            (string) json_encode([
                'id' => (int) $campaign->id,
                'user_id' => (int) $user->id,
                'code' => $campaignCode,
                'name' => $campaign->name,
                'allowed_origin' => 'https://produto.example.com',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        $redis->setex(
            'tracking:pv:' . $userCode . ':' . $campaignCode . ':' . $pageviewCode,
            86400,
            (string) json_encode([
                'v' => 1,
                'campanha' => [
                    'id' => (int) $campaign->id,
                    'nome' => $campaign->name,
                    'user_id' => (int) $user->id,
                    'code' => $campaignCode,
                ],
                'pageview' => [
                    'id' => (int) $pageview->id,
                    'campaign_id' => (int) $campaign->id,
                    'user_id' => (int) $user->id,
                ],
                'timing' => [
                    'last_collect_at_ms' => now()->valueOf(),
                    'last_hit_at_ms' => now()->valueOf(),
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $eventSig = $this->buildEventSig($userCode, $campaignCode, $pageviewCode);

        $event = $this->postJson(route('tracking.event'), [
            'user_code' => $userCode,
            'campaign_code' => $campaignCode,
            'pageview_code' => $pageviewCode,
            'event_sig' => $eventSig,
            'event_type' => 'link_click',
            'target_url' => 'https://produto.example.com/checkout',
            'event_ts' => Carbon::now()->valueOf(),
        ], [
            'Origin' => 'https://produto.example.com',
            'Referer' => 'https://produto.example.com/pagina',
        ]);

        $event->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonStructure(['ok', 'event_id']);

        $this->assertDatabaseCount('pageview_events', 1);
    }

    public function test_event_is_ignored_when_redis_context_is_missing(): void
    {
        config([
            'tracking.logs.enabled' => false,
            'tracking.redis.prefix' => 'tracking',
        ]);
        $redis = $this->trackingRedisConnectionOrSkip();
        $this->clearTrackingRedisNamespace($redis);

        $user = User::factory()->create();
        $campaign = Campaign::create([
            'user_id' => $user->id,
            'name' => 'Campanha Missing Redis',
            'product_url' => 'https://produto.example.com/oferta',
            'channel_id' => 1,
            'affiliate_platform_id' => 1,
        ]);

        $pageview = Pageview::create([
            'user_id' => $user->id,
            'campaign_id' => $campaign->id,
            'url' => 'https://produto.example.com/pagina',
            'conversion' => false,
        ]);

        $hashid = app(HashidService::class);
        $userCode = $hashid->encode((int) $user->id);
        $campaignCode = (string) $campaign->code;
        $pageviewCode = $hashid->encode((int) $pageview->id);

        $eventSig = $this->buildEventSig($userCode, $campaignCode, $pageviewCode);
        $event = $this->postJson(route('tracking.event'), [
            'user_code' => $userCode,
            'campaign_code' => $campaignCode,
            'pageview_code' => $pageviewCode,
            'event_sig' => $eventSig,
            'event_type' => 'page_engaged',
            'target_url' => 'https://produto.example.com/pagina',
            'event_ts' => Carbon::now()->valueOf(),
        ], [
            'Origin' => 'https://produto.example.com',
            'Referer' => 'https://produto.example.com/pagina',
        ]);

        $event->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('ignored', true)
            ->assertJsonPath('reason', 'redis_context_missing');

        $this->assertDatabaseCount((new PageviewEvent())->getTable(), 0);
    }

    protected function trackingRedisConnectionOrSkip(): \Illuminate\Redis\Connections\Connection
    {
        try {
            $connection = Redis::connection((string) config('tracking.redis.connection', 'tracking'));
            $connection->command('ping');

            return $connection;
        } catch (\Throwable $e) {
            $this->markTestSkipped('Redis indisponivel para testes de tracking: ' . $e->getMessage());
        }
    }

    protected function clearTrackingRedisNamespace(\Illuminate\Redis\Connections\Connection $redis): void
    {
        $redis->command('flushdb');
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildCollectPayload(
        string $userCode,
        string $campaignCode,
        string $nonce,
        int $authTs,
        ?string $visitorCode
    ): array {
        return [
            'user_code' => $userCode,
            'campaign_code' => $campaignCode,
            'visitor_code' => $visitorCode,
            'auth_ts' => $authTs,
            'auth_nonce' => $nonce,
            'auth_sig' => $this->buildCollectSig($userCode, $campaignCode, $authTs, $nonce),
            'url' => 'https://produto.example.com/pagina',
            'landing_url' => 'https://produto.example.com/pagina',
            'referrer' => 'https://google.com',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
            'timestamp' => Carbon::now()->valueOf(),
            'utm_source' => 'google',
            'utm_medium' => 'cpc',
            'utm_campaign' => 'campanha-x',
        ];
    }

    protected function buildCollectSig(string $userCode, string $campaignCode, int $authTs, string $nonce): string
    {
        $payload = implode('|', [$userCode, $campaignCode, $authTs, $nonce]);

        return hash_hmac('sha256', $payload, (string) config('app.tracking_signature_secret', ''));
    }

    protected function buildEventSig(string $userCode, string $campaignCode, string $pageviewCode): string
    {
        $payload = implode('|', [$userCode, $campaignCode, $pageviewCode]);

        return hash_hmac('sha256', $payload, (string) config('app.tracking_signature_secret', ''));
    }
}
