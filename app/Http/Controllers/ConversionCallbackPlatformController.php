<?php

namespace App\Http\Controllers;

use App\Models\AdsConversion;
use App\Models\AffiliatePlatform;
use App\Models\Campaign;
use App\Models\Pageview;
use App\Services\ClickhousePageviewUpdater;
use App\Services\HashidService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ConversionCallbackPlatformController extends Controller
{
    /**
     * Callback dinâmico por plataforma.
     * Valida plataforma/usuário, resolve código composto via mapping
     * e cria conversão automática vinculada ao pageview.
     *
     * Exemplo:
     * /api/callback-platform/dr_cash/{userCode}?subid1=ABC123-CMP001-PV001&amount=9.50&cy=USD
     */
    public function handle(Request $request, string $platformSlug, string $userCode)
    {
        $log = Log::channel('affiliate_platform_callback');
        $log->info('CALLBACK PLATFORM RAW', $request->query());

        $userIdFromRoute = $this->resolveUserIdFromToken($userCode);
        if (!$userIdFromRoute) {
            $log->warning('Callback platform ignorado: user_code inválido.', [
                'platform_slug' => $platformSlug,
                'user_code' => $userCode,
            ]);
            return 'ignored';
        }

        $platform = AffiliatePlatform::query()
            ->where('slug', $platformSlug)
            ->where('active', true)
            ->first();

        if (!$platform) {
            $log->warning('Callback platform ignorado: plataforma inválida/inativa.', [
                'platform_slug' => $platformSlug,
            ]);
            return 'ignored';
        }

        $status = strtolower(trim((string) $request->query('status', '')));
        if ($status !== 'approved') {
            $log->info('Callback platform ignorado: status diferente de approved.', [
                'platform_slug' => $platformSlug,
                'status' => $status,
                'query' => $request->query(),
            ]);
            return 'ignored';
        }

        $composedCode = $this->resolveComposedCodeFromRequest($request, $platform);
        if (!$composedCode) {
            $log->warning('Callback platform ignorado: código composto não encontrado.', [
                'platform_slug' => $platformSlug,
                'query' => $request->query(),
            ]);
            return 'ignored';
        }

        // Formato esperado do código composto: {userCode}-{campaignCode}-{pageviewCode}
        [$userCodeFromSub, $campaignCode, $pageviewToken] = explode('-', $composedCode, 3);
        $userIdFromSub = $this->resolveUserIdFromToken($userCodeFromSub);
        $campaignId = $this->resolveCampaignIdFromToken($campaignCode);
        $pageviewId = $this->resolvePageviewIdFromToken($pageviewToken);

        if (!$userIdFromSub || !$campaignId || !$pageviewId) {
            $log->warning('Callback platform ignorado: token composto inválido.', [
                'platform_slug' => $platformSlug,
                'composed_code' => $composedCode,
            ]);
            return 'ignored';
        }

        if ((int) $userIdFromSub !== (int) $userIdFromRoute) {
            $log->warning('Callback platform ignorado: user_code da rota diverge do token composto.', [
                'platform_slug' => $platformSlug,
                'user_route' => $userIdFromRoute,
                'user_sub' => $userIdFromSub,
            ]);
            return 'ignored';
        }

        $campaign = Campaign::with('conversionGoal')
            ->where('id', $campaignId)
            ->where('user_id', $userIdFromRoute)
            ->where('code', $campaignCode)
            ->where('affiliate_platform_id', $platform->id)
            ->first();

        if (!$campaign) {
            $log->warning('Callback platform ignorado: campanha não encontrada para plataforma.', [
                'platform_slug' => $platformSlug,
                'campaign_id' => $campaignId,
                'campaign_code' => $campaignCode,
            ]);
            return 'ignored';
        }

        $pageview = Pageview::query()->find($pageviewId);
        if (!$pageview) {
            $log->warning('Callback platform ignorado: pageview não encontrada.', [
                'platform_slug' => $platformSlug,
                'pageview_id' => $pageviewId,
            ]);
            return 'ignored';
        }

        if ((int) $pageview->campaign_id !== (int) $campaign->id || (int) $pageview->user_id !== (int) $campaign->user_id) {
            $log->warning('Callback platform ignorado: pageview não pertence à campanha/usuário.', [
                'platform_slug' => $platformSlug,
                'pageview_id' => $pageview->id,
                'pageview_campaign_id' => $pageview->campaign_id,
                'campaign_id' => $campaign->id,
                'pageview_user_id' => $pageview->user_id,
                'campaign_user_id' => $campaign->user_id,
            ]);
            return 'ignored';
        }

        if ($pageview->conversion) {
            $log->info('Callback platform ignorado: pageview já convertida.', [
                'platform_slug' => $platformSlug,
                'pageview_id' => $pageview->id,
            ]);
            return 'ignored';
        }

        $pageview->update(['conversion' => 1]);

        if ((bool) config('clickhouse.active', false)) {
            try {
                app(ClickhousePageviewUpdater::class)->markConversion((int) $pageview->id);
            } catch (\Throwable $e) {
                $log->warning('Falha ao marcar conversão da pageview no ClickHouse.', [
                    'platform_slug' => $platformSlug,
                    'pageview_id' => (int) $pageview->id,
                    'campaign_id' => (int) $campaign->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $existingConversion = AdsConversion::query()
            ->where('pageview_id', $pageview->id)
            ->where('campaign_id', $campaign->id)
            ->first();

        if ($existingConversion) {
            return 'ok';
        }

        // Valor e moeda priorizam os campos mapeados na plataforma.
        // Se não estiverem configurados, aplica fallback legado.
        $conversionValue = $this->resolveConversionValue($request, $platform);
        $currencyCode = $this->resolveCurrencyCode($request, $platform);

        AdsConversion::create([
            'user_id' => $campaign->user_id,
            'campaign_id' => $campaign->id,
            'pageview_id' => $pageview->id,
            'gclid' => $pageview->gclid,
            'gbraid' => $pageview->gbraid,
            'wbraid' => $pageview->wbraid,
            'user_agent' => '',
            'ip_address' => $pageview->ip ?: $request->ip(),
            'conversion_name' => $campaign->conversionGoal?->goal_code,
            'conversion_value' => $conversionValue,
            'currency_code' => $currencyCode,
            'conversion_event_time' => now(),
            'google_upload_status' => AdsConversion::STATUS_PENDING,
        ]);

        return 'ok';
    }

    protected function resolveComposedCodeFromRequest(Request $request, AffiliatePlatform $platform): ?string
    {
        $log = Log::channel('affiliate_platform_callback');

        // Lê somente os campos de retorno mapeados (values de tracking_param_mapping),
        // onde o código composto deve chegar no postback.
        // Exemplo de mapping: { "sub1": "subid1", "sub2": "subid2" }
        // Exemplo de entrada: ?subid1=ABC123-CMP001-PV001
        $mapping = $platform->tracking_param_mapping ?: [];
        $mappedTargets = array_values(array_filter(array_map(
            fn ($value) => trim((string) $value),
            array_values($mapping)
        )));

        $candidateFields = array_values(array_unique($mappedTargets));

        foreach ($candidateFields as $field) {
            $value = trim((string) $request->query($field, ''));
            if ($value === '') {
                continue;
            }

            if (preg_match('/^[A-Za-z0-9]+-[A-Za-z0-9]+-[A-Za-z0-9]+$/', $value)) {
                $log->info('Callback platform: código composto encontrado via mapeamento.', [
                    'platform_slug' => $platform->slug,
                    'mapped_field' => $field,
                    'composed_code' => $value,
                ]);
                return $value;
            }
        }

        return null;
    }

    protected function resolveConversionValue(Request $request, AffiliatePlatform $platform): float
    {
        $log = Log::channel('affiliate_platform_callback');

        // Mapeamento fixo por chave de domínio: conversion_value -> nome do parâmetro no callback.
        // Exemplo: conversion_param_mapping = { "conversion_value": "amount" }
        // Exemplo de entrada: ?amount=9.50
        $mapping = $platform->conversion_param_mapping ?: [];
        $mappedField = trim((string) ($mapping['conversion_value'] ?? ''));
        $mappedValue = $this->resolveMappedParamValue($request, $platform, 'conversion_value');
        if ($mappedValue !== null) {
            $value = $this->parseMonetaryValue($mappedValue);
            if ($value !== null) {
                $log->info('Callback platform: valor da conversão resolvido via mapeamento.', [
                    'platform_slug' => $platform->slug,
                    'mapped_field' => $mappedField,
                    'raw_value' => $mappedValue,
                    'normalized_value' => $value,
                ]);
                return $value > 0 ? $value : 1.00;
            }

            $log->warning('Callback platform: valor mapeado inválido; aplicando fallback 1.00.', [
                'platform_slug' => $platform->slug,
                'mapped_field' => $mappedField,
                'raw_value' => $mappedValue,
            ]);
        }

        $candidates = ['amount', 'payment', 'payout', 'reward', 'value'];
        foreach ($candidates as $key) {
            $raw = $request->query($key);
            if ($raw === null || $raw === '') {
                continue;
            }

            $value = $this->parseMonetaryValue((string) $raw);
            if ($value !== null) {
                return $value > 0 ? $value : 1.00;
            }
        }

        return 1.00;
    }

    protected function resolveCurrencyCode(Request $request, AffiliatePlatform $platform): string
    {
        $log = Log::channel('affiliate_platform_callback');

        // Mapeamento fixo por chave de domínio: currency_code -> nome do parâmetro no callback.
        // Exemplo: conversion_param_mapping = { "currency_code": "cy" }
        // Exemplo de entrada: ?cy=USD
        $mapping = $platform->conversion_param_mapping ?: [];
        $mappedField = trim((string) ($mapping['currency_code'] ?? ''));
        $mappedValue = $this->resolveMappedParamValue($request, $platform, 'currency_code');
        if ($mappedValue !== null) {
            $normalized = strtoupper(trim($mappedValue));
            if (preg_match('/^[A-Z]{3}$/', $normalized)) {
                $log->info('Callback platform: moeda resolvida via mapeamento.', [
                    'platform_slug' => $platform->slug,
                    'mapped_field' => $mappedField,
                    'raw_value' => $mappedValue,
                    'normalized_currency' => $normalized,
                ]);
                return $normalized;
            }

            $log->warning('Callback platform: moeda mapeada inválida; aplicando fallback USD.', [
                'platform_slug' => $platform->slug,
                'mapped_field' => $mappedField,
                'raw_value' => $mappedValue,
            ]);
        }

        $candidates = ['cy', 'currency', 'currency_code'];
        foreach ($candidates as $key) {
            $raw = strtoupper(trim((string) $request->query($key, '')));
            if (preg_match('/^[A-Z]{3}$/', $raw)) {
                return $raw;
            }
        }

        return 'USD';
    }

    protected function parseMonetaryValue(string $raw): ?float
    {
        // Exemplos válidos: "10", "10.25", "10,25"
        // Exemplos inválidos: "USD", "10,2,5", "abc"
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

    protected function resolveMappedParamValue(Request $request, AffiliatePlatform $platform, string $mappingKey): ?string
    {
        // As chaves de mapping são fixas no domínio.
        // O usuário altera apenas o valor (nome do parâmetro recebido no postback).
        // Exemplo:
        // mappingKey = "conversion_value"
        // conversion_param_mapping = { "conversion_value": "payment", "currency_code": "cy" }
        // query = ?payment=9.50&cy=USD
        $mapping = $platform->conversion_param_mapping ?: [];
        $field = trim((string) ($mapping[$mappingKey] ?? ''));
        if ($field === '') {
            return null;
        }

        $value = trim((string) $request->query($field, ''));
        return $value === '' ? null : $value;
    }

    protected function resolvePageviewIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }

    protected function resolveCampaignIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }

    protected function resolveUserIdFromToken(string $token): ?int
    {
        $value = trim($token);
        if ($value === '') {
            return null;
        }

        return app(HashidService::class)->decode($value);
    }
}
