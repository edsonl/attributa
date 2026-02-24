<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\UserNotificationPreference;

class NotificationDispatchService
{
    /**
     * Dispara notificação in-app para um usuário a partir do slug do tipo.
     *
     * Regras:
     * - Só envia se o tipo existir e estiver ativo.
     * - Respeita preferência explícita do usuário (enabled_in_app=false bloqueia).
     * - Sem preferência cadastrada, aplica fallback permissivo (envia).
     */
    public function dispatchByTypeSlug(
        int $userId,
        string $typeSlug,
        array $payload = [],
        ?string $sourceType = null,
        ?int $sourceId = null,
        ?string $title = null,
        ?string $message = null
    ): ?Notification {
        $slug = strtolower(trim($typeSlug));
        if ($userId <= 0 || $slug === '') {
            return null;
        }

        $type = NotificationType::query()
            ->where('slug', $slug)
            ->where('active', true)
            ->first();

        if (!$type) {
            return null;
        }

        $pref = UserNotificationPreference::query()
            ->where('user_id', $userId)
            ->where('notification_type_id', (int) $type->id)
            ->first();

        if ($pref && !$pref->enabled_in_app) {
            return null;
        }

        $resolvedTitle = $title ?? $this->renderTemplate(
            (string) ($type->default_title ?: $type->name),
            $payload
        );
        $resolvedMessage = $message ?? $this->renderTemplate(
            (string) ($type->default_message ?: $type->name),
            $payload
        );

        return Notification::query()->create([
            'user_id' => $userId,
            'notification_type_id' => (int) $type->id,
            'title' => $resolvedTitle,
            'message' => $resolvedMessage,
            'payload_json' => $payload ?: null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => Notification::STATUS_UNREAD,
            'sent_at' => now(),
        ]);
    }

    protected function renderTemplate(string $template, array $payload): string
    {
        if ($template === '') {
            return '';
        }

        $replacements = [];
        foreach ($payload as $key => $value) {
            $token = '{{' . $key . '}}';
            if (is_scalar($value) || $value === null) {
                $replacements[$token] = (string) ($value ?? '');
            }
        }

        return strtr($template, $replacements);
    }
}

