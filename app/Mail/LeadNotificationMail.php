<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LeadNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Notification $notification
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject((string) $this->notification->title)
            ->view('email.lead-notification')
            ->with([
                'title' => (string) $this->notification->title,
                'messageText' => (string) $this->notification->message,
                'createdAt' => optional($this->notification->created_at)?->format('d/m/Y H:i'),
                'payload' => is_array($this->notification->payload_json) ? $this->notification->payload_json : [],
            ]);
    }
}

