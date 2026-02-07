<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdsConversion extends Model
{
    protected $table = 'ads_conversions';

    protected $fillable = [
        'conversion_event_time',
        'gclid',
        'conversion_name',
        'campaign_id',
        'pageview_id',
        'conversion_value',
        'currency_code',
    ];

    protected $casts = [
        'conversion_event_time' => 'datetime',
        'conversion_value'      => 'decimal:2',
    ];
}
