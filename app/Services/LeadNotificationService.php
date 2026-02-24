<?php

namespace App\Services;

use App\Models\Lead;

class LeadNotificationService
{
    public function __construct(
        protected NotificationDispatchService $notificationDispatchService
    ) {
    }

    /**
     * Decide se deve notificar no fluxo de ingestão de lead e dispara via dispatcher genérico.
     *
     * Regra de emissão:
     * - Em criação: notifica o status inicial do lead.
     * - Em update: notifica apenas quando houve mudança de status.
     */
    public function notifyFromIngestion(Lead $lead, string $operation, ?string $previousStatus): void
    {
        $currentStatus = strtolower(trim((string) $lead->lead_status));
        $normalizedPrevious = strtolower(trim((string) $previousStatus));
        $isCreatedOperation = str_starts_with($operation, 'created');
        $statusChanged = $normalizedPrevious !== '' && $normalizedPrevious !== $currentStatus;

        if (!$isCreatedOperation && !$statusChanged) {
            return;
        }

        $typeSlug = $this->typeSlugFromLeadStatus($currentStatus);
        if (!$typeSlug) {
            return;
        }

        $payload = [
            'lead_id' => (int) $lead->id,
            'lead_status_label' => Lead::statusLabel($currentStatus),
            'campaign_id' => (int) $lead->campaign_id,
            'campaign_name' => (string) optional($lead->campaign)->name,
            'campaign_code' => (string) optional($lead->campaign)->code,
            'platform_id' => (int) $lead->affiliate_platform_id,
            'pageview_id' => (int) $lead->pageview_id,
            'platform_lead_id' => (string) ($lead->platform_lead_id ?? ''),
            'status' => $currentStatus,
            'previous_status' => $normalizedPrevious !== '' ? $normalizedPrevious : null,
            'payout_amount' => (string) $lead->payout_amount,
            'currency_code' => (string) $lead->currency_code,
            'offer_id' => $lead->offer_id,
        ];

        $this->notificationDispatchService->dispatchByTypeSlug(
            userId: (int) $lead->user_id,
            typeSlug: $typeSlug,
            payload: $payload,
            sourceType: 'lead',
            sourceId: (int) $lead->id
        );
    }

    protected function typeSlugFromLeadStatus(string $status): ?string
    {
        return match ($status) {
            Lead::STATUS_PROCESSING => 'lead_created_processing',
            Lead::STATUS_APPROVED => 'lead_approved',
            Lead::STATUS_REJECTED => 'lead_rejected',
            Lead::STATUS_TRASH => 'lead_trash',
            Lead::STATUS_CANCELLED => 'lead_cancelled',
            Lead::STATUS_REFUNDED => 'lead_refunded',
            Lead::STATUS_CHARGEBACK => 'lead_chargeback',
            default => null,
        };
    }
}
