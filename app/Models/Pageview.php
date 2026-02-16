<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\IpCategory;
use App\Models\Campaign;
use App\Models\TrafficSourceCategory;
use App\Models\DeviceCategory;
use App\Models\Browser;

class Pageview extends Model
{
    protected $fillable = [
        'user_id',

        // Originais
        'campaign_id',
        'campaign_code',
        'url',
        'landing_url',
        'referrer',
        'user_agent',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'ip',
        'timestamp_ms',
        'gclid',
        'gad_campaignid',
        'fbclid',
        'ttclid',
        'msclkid',
        'wbraid',
        'gbraid',
        'conversion',

        // Classificação IP
        'ip_category_id',
        'traffic_source_category_id',
        'traffic_source_reason',
        'device_category_id',
        'browser_id',
        'device_type',
        'device_brand',
        'device_model',
        'os_name',
        'os_version',
        'browser_name',
        'browser_version',
        'screen_width',
        'screen_height',
        'viewport_width',
        'viewport_height',
        'device_pixel_ratio',
        'platform',
        'language',

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
        'screen_width' => 'integer',
        'screen_height' => 'integer',
        'viewport_width' => 'integer',
        'viewport_height' => 'integer',
        'device_pixel_ratio' => 'float',
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


    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function trafficSourceCategory()
    {
        return $this->belongsTo(TrafficSourceCategory::class);
    }

    public function deviceCategory()
    {
        return $this->belongsTo(DeviceCategory::class);
    }

    public function browser()
    {
        return $this->belongsTo(Browser::class);
    }

}
