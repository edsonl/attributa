<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IpLookupCache extends Model
{
    protected $table = 'ip_lookup_cache';

    protected $fillable = [
        'ip',
        'ip_category_id',

        'is_proxy',
        'is_vpn',
        'is_tor',
        'is_datacenter',
        'is_bot',
        'fraud_score',

        'country_code',
        'country_name',
        'region_name',
        'city',
        'latitude',
        'longitude',
        'timezone',

        'isp',
        'organization',

        'api_response',
        'last_checked_at',
    ];

    protected $casts = [
        'is_proxy' => 'boolean',
        'is_vpn' => 'boolean',
        'is_tor' => 'boolean',
        'is_datacenter' => 'boolean',
        'is_bot' => 'boolean',
        'fraud_score' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'api_response' => 'array',
        'last_checked_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function ipCategory(): BelongsTo
    {
        return $this->belongsTo(IpCategory::class);
    }
}
