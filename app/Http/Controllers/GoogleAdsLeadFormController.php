<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Pageview;
use App\Services\HashidService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GoogleAdsLeadFormController extends Controller
{
    /**
     * Endpoint público para webhooks de formulário de lead do Google Ads.
     *
     * O processamento de payload será implementado na próxima etapa.
     */
    public function handle(Request $request, string $userHash, string $campaignHash): JsonResponse
    {
        $payload = $this->resolvePayload($request);

        $hashidService = app(HashidService::class);
        $userId = $hashidService->decode($userHash);
        $campaignId = $hashidService->decode($campaignHash);

        $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
            'stage' => 'received',
        ]);

        if (!$userId || !$campaignId) {
            $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
                'stage' => 'rejected',
                'reason' => 'invalid_identifiers',
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Identificadores inválidos.',
            ], 422);
        }

        $campaign = Campaign::query()
            ->where('id', (int) $campaignId)
            ->where('user_id', (int) $userId)
            ->first();

        if (!$campaign) {
            $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
                'stage' => 'rejected',
                'reason' => 'campaign_not_found',
                'resolved_user_id' => (int) $userId,
                'resolved_campaign_id' => (int) $campaignId,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Campanha não encontrada para os identificadores informados.',
            ], 404);
        }

        if (!(bool) $campaign->form_lead_active) {
            $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
                'stage' => 'rejected',
                'reason' => 'lead_form_disabled',
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $campaign->user_id,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Integração de formulário de lead desativada para esta campanha.',
            ], 422);
        }

        $incomingKey = $this->extractGoogleKey($payload);
        $expectedKey = (string) ($campaign->google_ads_form_key ?? '');
        $validKey = $incomingKey !== '' && $expectedKey !== '' && hash_equals($expectedKey, $incomingKey);

        if (!$validKey) {
            $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
                'stage' => 'rejected',
                'reason' => 'invalid_google_key',
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $campaign->user_id,
                'incoming_google_key' => $incomingKey,
                'expected_google_key_masked' => $this->maskSecret($expectedKey),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Autenticação inválida (google_key).',
            ], 401);
        }

        $pageview = $this->storeAsPageview($request, $campaign, $payload);
        $this->dispatchLeadToAffiliatePlatform($request, $campaign, $pageview, $payload);

        $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
            'stage' => 'accepted',
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $campaign->user_id,
            'pageview_id' => (int) $pageview->id,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Webhook autenticado e salvo em pageviews.',
            'pageview_id' => (int) $pageview->id,
        ], 202);
    }

    protected function resolvePayload(Request $request): array
    {
        $payload = $request->all();
        if (!empty($payload)) {
            return is_array($payload) ? $payload : [];
        }

        $raw = trim((string) $request->getContent());
        if ($raw === '' || !str_starts_with($raw, '{')) {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    protected function extractGoogleKey(array $payload): string
    {

        foreach (['google_key', 'Google_key', 'googleKey', 'key'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $value = trim((string) $payload[$field]);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    protected function storeAsPageview(Request $request, Campaign $campaign, array $payload): Pageview
    {
        $columns = $this->extractUserColumnData($payload);
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];

        $leadId = trim((string) ($payload['lead_id'] ?? ''));
        $gclid = trim((string) ($payload['gcl_id'] ?? $payload['gclid'] ?? ''));
        if ($gclid === '') {
            $gclid = $leadId;
        }
        $gclid = $gclid === '' ? null : mb_substr($gclid, 0, 150);

        $googleCampaignId = trim((string) ($payload['campaign_id'] ?? ''));
        $googleAdGroupId = trim((string) ($payload['adgroup_id'] ?? ''));
        $googleCreativeId = trim((string) ($payload['creative_id'] ?? ''));
        $formId = trim((string) ($payload['form_id'] ?? ''));
        $apiVersion = trim((string) ($payload['api_version'] ?? ''));
        $isTest = filter_var($payload['is_test'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $trafficReason = 'google_ads_lead_form_webhook';
        if ($isTest) {
            $trafficReason .= '_test';
        }

        return Pageview::query()->create([
            'user_id' => (int) $campaign->user_id,
            'campaign_id' => (int) $campaign->id,
            'url' => mb_substr((string) $request->fullUrl(), 0, 500),
            'landing_url' => null,
            'referrer' => mb_substr((string) ($request->headers->get('referer') ?? 'google_ads_lead_form'), 0, 65535),
            'user_agent' => mb_substr((string) ($request->userAgent() ?? ''), 0, 500),
            'utm_source' => 'google_ads',
            'utm_medium' => 'lead_form',
            'utm_campaign' => $formId !== '' ? mb_substr('form_' . $formId, 0, 191) : null,
            'utm_term' => $googleAdGroupId !== '' ? mb_substr($googleAdGroupId, 0, 191) : null,
            'utm_content' => $googleCreativeId !== '' ? mb_substr($googleCreativeId, 0, 191) : null,
            'gclid' => $gclid,
            'gad_campaignid' => $googleCampaignId !== '' ? mb_substr($googleCampaignId, 0, 191) : null,
            'ip' => mb_substr((string) (($client['ip'] ?? null) ?: $request->ip() ?: ''), 0, 45),
            'country_name' => $this->nullableTrim(($columns['country'] ?? null) ?: ($client['country'] ?? null), 191),
            'city' => $this->nullableTrim(($columns['city'] ?? null) ?: ($client['city'] ?? null), 191),
            'occurred_at' => now('UTC'),
            'traffic_source_reason' => mb_substr($trafficReason, 0, 191),
            'platform' => $this->nullableTrim($apiVersion, 191),
            'language' => $this->nullableTrim($columns['language'] ?? null, 20),
        ]);
    }

    protected function dispatchLeadToAffiliatePlatform(
        Request $request,
        Campaign $campaign,
        Pageview $pageview,
        array $payload
    ): void {
        $platform = $campaign->affiliatePlatform()->first();
        if (!$platform) {
            $this->logOutgoingDispatch('skipped', $campaign, $pageview, [
                'reason' => 'affiliate_platform_not_found',
            ]);
            return;
        }

        $slug = trim((string) $platform->slug);
        if ($slug !== 'dr_cash') {
            $this->logOutgoingDispatch('skipped', $campaign, $pageview, [
                'reason' => 'platform_handler_not_implemented',
                'platform_slug' => $slug,
            ]);
            return;
        }

        $postUrl = trim((string) ($platform->postback_url ?? ''));
        $apiKey = trim((string) ($platform->api_post_key ?? ''));
        $streamCode = trim((string) ($campaign->stream_code ?? ''));

        if ($postUrl === '' || $apiKey === '' || $streamCode === '') {
            $this->logOutgoingDispatch('skipped', $campaign, $pageview, [
                'reason' => 'missing_post_dispatch_config',
                'platform_slug' => $slug,
                'has_postback_url' => $postUrl !== '',
                'has_api_post_key' => $apiKey !== '',
                'has_stream_code' => $streamCode !== '',
            ]);
            return;
        }

        $columns = $this->extractUserColumnData($payload);
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $composedSub1 = $this->buildComposedSub1($campaign, $pageview);

        $body = [
            'stream_code' => $streamCode,
            'client' => [
                'phone' => $this->firstNonEmpty([
                    $columns['phone'] ?? null,
                    $client['phone'] ?? null,
                ]),
                'name' => $this->firstNonEmpty([
                    $columns['full_name'] ?? null,
                    trim(($columns['first_name'] ?? '') . ' ' . ($columns['last_name'] ?? '')),
                    $client['name'] ?? null,
                ]),
                'surname' => $this->firstNonEmpty([$columns['last_name'] ?? null, $client['surname'] ?? null]),
                'email' => $this->firstNonEmpty([$columns['email'] ?? null, $columns['work_email'] ?? null, $client['email'] ?? null]),
                'address' => $this->firstNonEmpty([$client['address'] ?? null]),
                'ip' => $this->firstNonEmpty([$client['ip'] ?? null, $request->ip()]),
                'country' => $this->firstNonEmpty([$columns['country'] ?? null, $client['country'] ?? null]),
                'city' => $this->firstNonEmpty([$columns['city'] ?? null, $client['city'] ?? null]),
                'postcode' => $this->firstNonEmpty([$columns['postal_code'] ?? null, $client['postcode'] ?? null]),
            ],
            'sub1' => $composedSub1,
            'sub2' => null,
            'sub3' => null,
            'sub4' => null,
            'sub5' => null,
        ];

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($postUrl, $body);

            $this->logOutgoingDispatch('completed', $campaign, $pageview, [
                'platform_slug' => $slug,
                'request_url' => $postUrl,
                'request_body' => $body,
                'response_status' => $response->status(),
                'response_body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            $this->logOutgoingDispatch('failed', $campaign, $pageview, [
                'platform_slug' => $slug,
                'request_url' => $postUrl,
                'request_body' => $body,
                'error' => $e->getMessage(),
            ]);
        }
    }

    protected function buildComposedSub1(Campaign $campaign, Pageview $pageview): string
    {
        $hashidService = app(HashidService::class);
        $userHash = $hashidService->encode((int) $campaign->user_id);
        $campaignHash = $hashidService->encode((int) $campaign->id);
        $pageviewHash = $hashidService->encode((int) $pageview->id);

        return $userHash . '-' . $campaignHash . '-' . $pageviewHash;
    }

    protected function firstNonEmpty(array $values): ?string
    {
        foreach ($values as $value) {
            $text = trim((string) $value);
            if ($text !== '') {
                return $text;
            }
        }

        return null;
    }

    protected function extractUserColumnData(array $payload): array
    {
        $result = [];
        $rows = $payload['user_column_data'] ?? [];
        if (!is_array($rows)) {
            return $result;
        }

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $columnId = strtoupper(trim((string) ($row['column_id'] ?? '')));
            $value = trim((string) ($row['string_value'] ?? ''));
            if ($columnId === '' || $value === '') {
                continue;
            }

            if (in_array($columnId, ['COUNTRY', 'COUNTRY_CODE'], true) && !array_key_exists('country', $result)) {
                $result['country'] = $value;
            }
            if ($columnId === 'CITY' && !array_key_exists('city', $result)) {
                $result['city'] = $value;
            }
            if ($columnId === 'EMAIL' && !array_key_exists('email', $result)) {
                $result['email'] = $value;
            }
            if ($columnId === 'PHONE_NUMBER' && !array_key_exists('phone', $result)) {
                $result['phone'] = $value;
            }
            if ($columnId === 'FIRST_NAME' && !array_key_exists('first_name', $result)) {
                $result['first_name'] = $value;
            }
            if ($columnId === 'LAST_NAME' && !array_key_exists('last_name', $result)) {
                $result['last_name'] = $value;
            }
            if ($columnId === 'FULL_NAME' && !array_key_exists('full_name', $result)) {
                $result['full_name'] = $value;
            }
            if ($columnId === 'POSTAL_CODE' && !array_key_exists('postal_code', $result)) {
                $result['postal_code'] = $value;
            }
            if ($columnId === 'WORK_EMAIL' && !array_key_exists('work_email', $result)) {
                $result['work_email'] = $value;
            }
            if ($columnId === 'LANGUAGE' && !array_key_exists('language', $result)) {
                $result['language'] = $value;
            }
        }

        return $result;
    }

    protected function nullableTrim(?string $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    protected function maskSecret(string $secret): string
    {
        $length = strlen($secret);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 3) . str_repeat('*', max($length - 6, 0)) . substr($secret, -3);
    }

    protected function logIncomingRequest(Request $request, array $payload, string $userHash, string $campaignHash, array $context = []): void
    {
        Log::channel('google_ads_lead_form')->info('Google Ads lead form webhook inbound', array_merge([
            'route' => [
                'user_hash' => $userHash,
                'campaign_hash' => $campaignHash,
            ],
            'request' => [
                'method' => $request->method(),
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => (string) $request->userAgent(),
                'headers' => $request->headers->all(),
                'query' => $request->query(),
                'payload' => $payload,
                'raw_body' => $request->getContent(),
            ],
        ], $context));
    }

    protected function logOutgoingDispatch(string $stage, Campaign $campaign, Pageview $pageview, array $context = []): void
    {
        Log::channel('google_ads_lead_form')->info('Google Ads lead form outbound dispatch', array_merge([
            'stage' => $stage,
            'campaign_id' => (int) $campaign->id,
            'user_id' => (int) $campaign->user_id,
            'pageview_id' => (int) $pageview->id,
            'affiliate_platform_id' => (int) $campaign->affiliate_platform_id,
        ], $context));
    }
}
