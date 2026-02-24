<?php

namespace Database\Seeders;

use App\Models\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Database\Seeder;

class DevUserNotificationPreferencesSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            return;
        }

        $user = User::query()->where('email', 'edson@master.dev.br')->first();
        if (!$user) {
            return;
        }

        $types = NotificationType::query()
            ->where('active', true)
            ->get(['id', 'slug']);

        if ($types->isEmpty()) {
            return;
        }

        $emailEnabledSlugs = [
            'billing_plan_expiring',
            'billing_payment_failed',
            'billing_plan_renewed',
            'security_new_login',
            'security_password_changed',
            'security_suspicious_access',
            'lead_approved',
            'lead_chargeback',
            'conversion_export_failed',
        ];
        $emailEnabledSet = array_fill_keys($emailEnabledSlugs, true);

        $activeTypeIds = $types->pluck('id')->map(fn ($id) => (int) $id)->all();

        UserNotificationPreference::query()
            ->where('user_id', $user->id)
            ->whereNotIn('notification_type_id', $activeTypeIds)
            ->delete();

        foreach ($types as $type) {
            UserNotificationPreference::query()->updateOrCreate(
                [
                    'user_id' => $user->id,
                    'notification_type_id' => (int) $type->id,
                ],
                [
                    'enabled_in_app' => true,
                    'enabled_email' => isset($emailEnabledSet[$type->slug]),
                    'enabled_push' => false,
                    'frequency' => null,
                ]
            );
        }
    }
}

