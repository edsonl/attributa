<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NotificationType extends Model
{
    use HasFactory;

    public const SEVERITIES = ['info', 'success', 'warning', 'error'];

    protected $fillable = [
        'notification_category_id',
        'name',
        'slug',
        'description',
        'default_title',
        'default_message',
        'severity',
        'active',
        'sort_order',
    ];

    protected $casts = [
        'active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function category()
    {
        return $this->belongsTo(NotificationCategory::class, 'notification_category_id');
    }

    public function userPreferences()
    {
        return $this->hasMany(UserNotificationPreference::class);
    }
}

