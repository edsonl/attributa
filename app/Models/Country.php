<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Country extends Model
{
    use HasFactory;

    protected $fillable = [
        'iso2',
        'iso3',
        'name',
        'currency',
        'currency_symbol',
        'timezone_default',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    /**
     * Campanhas vinculadas ao país
     */
    public function campaigns()
    {
        return $this->belongsToMany(
            Campaign::class,
            'campaign_country',
            'country_id',
            'campaign_id'
        );
    }
}
