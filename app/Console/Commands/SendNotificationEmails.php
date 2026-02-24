<?php

namespace App\Console\Commands;

use App\Mail\LeadNotificationMail;
use App\Models\Notification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendNotificationEmails extends Command
{
    protected $signature = 'notifications:send-email {--limit=0 : Máximo de envios por execução (0 = sem limite)}';

    protected $description = 'Envia e-mails pendentes de notificações de lead';

    public function handle(): int
    {
        $log = Log::channel('notification_email');
        $optionLimit = (int) $this->option('limit');
        $defaultLimit = (int) env('NOTIFICATIONS_EMAIL_SEND_LIMIT', 100);
        $limit = $optionLimit > 0 ? $optionLimit : max($defaultLimit, 0);

        $query = Notification::query()
            ->with([
                'type:id,slug,name',
                'user:id,name,email,notification_email',
            ])
            ->whereNull('sent_email_at')
            ->whereNotNull('notification_type_id')
            ->whereHas('type', function ($q) {
                $q->where('active', true)->where('slug', 'like', 'lead_%');
            })
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('user_notification_preferences as up')
                    ->whereColumn('up.user_id', 'notifications.user_id')
                    ->whereColumn('up.notification_type_id', 'notifications.notification_type_id')
                    ->where('up.enabled_email', true);
            })
            ->orderBy('id');

        if ($limit > 0) {
            $query->limit($limit);
        }

        $pending = $query->get();

        if ($pending->isEmpty()) {
            $log->info('notification email dispatch: no pending rows', [
                'limit' => $limit,
            ]);
            $this->info('Nenhum e-mail pendente para envio.');
            return self::SUCCESS;
        }

        $log->info('notification email dispatch: start', [
            'pending' => $pending->count(),
            'limit' => $limit,
        ]);

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($pending as $notification) {
            $recipient = $this->resolveRecipientEmail($notification);
            if (!$recipient) {
                $skipped++;
                $log->warning('notification email dispatch: skipped (invalid recipient)', [
                    'notification_id' => (int) $notification->id,
                    'user_id' => (int) $notification->user_id,
                ]);
                $this->warn("Notificação #{$notification->id} sem destinatário válido. Ignorada.");
                continue;
            }

            try {
                Mail::to($recipient)->send(new LeadNotificationMail($notification));

                $notification->forceFill([
                    'sent_email_at' => now(),
                ])->save();

                $sent++;
                $log->info('notification email dispatch: sent', [
                    'notification_id' => (int) $notification->id,
                    'user_id' => (int) $notification->user_id,
                    'notification_type_id' => (int) $notification->notification_type_id,
                    'recipient' => $recipient,
                ]);
            } catch (\Throwable $e) {
                $failed++;
                report($e);
                $log->error('notification email dispatch: failed', [
                    'notification_id' => (int) $notification->id,
                    'user_id' => (int) $notification->user_id,
                    'notification_type_id' => (int) $notification->notification_type_id,
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Falha ao enviar notificação #{$notification->id}: {$e->getMessage()}");
            }
        }

        $log->info('notification email dispatch: finished', [
            'sent' => $sent,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);

        $this->info("Envio finalizado. Enviadas: {$sent} | Ignoradas: {$skipped} | Falhas: {$failed}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    protected function resolveRecipientEmail(Notification $notification): ?string
    {
        $user = $notification->user;
        if (!$user) {
            return null;
        }

        $candidate = trim((string) ($user->notification_email ?: $user->email));
        if ($candidate === '' || !filter_var($candidate, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $candidate;
    }
}
