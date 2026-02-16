<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TrafficSourceCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'icon_name',
        'color_hex',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function pageviews(): HasMany
    {
        return $this->hasMany(Pageview::class);
    }
}
