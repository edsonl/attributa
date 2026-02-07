<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AffiliatePlatform extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'id'
    ];

    /**
     * Campanhas vinculadas ao canal
     */
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}
