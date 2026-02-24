<?php

namespace App\Services;

use App\Models\AdsConversion;
use App\Models\Lead;
use Illuminate\Support\Carbon;

class LeadConversionService
{
    /**
     * Serviço de orquestração da criação de conversão a partir de lead.
     *
     * Objetivo:
     * - centralizar regra de elegibilidade de conversão baseada no status do lead;
     * - evitar duplicidade por lead e por pageview;
     * - manter consistência de dados entre lead, conversão e pageview.
     */
    /**
     * @return array{
     *   created: bool,
     *   reason: string,
     *   conversion: ?AdsConversion
     * }
     */
    public function createIfEligible(Lead $lead, ?string $previousStatus = null): array
    {
        $currentStatus = strtolower(trim((string) $lead->lead_status));
        $previousStatus = $previousStatus !== null ? strtolower(trim($previousStatus)) : null;

        // Regra 1: só cria conversão para lead aprovado.
        if ($currentStatus !== Lead::STATUS_APPROVED) {
            return [
                'created' => false,
                'reason' => 'lead_not_approved',
                'conversion' => null,
            ];
        }

        // Regra 2: se já era aprovado antes, não recria.
        // Evita conversão duplicada em callbacks repetidos.
        if ($previousStatus === Lead::STATUS_APPROVED) {
            return [
                'created' => false,
                'reason' => 'already_approved_before',
                'conversion' => null,
            ];
        }

        // Regra 3: deduplicação primária por lead_id.
        $existingByLead = AdsConversion::query()
            ->where('lead_id', (int) $lead->id)
            ->first();

        if ($existingByLead) {
            return [
                'created' => false,
                'reason' => 'conversion_exists_for_lead',
                'conversion' => $existingByLead,
            ];
        }

        // Regra 4: deduplicação secundária por pageview no escopo da campanha.
        // Útil quando a conversão já existia antes do vínculo com lead.
        $existingByPageview = null;
        if (!empty($lead->pageview_id)) {
            $existingByPageview = AdsConversion::query()
                ->where('campaign_id', (int) $lead->campaign_id)
                ->where('pageview_id', (int) $lead->pageview_id)
                ->first();
        }

        if ($existingByPageview) {
            // Se havia conversão da pageview sem lead_id, retrovincula para rastreabilidade.
            if (empty($existingByPageview->lead_id)) {
                $existingByPageview->lead_id = (int) $lead->id;
                $existingByPageview->save();
            }

            return [
                'created' => false,
                'reason' => 'conversion_exists_for_pageview',
                'conversion' => $existingByPageview,
            ];
        }

        // Carrega relações necessárias para montar payload da conversão.
        $lead->loadMissing(['campaign.conversionGoal', 'pageview']);

        $pageview = $lead->pageview;
        // Valor mínimo defensivo: se payout inválido/zero, usa 1.00.
        $conversionValue = (float) $lead->payout_amount;
        if ($conversionValue <= 0) {
            $conversionValue = 1.00;
        }

        // Sanitiza moeda para padrão ISO (fallback USD).
        $currencyCode = strtoupper(trim((string) $lead->currency_code));
        if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
            $currencyCode = 'USD';
        }

        // Data do evento da conversão herda occurred_at do lead quando disponível.
        $conversionEventTime = $lead->occurred_at instanceof Carbon
            ? $lead->occurred_at
            : now();

        // Criação efetiva da conversão pronta para fila de upload Google.
        $conversion = AdsConversion::query()->create([
            'user_id' => (int) $lead->user_id,
            'campaign_id' => (int) $lead->campaign_id,
            'lead_id' => (int) $lead->id,
            'pageview_id' => $lead->pageview_id ? (int) $lead->pageview_id : null,
            'gclid' => $pageview?->gclid ? mb_substr((string) $pageview->gclid, 0, 150) : null,
            'gbraid' => $pageview?->gbraid ? mb_substr((string) $pageview->gbraid, 0, 150) : null,
            'wbraid' => $pageview?->wbraid ? mb_substr((string) $pageview->wbraid, 0, 150) : null,
            'user_agent' => $pageview?->user_agent ? mb_substr((string) $pageview->user_agent, 0, 500) : null,
            'ip_address' => $pageview?->ip ? mb_substr((string) $pageview->ip, 0, 45) : null,
            'conversion_name' => $lead->campaign?->conversionGoal?->goal_code ?: 'LEAD_APPROVED',
            'conversion_value' => $conversionValue,
            'currency_code' => $currencyCode,
            'conversion_event_time' => $conversionEventTime,
            'google_upload_status' => AdsConversion::STATUS_PENDING,
        ]);

        // Marca a pageview como convertida para manter coerência visual/analítica no painel.
        if ($pageview && !$pageview->conversion) {
            $pageview->conversion = true;
            $pageview->save();
        }

        return [
            'created' => true,
            'reason' => 'created',
            'conversion' => $conversion,
        ];
    }
}
