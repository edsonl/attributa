<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampaignStatus extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color_hex',
        'description',
        'is_system',
        'active',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'active' => 'boolean',
    ];

    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }
}

