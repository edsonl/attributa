<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Channel extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * Campanhas vinculadas ao canal
     */
    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }
}
