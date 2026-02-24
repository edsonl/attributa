<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'leads';

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_TRASH = 'trash';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CHARGEBACK = 'chargeback';

    public const ALLOWED_STATUSES = [
        self::STATUS_PROCESSING,
        self::STATUS_REJECTED,
        self::STATUS_TRASH,
        self::STATUS_APPROVED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
        self::STATUS_CHARGEBACK,
    ];

    public const LABEL_BY_STATUS = [
        self::STATUS_PROCESSING => 'Processando',
        self::STATUS_REJECTED => 'Rejeitado',
        self::STATUS_TRASH => 'Lixo',
        self::STATUS_APPROVED => 'Aprovado',
        self::STATUS_CANCELLED => 'Cancelado',
        self::STATUS_REFUNDED => 'Reembolsado',
        self::STATUS_CHARGEBACK => 'Chargeback',
    ];

    public const COLOR_BY_STATUS = [
        self::STATUS_PROCESSING => 'warning',
        self::STATUS_REJECTED => 'negative',
        self::STATUS_TRASH => 'grey-6',
        self::STATUS_APPROVED => 'positive',
        self::STATUS_CANCELLED => 'orange',
        self::STATUS_REFUNDED => 'deep-orange',
        self::STATUS_CHARGEBACK => 'brown',
    ];

    protected $fillable = [
        'user_id',
        'campaign_id',
        'pageview_id',
        'affiliate_platform_id',
        'platform_lead_id',
        'lead_status',
        'status_raw',
        'payout_amount',
        'currency_code',
        'offer_id',
        'occurred_at',
        'payload_json',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'campaign_id' => 'integer',
        'pageview_id' => 'integer',
        'affiliate_platform_id' => 'integer',
        'payout_amount' => 'decimal:2',
        'offer_id' => 'integer',
        'occurred_at' => 'datetime',
        'payload_json' => 'array',
    ];

    protected $attributes = [
        'lead_status' => self::STATUS_PROCESSING,
        'payout_amount' => 0,
        'currency_code' => 'USD',
    ];

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function pageview()
    {
        return $this->belongsTo(Pageview::class);
    }

    public function affiliatePlatform()
    {
        return $this->belongsTo(AffiliatePlatform::class);
    }

    public static function normalizeStatus(?string $status): string
    {
        $normalized = strtolower(trim((string) $status));
        return in_array($normalized, self::ALLOWED_STATUSES, true)
            ? $normalized
            : self::STATUS_PROCESSING;
    }

    public static function statusLabel(?string $status): string
    {
        $normalized = self::normalizeStatus($status);
        return self::LABEL_BY_STATUS[$normalized] ?? 'Processando';
    }

    public static function statusColor(?string $status): string
    {
        $normalized = self::normalizeStatus($status);
        return self::COLOR_BY_STATUS[$normalized] ?? 'warning';
    }
}
