<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'corporate_name',
        'phone',
        'whatsapp',
        'email',
        'cnpj',
        'site',
        'notes',
        'company_id'
    ];
}
