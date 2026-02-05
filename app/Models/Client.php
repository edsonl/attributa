<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;


class Client extends Model
{
    use HasFactory;


    protected $fillable = [
        'name', 'website', 'image_path', 'image_meta', 'order', 'visible',
    ];


    protected $casts = [
        'visible'    => 'boolean',
        'order'      => 'integer',
        'image_meta' => 'array',
    ];


    public function getImageUrlAttribute(): ?string
    {
        return $this->image_path ? asset('uploads/' . $this->image_path) : null;
    }
}
