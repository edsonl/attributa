<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AffiliatePlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'active',
        'integration_type',
        'tracking_param_mapping',
        'lead_param_mapping',
        'lead_status_mapping',
        'postback_additional_params',
    ];

    protected $casts = [
        'active' => 'boolean',
        'tracking_param_mapping' => 'array',
        'lead_param_mapping' => 'array',
        'lead_status_mapping' => 'array',
        'postback_additional_params' => 'array',
    ];

    /**
     * Campanhas vinculadas ao canal
     */
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}
