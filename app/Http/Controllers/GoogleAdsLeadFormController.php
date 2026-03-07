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
     * Endpoint público de webhook para Google Ads Lead Form.
     *
     * Resumo detalhado do fluxo:
     * 1) Normaliza o payload (JSON body e fallback de parsing) para um array consistente.
     * 2) Resolve os hashids da rota (`{userHash}-{campaignHash}`) em IDs internos.
     * 3) Registra log bruto de entrada (headers, body, query, payload e metadados da rota).
     * 4) Valida contexto:
     *    - identificadores válidos;
     *    - campanha existente para o usuário;
     *    - integração de formulário ativa na campanha.
     * 5) Autentica via chave compartilhada (`google_key`) comparando com
     *    `campaign.google_ads_form_key` usando `hash_equals`.
     * 6) Detecta requisições de teste (Google test webhook) por:
     *    - `is_test = true`; ou
     *    - `lead_id` iniciando com `TeSter` (fallback observado em payloads de teste).
     * 7) Persistência:
     *    - teste: salva pageview temporária e retorna 200;
     *    - produção: salva pageview e retorna 202.
     * 8) Pós-persistência: tenta enviar o lead para a plataforma afiliada da campanha
     *    (atualmente com handler específico para slug `dr_cash`).
     * 9) Loga resultado de aceite/rejeição e o resultado do dispatch de saída.
     *
     * Observações de operação:
     * - Este endpoint NÃO usa autenticação de sessão (webhook externo).
     * - Os códigos HTTP seguem semântica de integração:
     *   * 200: teste aceito;
     *   * 202: payload aceito para processamento assíncrono/integração;
     *   * 4xx: rejeição de validação/autenticação.
     */
    public function handle(Request $request, string $userHash, string $campaignHash): JsonResponse
    {
        // 1) Normalização de payload para suportar JSON puro e variações de envio.
        $payload = $this->resolvePayload($request);

        // 2) Resolução de contexto via hashids da URL.
        $hashidService = app(HashidService::class);
        $userId = $hashidService->decode($userHash);
        $campaignId = $hashidService->decode($campaignHash);

        // 3) Auditoria de entrada antes de qualquer validação/rejeição.
        $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
            'stage' => 'received',
        ]);

        // 4.a) Rejeita cedo quando hashids não são válidos.
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

        // 4.b) Rejeita se campanha não for encontrada no escopo do usuário resolvido.
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

        // 4.c) Rejeita se integração estiver desativada para a campanha.
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

        // 5) Autenticação por chave compartilhada enviada pelo Google no payload.
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

        // 6 + 7.a) Fluxo de teste: persiste pageview para auditoria e retorna 200.
        if ($this->isTestWebhook($payload)) {
            $pageview = $this->storeAsPageview($request, $campaign, $payload);

            // 8) Mesmo em teste, envia para afiliado temporariamente para validação ponta a ponta.
            $this->dispatchLeadToAffiliatePlatform($request, $campaign, $pageview, $payload);

            $this->logIncomingRequest($request, $payload, $userHash, $campaignHash, [
                'stage' => 'accepted_test',
                'campaign_id' => (int) $campaign->id,
                'user_id' => (int) $campaign->user_id,
                'pageview_id' => (int) $pageview->id,
            ]);

            return response()->json([
                'ok' => true,
                'message' => 'Teste do webhook recebido com sucesso.',
                'is_test' => true,
                'pageview_id' => (int) $pageview->id,
            ], 200);
        }

        // 7.b) Fluxo de produção: persiste pageview e segue com dispatch.
        $pageview = $this->storeAsPageview($request, $campaign, $payload);

        // 8) Dispatch para afiliado (handler por slug).
        $this->dispatchLeadToAffiliatePlatform($request, $campaign, $pageview, $payload);

        // 9) Registro final de aceite para observabilidade operacional.
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

    /**
     * Detecta requisição de teste do Google Ads.
     *
     * Critérios:
     * - campo explícito `is_test=true`; ou
     * - `lead_id` com prefixo "tester" (fallback defensivo).
     */
    protected function isTestWebhook(array $payload): bool
    {
        if (filter_var($payload['is_test'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $leadId = trim((string) ($payload['lead_id'] ?? ''));
        if ($leadId === '') {
            return false;
        }

        return str_starts_with(strtolower($leadId), 'tester');
    }

    /**
     * Resolve payload para array.
     *
     * Prioriza `$request->all()` e, quando necessário, faz parse do corpo bruto JSON.
     */
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

    /**
     * Extrai chave de autenticação do payload suportando variações de naming.
     */
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

    /**
     * Persiste uma representação de lead webhook na tabela de pageviews.
     *
     * Objetivo principal:
     * - rastreabilidade da entrada;
     * - reaproveitar infraestrutura de análise já existente baseada em pageview.
     */
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

    /**
     * Envia lead para a plataforma afiliada (dispatch de saída).
     *
     * Estratégia atual:
     * - branch por slug da plataforma;
     * - implementado: `dr_cash`;
     * - demais slugs: log de skip controlado.
     */
    protected function dispatchLeadToAffiliatePlatform(
        Request $request,
        Campaign $campaign,
        Pageview $pageview,
        array $payload
    ): void {
        $campaign->loadMissing('affiliatePlatform');
        $platform = $campaign->affiliatePlatform;
        if (!$platform) {
            $this->logOutgoingDispatch('skipped', $campaign, $pageview, [
                'reason' => 'affiliate_platform_not_found',
            ]);
            return;
        }

        $columns = $this->extractUserColumnData($payload);
        $client = is_array($payload['client'] ?? null) ? $payload['client'] : [];
        $composedSub1 = $this->buildComposedSub1($campaign, $pageview);
        $streamCode = trim((string) ($campaign->stream_code ?? ''));
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

        // Handler temporário específico por plataforma (extensível para strategy/provider no futuro).
        $slug = trim((string) $platform->slug);
        if ($slug !== 'dr_cash') {
            $this->logOutgoingDispatch('skipped', $campaign, $pageview, [
                'reason' => 'platform_handler_not_implemented',
                'platform_slug' => $slug,
                'payload_preview' => $body,
            ]);
            return;
        }

        $postUrl = trim((string) ($platform->postback_url ?? ''));
        $apiKey = trim((string) ($platform->api_post_key ?? ''));

        if ($postUrl === '' || $apiKey === '' || $streamCode === '') {
            $this->logOutgoingDispatch('skipped', $campaign, $pageview, [
                'reason' => 'missing_post_dispatch_config',
                'platform_slug' => $slug,
                'has_postback_url' => $postUrl !== '',
                'has_api_post_key' => $apiKey !== '',
                'has_stream_code' => $streamCode !== '',
                'configured_postback_url' => $postUrl !== '' ? $postUrl : null,
                'configured_api_post_key_masked' => $apiKey !== '' ? $this->maskSecret($apiKey) : null,
                'payload_preview' => $body,
            ]);
            return;
        }

        $startedAt = microtime(true);
        $this->logOutgoingDispatch('outgoing_request', $campaign, $pageview, [
            'platform_slug' => $slug,
            'request_url' => $postUrl,
            'request_method' => 'POST',
            'request_headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->maskSecret($apiKey),
            ],
            'request_body' => $body,
        ]);

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->withToken($apiKey)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($postUrl, $body);

            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $status = $response->status();
            $success = $status >= 200 && $status < 300;

            $this->logOutgoingDispatch('outgoing_response', $campaign, $pageview, [
                'platform_slug' => $slug,
                'request_url' => $postUrl,
                'success' => $success,
                'elapsed_ms' => $elapsedMs,
                'response_status' => $status,
                'response_headers' => $response->headers(),
                'response_body' => $response->body(),
            ]);
        } catch (\Throwable $e) {
            $elapsedMs = (int) round((microtime(true) - $startedAt) * 1000);
            $this->logOutgoingDispatch('outgoing_response', $campaign, $pageview, [
                'platform_slug' => $slug,
                'request_url' => $postUrl,
                'success' => false,
                'elapsed_ms' => $elapsedMs,
                'response_status' => null,
                'response_headers' => null,
                'response_body' => null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Monta sub1 composto no padrão:
     * {userHash}-{campaignHash}-{pageviewHash}
     */
    protected function buildComposedSub1(Campaign $campaign, Pageview $pageview): string
    {
        $hashidService = app(HashidService::class);
        $userHash = $hashidService->encode((int) $campaign->user_id);
        $campaignHash = $hashidService->encode((int) $campaign->id);
        $pageviewHash = $hashidService->encode((int) $pageview->id);

        return $userHash . '-' . $campaignHash . '-' . $pageviewHash;
    }

    /**
     * Retorna o primeiro valor não vazio da lista (após trim).
     */
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

    /**
     * Extrai colunas do array `user_column_data` enviado pelo Google.
     *
     * Mantém apenas campos relevantes para mapeamentos atuais.
     */
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

    /**
     * Normaliza string opcional com trim e limite de tamanho.
     */
    protected function nullableTrim(?string $value, int $maxLength): ?string
    {
        $text = trim((string) $value);
        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    /**
     * Mascara segredo para logging seguro.
     */
    protected function maskSecret(string $secret): string
    {
        $length = strlen($secret);
        if ($length <= 6) {
            return str_repeat('*', $length);
        }

        return substr($secret, 0, 3) . str_repeat('*', max($length - 6, 0)) . substr($secret, -3);
    }

    /**
     * Log estruturado da entrada do webhook.
     */
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

    /**
     * Log estruturado do dispatch de saída para plataforma afiliada.
     */
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
