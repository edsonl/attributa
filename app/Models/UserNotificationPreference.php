<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserNotificationPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'notification_type_id',
        'enabled_in_app',
        'enabled_email',
        'enabled_push',
        'frequency',
    ];

    protected $casts = [
        'enabled_in_app' => 'boolean',
        'enabled_email' => 'boolean',
        'enabled_push' => 'boolean',
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

