<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class GoogleAdsAccount extends Model
{
    use HasFactory;

    protected $table = 'google_ads_accounts';

    protected $fillable = [
        'user_id',
        'google_ads_customer_id',
        'email',
        'refresh_token',
        'access_token',
        'token_expires_at',
        'active',
    ];

    protected $casts = [
        'token_expires_at' => 'datetime',
        'active'           => 'boolean',
    ];

    /* =====================================================
     | Relationships
     ===================================================== */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    /* =====================================================
     | Helpers
     ===================================================== */

    /**
     * Verifica se o access token expirou
     */
    public function isTokenExpired(): bool
    {
        if (!$this->token_expires_at) {
            return true;
        }

        return now()->gte($this->token_expires_at);
    }

    /**
     * Retorna o customer_id no formato limpo (sem hífen)
     * Necessário para Google Ads API
     */
    public function getCustomerIdForApi(): string
    {
        return str_replace('-', '', $this->google_ads_customer_id);
    }
}
