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
        'nome',
        'currency',
        'currency_symbol',
        'timezone_default',
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
