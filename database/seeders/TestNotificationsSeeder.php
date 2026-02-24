<?php

namespace Database\Seeders;

use App\Models\Notification;
use App\Models\NotificationType;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestNotificationsSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $user = User::query()->where('email', 'edson@master.dev.br')->first();
        if (! $user) {
            $this->command?->warn('TestNotificationsSeeder: usuário dev não encontrado.');
            return;
        }

        $slugs = [
            'lead_created_processing',
            'lead_approved',
            'lead_rejected',
            'lead_trash',
            'lead_cancelled',
            'lead_refunded',
            'lead_chargeback',
            'billing_plan_expiring',
            'billing_payment_failed',
            'conversion_export_failed',
            'security_new_login',
            'integration_callback_invalid',
        ];

        $types = NotificationType::query()
            ->whereIn('slug', $slugs)
            ->where('active', true)
            ->get()
            ->keyBy('slug');

        if ($types->isEmpty()) {
            $this->command?->warn('TestNotificationsSeeder: tipos de notificação não encontrados.');
            return;
        }

        Notification::query()
            ->where('user_id', (int) $user->id)
            ->where('source_type', 'seed_test')
            ->delete();

        $now = now();
        $rows = [];
        $leadEmailEnabledSlugs = ['lead_approved', 'lead_chargeback'];

        for ($i = 1; $i <= 20; $i++) {
            $slug = $slugs[($i - 1) % count($slugs)];
            $type = $types->get($slug);
            if (! $type) {
                continue;
            }

            $createdAt = $now->copy()->subMinutes(($i - 1) * 7);
            $isRead = ($i % 3) === 0;
            $isLeadEmailPending = in_array($slug, $leadEmailEnabledSlugs, true);

            $rows[] = [
                'user_id' => (int) $user->id,
                'notification_type_id' => (int) $type->id,
                'title' => sprintf('[Seed %02d] %s', $i, $type->default_title ?: $type->name),
                'message' => sprintf(
                    'Notificação de teste %02d para o tipo %s. Lead #%d, offer #%d.',
                    $i,
                    $type->slug,
                    1000 + $i,
                    28000 + $i
                ),
                'payload_json' => json_encode([
                    'seed' => true,
                    'seed_index' => $i,
                    'type_slug' => $type->slug,
                    'lead_id' => 1000 + $i,
                    'offer_id' => 28000 + $i,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'source_type' => 'seed_test',
                'source_id' => $i,
                'status' => $isRead ? Notification::STATUS_READ : Notification::STATUS_UNREAD,
                'read_at' => $isRead ? $createdAt->copy()->addMinutes(2) : null,
                'sent_at' => $createdAt,
                // Mantém algumas notificações de lead pendentes para teste do comando notifications:send-email.
                'sent_email_at' => $isLeadEmailPending ? null : $createdAt->copy()->addMinute(),
                'created_at' => $createdAt,
                'updated_at' => $createdAt,
            ];
        }

        if (! empty($rows)) {
            Notification::query()->insert($rows);
        }
    }
}
