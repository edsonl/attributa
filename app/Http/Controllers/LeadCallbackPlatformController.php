<?php

namespace App\Http\Controllers;

use App\Models\AffiliatePlatform;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Pageview;
use App\Services\LeadConversionService;
use App\Services\LeadIngestionService;
use App\Services\LeadNotificationService;
use App\Services\HashidService;
use Illuminate\Support\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LeadCallbackPlatformController extends Controller
{
    /**
     * Controller de entrada para callbacks de LEAD vindos de plataformas afiliadas.
     *
     * Responsabilidades principais:
     * 1) Resolver e validar contexto (usuário, plataforma, campanha, pageview).
     * 2) Normalizar payload com base no mapeamento configurado na plataforma.
     * 3) Criar/atualizar lead de forma idempotente (via service de ingestão).
     * 4) Disparar eventual criação de conversão quando o lead ficar elegível.
     *
     * Observação:
     * - Este fluxo é independente do callback legado de conversão.
     * - A resposta retorna dados resolvidos para facilitar depuração da integração.
     */
    /**
     * Resolve os identificadores e registra/atualiza o lead com base no callback.
     *
     * Exemplo:
     * /api/get/platform-lead/dr_cash/{userCode}?subid1=ABC123-CMP001-PV001&payment=9.50&currency=USD&status=approved&uuid=ABC123
     */
    public function handle(
        Request $request,
        string $platformSlug,
        string $userCode,
        LeadIngestionService $leadIngestionService,
        LeadConversionService $leadConversionService,
        LeadNotificationService $leadNotificationService
    ): JsonResponse
    {
        // Canal dedicado para auditoria de integrações com plataformas.
        $log = Log::channel('affiliate_platform_callback');
        $log->info('CALLBACK LEAD PLATFORM RAW', $request->query());

        // 1) Usuário da rota: primeiro filtro de segurança/consistência.
        $userIdFromRoute = $this->resolveUserIdFromToken($userCode);
        if (!$userIdFromRoute) {
            $log->warning('Lead callback ignorado: user_code inválido.', [
                'platform_slug' => $platformSlug,
                'user_code' => $userCode,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'user_code inválido.',
            ], 422);
        }

        // 2) Plataforma ativa: impede callbacks de slugs inválidos ou desativados.
        $platform = AffiliatePlatform::query()
            ->where('slug', $platformSlug)
            ->where('active', true)
            ->first();

        if (!$platform) {
            $log->warning('Lead callback ignorado: plataforma inválida/inativa.', [
                'platform_slug' => $platformSlug,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Plataforma inválida ou inativa.',
            ], 422);
        }

        // 3) Código composto (user-campaign-pageview), geralmente vindo em subid/sub.
        //    Esse código conecta o callback ao rastro original da visita.
        $composedCode = $this->resolveComposedCodeFromRequest($request, $platform);
        if (!$composedCode) {
            $log->warning('Lead callback ignorado: código composto não encontrado.', [
                'platform_slug' => $platformSlug,
                'query' => $request->query(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Código composto não encontrado.',
            ], 422);
        }

        // 4) Decodifica o composto em IDs internos (via HashidService).
        [$userCodeFromSub, $campaignCode, $pageviewToken] = explode('-', $composedCode, 3);
        $userIdFromSub = $this->resolveUserIdFromToken($userCodeFromSub);
        $campaignId = $this->resolveCampaignIdFromToken($campaignCode);
        $pageviewId = $this->resolvePageviewIdFromToken($pageviewToken);

        if (!$userIdFromSub || !$campaignId || !$pageviewId) {
            $log->warning('Lead callback ignorado: token composto inválido.', [
                'platform_slug' => $platformSlug,
                'composed_code' => $composedCode,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Token composto inválido.',
            ], 422);
        }

        // 5) Segurança adicional: usuário da URL deve bater com usuário do composto.
        if ((int) $userIdFromSub !== (int) $userIdFromRoute) {
            $log->warning('Lead callback ignorado: user_code da rota diverge do token composto.', [
                'platform_slug' => $platformSlug,
                'user_route' => $userIdFromRoute,
                'user_sub' => $userIdFromSub,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'user_code da rota diverge do token composto.',
            ], 422);
        }

        // 6) Campanha precisa pertencer ao usuário e à plataforma do callback.
        $campaign = Campaign::query()
            ->where('id', $campaignId)
            ->where('user_id', $userIdFromRoute)
            ->where('code', $campaignCode)
            ->where('affiliate_platform_id', $platform->id)
            ->first();

        if (!$campaign) {
            $log->warning('Lead callback ignorado: campanha não encontrada para plataforma.', [
                'platform_slug' => $platformSlug,
                'campaign_id' => $campaignId,
                'campaign_code' => $campaignCode,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Campanha não encontrada para a plataforma.',
            ], 422);
        }

        // 7) Pageview precisa existir e estar vinculada à mesma campanha/usuário.
        $pageview = Pageview::query()->find($pageviewId);
        if (!$pageview) {
            $log->warning('Lead callback ignorado: pageview não encontrada.', [
                'platform_slug' => $platformSlug,
                'pageview_id' => $pageviewId,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Pageview não encontrada.',
            ], 422);
        }

        if ((int) $pageview->campaign_id !== (int) $campaign->id || (int) $pageview->user_id !== (int) $campaign->user_id) {
            $log->warning('Lead callback ignorado: pageview não pertence à campanha/usuário.', [
                'platform_slug' => $platformSlug,
                'pageview_id' => $pageview->id,
                'pageview_campaign_id' => $pageview->campaign_id,
                'campaign_id' => $campaign->id,
                'pageview_user_id' => $pageview->user_id,
                'campaign_user_id' => $campaign->user_id,
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'Pageview não pertence à campanha/usuário.',
            ], 422);
        }

        // 8) Normalização dos campos de negócio (status, valor, moeda, oferta, data).
        //    Usa mapping da plataforma e fallback para chaves comuns.
        $statusRaw = $this->resolveRawLeadStatus($request, $platform);
        $statusMapped = $this->mapLeadStatus($statusRaw, $platform);
        $platformLeadId = $this->resolveMappedParamValue($request, $platform, 'platform_lead_id');
        $payoutAmount = $this->resolvePayoutAmount($request, $platform);
        $currencyCode = $this->resolveCurrencyCode($request, $platform);
        $offerId = $this->resolveOfferId($request, $platform);
        $occurredAt = $this->resolveOccurredAt($request, $platform);

        // 9) Ingestão idempotente: atualiza se já existe (affiliate_platform_id + platform_lead_id),
        //    cria caso contrário; mantém payload bruto para auditoria.
        $ingestion = $leadIngestionService->upsertFromNormalizedData(
            campaign: $campaign,
            pageview: $pageview,
            platform: $platform,
            attributes: [
                'platform_lead_id' => $platformLeadId,
                'status_raw' => $statusRaw,
                'status_mapped' => $statusMapped,
                'payout_amount' => $payoutAmount,
                'currency_code' => $currencyCode,
                'offer_id' => $offerId,
                'occurred_at' => $occurredAt,
                'payload_json' => $request->query(),
            ]
        );
        $lead = $ingestion['lead'];
        $operation = $ingestion['operation'];

        // 10) Notificação desacoplada (categoria Leads), reutilizável para outras entradas.
        //     Em update, dispara apenas quando houver mudança de status.
        $leadNotificationService->notifyFromIngestion(
            lead: $lead,
            operation: $operation,
            previousStatus: $ingestion['previous_status'] ?? null
        );

        // 11) Criação de conversão desacoplada em service:
        //     apenas quando status do lead é elegível (approved) e sem duplicidade.
        $conversionOutcome = $leadConversionService->createIfEligible(
            lead: $lead,
            previousStatus: $ingestion['previous_status']
        );
        $conversionCreated = (bool) ($conversionOutcome['created'] ?? false);
        $conversion = $conversionOutcome['conversion'] ?? null;
        $conversionReason = (string) ($conversionOutcome['reason'] ?? 'unknown');

        $log->info('Lead callback processado com sucesso.', [
            'platform_slug' => $platformSlug,
            'campaign_id' => $campaign->id,
            'pageview_id' => $pageview->id,
            'lead_id' => $lead->id,
            'operation' => $operation,
            'platform_lead_id' => $platformLeadId,
            'status_raw' => $statusRaw,
            'status_mapped' => $statusMapped,
            'payout_amount' => $payoutAmount,
            'currency_code' => $currencyCode,
            'offer_id' => $offerId,
            'conversion_created' => $conversionCreated,
            'conversion_id' => $conversion?->id,
            'conversion_reason' => $conversionReason,
        ]);

        return response()->json([
            'ok' => true,
            'resolved' => [
                'user_id' => (int) $campaign->user_id,
                'campaign_id' => (int) $campaign->id,
                'pageview_id' => (int) $pageview->id,
                'affiliate_platform_id' => (int) $platform->id,
                'platform_slug' => (string) $platform->slug,
                'lead_id' => (int) $lead->id,
                'operation' => $operation,
                'platform_lead_id' => $platformLeadId,
                'payout_amount' => $payoutAmount,
                'currency_code' => $currencyCode,
                'offer_id' => $offerId,
                'occurred_at' => $occurredAt?->toIso8601String(),
                'status_raw' => $statusRaw === '' ? null : $statusRaw,
                'status_mapped' => $statusMapped,
                'conversion_created' => $conversionCreated,
                'conversion_id' => $conversion?->id,
                'conversion_reason' => $conversionReason,
                'composed_code' => $composedCode,
            ],
        ]);
    }

    /**
     * Resolve status bruto priorizando o mapeamento configurado na plataforma
     * (lead_param_mapping.lead_status), com fallback em `status`.
     */
    protected function resolveRawLeadStatus(Request $request, AffiliatePlatform $platform): string
    {
        $mapping = $platform->lead_param_mapping ?: [];
        $statusField = strtolower(trim((string) ($mapping['lead_status'] ?? '')));

        if ($statusField !== '') {
            $mappedValue = strtolower(trim((string) $request->query($statusField, '')));
            if ($mappedValue !== '') {
                return $mappedValue;
            }
        }

        return strtolower(trim((string) $request->query('status', '')));
    }

    /**
     * Mapeia status bruto para status canônico interno.
     *
     * Prioridade:
     * 1) lead_status_mapping configurado na plataforma
     * 2) status já canônico recebido diretamente
     * 3) fallback `processing`
     */
    protected function mapLeadStatus(string $statusRaw, AffiliatePlatform $platform): string
    {
        $normalized = strtolower(trim($statusRaw));
        if ($normalized === '') {
            return Lead::STATUS_PROCESSING;
        }

        $mapping = $platform->lead_status_mapping ?: [];
        $mapped = strtolower(trim((string) ($mapping[$normalized] ?? '')));

        if (in_array($mapped, Lead::ALLOWED_STATUSES, true)) {
            return $mapped;
        }

        // Fallbacks para variações comuns entre plataformas:
        // canceled/cancelled, refund/refunded, charge-back/charge_back/cb, etc.
        $fallbackAliases = [
            'canceled' => Lead::STATUS_CANCELLED,
            'cancelled' => Lead::STATUS_CANCELLED,
            'cancel' => Lead::STATUS_CANCELLED,
            'order_canceled' => Lead::STATUS_CANCELLED,
            'order_cancelled' => Lead::STATUS_CANCELLED,
            'refund' => Lead::STATUS_REFUNDED,
            'refunded' => Lead::STATUS_REFUNDED,
            'partial_refund' => Lead::STATUS_REFUNDED,
            'chargeback' => Lead::STATUS_CHARGEBACK,
            'charge_back' => Lead::STATUS_CHARGEBACK,
            'charge-back' => Lead::STATUS_CHARGEBACK,
            'cb' => Lead::STATUS_CHARGEBACK,
        ];

        if (array_key_exists($normalized, $fallbackAliases)) {
            return $fallbackAliases[$normalized];
        }

        if (in_array($normalized, Lead::ALLOWED_STATUSES, true)) {
            return $normalized;
        }

        return Lead::STATUS_PROCESSING;
    }

    /**
     * Resolve um campo via mapeamento de parâmetros da plataforma.
     * Ex.: mappingKey=platform_lead_id pode apontar para `uuid`.
     */
    protected function resolveMappedParamValue(Request $request, AffiliatePlatform $platform, string $mappingKey): ?string
    {
        $mapping = $platform->lead_param_mapping ?: [];
        $field = trim((string) ($mapping[$mappingKey] ?? ''));
        if ($field === '') {
            return null;
        }

        $value = trim((string) $request->query($field, ''));
        return $value === '' ? null : $value;
    }

    /**
     * Resolve valor de payout em formato decimal string (2 casas).
     * Aceita mapping + chaves de fallback comuns.
     */
    protected function resolvePayoutAmount(Request $request, AffiliatePlatform $platform): string
    {
        $mapped = $this->resolveMappedParamValue($request, $platform, 'payout_amount');
        if ($mapped !== null) {
            $value = $this->parseMonetaryValue($mapped);
            if ($value !== null) {
                return number_format(max($value, 0), 2, '.', '');
            }
        }

        foreach (['payment', 'payout', 'amount', 'value'] as $key) {
            $raw = trim((string) $request->query($key, ''));
            if ($raw === '') {
                continue;
            }
            $value = $this->parseMonetaryValue($raw);
            if ($value !== null) {
                return number_format(max($value, 0), 2, '.', '');
            }
        }

        return '0.00';
    }

    /**
     * Resolve moeda (ISO-4217, 3 letras) com mapping e fallback.
     */
    protected function resolveCurrencyCode(Request $request, AffiliatePlatform $platform): string
    {
        $mapped = $this->resolveMappedParamValue($request, $platform, 'currency_code');
        if ($mapped !== null) {
            $normalized = strtoupper(trim($mapped));
            if (preg_match('/^[A-Z]{3}$/', $normalized) === 1) {
                return $normalized;
            }
        }

        foreach (['currency', 'cy', 'currency_code'] as $key) {
            $raw = strtoupper(trim((string) $request->query($key, '')));
            if (preg_match('/^[A-Z]{3}$/', $raw) === 1) {
                return $raw;
            }
        }

        return 'USD';
    }

    /**
     * Resolve offer_id inteiro via mapping ou fallback.
     */
    protected function resolveOfferId(Request $request, AffiliatePlatform $platform): ?int
    {
        $mapped = $this->resolveMappedParamValue($request, $platform, 'offer_id');
        if ($mapped !== null) {
            return ctype_digit($mapped) ? (int) $mapped : null;
        }

        $fallback = trim((string) $request->query('offer', ''));
        if ($fallback !== '' && ctype_digit($fallback)) {
            return (int) $fallback;
        }

        return null;
    }

    /**
     * Resolve data/hora do evento do lead.
     * Se não vier data válida, usa `now()` para não perder rastreabilidade temporal.
     */
    protected function resolveOccurredAt(Request $request, AffiliatePlatform $platform): ?Carbon
    {
        $mapped = $this->resolveMappedParamValue($request, $platform, 'occurred_at');
        if ($mapped !== null) {
            $parsed = $this->parseDateValue($mapped);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        foreach (['date', 'occurred_at', 'event_time'] as $key) {
            $raw = trim((string) $request->query($key, ''));
            if ($raw === '') {
                continue;
            }
            $parsed = $this->parseDateValue($raw);
            if ($parsed !== null) {
                return $parsed;
            }
        }

        return now();
    }

    /**
     * Converte timestamp (s/ms) ou texto de data para Carbon UTC.
     */
    protected function parseDateValue(string $raw): ?Carbon
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $numeric = (int) $value;
            if (strlen($value) >= 13) {
                $numeric = (int) floor($numeric / 1000);
            }

            try {
                return Carbon::createFromTimestampUTC($numeric);
            } catch (\Throwable) {
                return null;
            }
        }

        try {
            // Entrada textual, ex. ISO8601, RFC, etc.
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normaliza string monetária para float.
     * Aceita vírgula decimal quando não houver ponto.
     */
    protected function parseMonetaryValue(string $raw): ?float
    {
        $value = trim($raw);
        if ($value === '') {
            return null;
        }

        $normalized = str_replace(' ', '', $value);
        if (str_contains($normalized, ',') && !str_contains($normalized, '.')) {
            $normalized = str_replace(',', '.', $normalized);
        }

        if (!preg_match('/^-?\d+(?:\.\d+)?$/', $normalized)) {
            return null;
        }

        return (float) $normalized;
    }

    /**
     * Resolve código composto a partir dos mappings de tracking.
     * Fallback em subid1..5 / sub1..5 para facilitar setup inicial.
     */
    protected function resolveComposedCodeFromRequest(Request $request, AffiliatePlatform $platform): ?string
    {
        $mapping = $platform->tracking_param_mapping ?: [];
        $mappedTargets = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            array_values($mapping)
        )));

        $candidateFields = array_values(array_unique($mappedTargets));
        foreach ($candidateFields as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value !== '' && $this->isValidComposedCode($value)) {
                return $value;
            }
        }

        // Fallback para facilitar testes quando o mapping ainda não estiver configurado.
        for ($i = 1; $i <= 5; $i++) {
            foreach (["subid{$i}", "sub{$i}"] as $field) {
                $value = trim((string) $request->query($field, ''));
                if ($value !== '' && $this->isValidComposedCode($value)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Formato esperado: TOKEN-TOKEN-TOKEN (user-campaign-pageview).
     */
    protected function isValidComposedCode(string $value): bool
    {
        return preg_match('/^[A-Za-z0-9]+-[A-Za-z0-9]+-[A-Za-z0-9]+$/', $value) === 1;
    }

    /**
     * Decodifica token hashid da pageview para ID interno.
     */
    protected function resolvePageviewIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }

    /**
     * Decodifica código hashid de campanha para ID interno.
     */
    protected function resolveCampaignIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }

    /**
     * Decodifica código hashid de usuário para ID interno.
     */
    protected function resolveUserIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }
}
