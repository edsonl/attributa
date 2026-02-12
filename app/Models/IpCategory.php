<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IpCategory extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'color_hex',
        'description',
        'is_system',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * Pageviews que pertencem a esta categoria de IP
     */
    public function pageviews(): HasMany
    {
        return $this->hasMany(Pageview::class);
    }
}
