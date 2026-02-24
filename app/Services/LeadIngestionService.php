<?php

namespace App\Services;

use App\Models\AffiliatePlatform;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\Pageview;
use Illuminate\Support\Carbon;

class LeadIngestionService
{
    /**
     * Serviço responsável por persistir LEAD de forma idempotente.
     *
     * Regras centrais:
     * - Se houver `platform_lead_id`, usa a chave (affiliate_platform_id + platform_lead_id)
     *   para decidir entre update e create.
     * - Sem `platform_lead_id`, cria novo registro (não há como deduplicar com segurança).
     * - Sempre salva payload bruto recebido para auditoria/debug.
     * - Retorna contexto da operação para orquestrar a etapa seguinte (conversão).
     */
    /**
     * @param array{
     *   platform_lead_id:?string,
     *   status_raw:?string,
     *   status_mapped:string,
     *   payout_amount:string|float|int,
     *   currency_code:string,
     *   offer_id:?int,
     *   occurred_at:?Carbon,
     *   payload_json:array
     * } $attributes
     *
     * @return array{
     *   lead: Lead,
     *   operation: string,
     *   was_created: bool,
     *   previous_status: ?string,
     *   current_status: string,
     *   became_approved: bool
     * }
     */
    public function upsertFromNormalizedData(
        Campaign $campaign,
        Pageview $pageview,
        AffiliatePlatform $platform,
        array $attributes
    ): array {
        // Identificador externo do lead na plataforma.
        // Quando presente, é a base da idempotência.
        $platformLeadId = isset($attributes['platform_lead_id'])
            ? trim((string) $attributes['platform_lead_id'])
            : null;
        $platformLeadId = $platformLeadId === '' ? null : $platformLeadId;

        // Status bruto e canônico normalizados para armazenamento consistente.
        $statusRaw = isset($attributes['status_raw']) ? trim((string) $attributes['status_raw']) : null;
        $statusRaw = $statusRaw === '' ? null : strtolower($statusRaw);
        $statusMapped = strtolower(trim((string) ($attributes['status_mapped'] ?? Lead::STATUS_PROCESSING)));

        // Payload final que será persistido no lead.
        // Contém as chaves de negócio essenciais para rastreabilidade.
        $data = [
            'user_id' => (int) $campaign->user_id,
            'campaign_id' => (int) $campaign->id,
            'pageview_id' => (int) $pageview->id,
            'affiliate_platform_id' => (int) $platform->id,
            'platform_lead_id' => $platformLeadId,
            'lead_status' => $statusMapped,
            'status_raw' => $statusRaw,
            'payout_amount' => $attributes['payout_amount'] ?? '0.00',
            'currency_code' => strtoupper(trim((string) ($attributes['currency_code'] ?? 'USD'))),
            'offer_id' => $attributes['offer_id'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? null,
            'payload_json' => $attributes['payload_json'] ?? [],
        ];

        if ($platformLeadId !== null) {
            // Fluxo idempotente: tenta localizar lead existente da mesma plataforma.
            $existing = Lead::query()
                ->where('affiliate_platform_id', (int) $platform->id)
                ->where('platform_lead_id', $platformLeadId)
                ->first();

            // Necessário para detectar transição de status (ex.: processing -> approved).
            $previousStatus = $existing?->lead_status;

            if ($existing) {
                // Update do mesmo lead externo: preserva unicidade e histórico de status.
                $existing->fill($data);
                $existing->save();

                $lead = $existing->fresh();
                $operation = 'updated';
                $wasCreated = false;
            } else {
                // Primeiro recebimento desse lead externo.
                $lead = Lead::query()->create($data);
                $operation = 'created';
                $wasCreated = true;
            }
        } else {
            // Sem ID externo não há deduplicação confiável.
            // Mantemos comportamento explícito criando novo registro.
            $lead = Lead::query()->create($data);
            $previousStatus = null;
            $operation = 'created_without_external_id';
            $wasCreated = true;
        }

        // Flag útil para camadas acima (service/controller):
        // indica se houve transição para approved nesta operação.
        $currentStatus = (string) $lead->lead_status;
        $becameApproved = $currentStatus === Lead::STATUS_APPROVED
            && $previousStatus !== Lead::STATUS_APPROVED;

        return [
            'lead' => $lead,
            'operation' => $operation,
            'was_created' => $wasCreated,
            'previous_status' => $previousStatus,
            'current_status' => $currentStatus,
            'became_approved' => $becameApproved,
        ];
    }
}
