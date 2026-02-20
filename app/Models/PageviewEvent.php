<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PageviewEvent extends Model
{
    use HasFactory;

    protected $table = 'pageview_events';

    protected $fillable = [
        'user_id',
        'campaign_id',
        'pageview_id',
        'event_type',
        'target_url',
        'element_id',
        'element_name',
        'element_classes',
        'form_fields_checked',
        'form_fields_filled',
        'form_has_user_data',
        'event_ts_ms',
    ];

    protected $casts = [
        'form_fields_checked' => 'integer',
        'form_fields_filled' => 'integer',
        'form_has_user_data' => 'boolean',
        'event_ts_ms' => 'integer',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pageview()
    {
        return $this->belongsTo(Pageview::class);
    }
}
