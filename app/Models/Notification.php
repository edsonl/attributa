<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasFactory;

    public const STATUS_UNREAD = 'unread';
    public const STATUS_READ = 'read';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id',
        'notification_type_id',
        'title',
        'message',
        'payload_json',
        'source_type',
        'source_id',
        'status',
        'read_at',
        'sent_at',
        'sent_email_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'read_at' => 'datetime',
        'sent_at' => 'datetime',
        'sent_email_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function type()
    {
        return $this->belongsTo(NotificationType::class, 'notification_type_id');
    }
}
