<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\IpCategory;

class Pageview extends Model
{
    protected $fillable = [

        // Originais
        'campaign_code',
        'url',
        'referrer',
        'user_agent',
        'ip',
        'timestamp_ms',
        'gclid',
        'gad_campaignid',
        'conversion',

        // Classificação IP
        'ip_category_id',

        // Geolocalização
        'country_code',
        'country_name',
        'region_name',
        'city',
        'latitude',
        'longitude',
        'timezone',
    ];

    protected $casts = [
        'timestamp_ms' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'conversion' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function ipCategory()
    {
        return $this->belongsTo(IpCategory::class);
    }
}
