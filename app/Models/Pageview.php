<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Pageview extends Model
{
    protected $fillable = [
        'campaign_code',
        'url',
        'referrer',
        'user_agent',
        'ip',
        'timestamp_ms',
        'gclid',
        'gad_campaignid',
        'conversion'
    ];

    protected $casts = [
        'timestamp_ms' => 'integer',
    ];
}
