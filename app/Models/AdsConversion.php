<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Carbon;

class AdsConversion extends Model
{
    use HasFactory;

    protected $table = 'ads_conversions';

    public const STATUS_PENDING = 0;
    public const STATUS_PROCESSING = 1;
    public const STATUS_PROCESSING_EXPORT = 2;
    public const STATUS_SUCCESS = 3;
    public const STATUS_EXPORTED = 4;
    public const STATUS_ERROR = 5;

    public const STATUS_BY_LABEL = [
        'pending' => self::STATUS_PENDING,
        'processing' => self::STATUS_PROCESSING,
        'processing_export' => self::STATUS_PROCESSING_EXPORT,
        'success' => self::STATUS_SUCCESS,
        'exported' => self::STATUS_EXPORTED,
        'error' => self::STATUS_ERROR,
    ];

    public const LABEL_BY_STATUS = [
        self::STATUS_PENDING => 'pending',
        self::STATUS_PROCESSING => 'processing',
        self::STATUS_PROCESSING_EXPORT => 'processing_export',
        self::STATUS_SUCCESS => 'success',
        self::STATUS_EXPORTED => 'exported',
        self::STATUS_ERROR => 'error',
    ];

    public const DISPLAY_LABEL_BY_SLUG = [
        'pending' => 'Pendente',
        'processing' => 'Processando',
        'processing_export' => 'Processando',
        'success' => 'Sucesso',
        'exported' => 'Exportado',
        'error' => 'Erro',
    ];

    /**
     * Campos que podem ser atribuídos em massa
     */
    protected $fillable = [
        'user_id',
        'created_by',
        'is_manual',
        'campaign_id',
        'lead_id',
        'pageview_id',
        'gclid',
        'gbraid',
        'wbraid',
        'user_agent',
        'ip_address',
        'conversion_name',
        'conversion_value',
        'currency_code',
        'conversion_event_time',
        'google_upload_status',
        'google_uploaded_at',
        'google_upload_error'
    ];

    /**
     * Casts de tipos
     */
    protected $casts = [
        'conversion_event_time' => 'datetime',
        'google_uploaded_at'    => 'datetime',
        'conversion_value'      => 'decimal:2',
        'google_upload_status'  => 'integer',
        'is_manual'             => 'boolean',
    ];

    /**
     * Valores padrão
     */
    protected $attributes = [
        'conversion_value'      => 1.00,
        'currency_code'         => 'USD',
        'google_upload_status'  => self::STATUS_PENDING,
        'is_manual'             => false,
    ];

    protected function googleUploadStatus(): Attribute
    {
        return Attribute::make(
            get: fn ($value) => self::googleUploadStatusLabel($value),
            set: fn ($value) => self::normalizeGoogleUploadStatus($value),
        );
    }

    public static function normalizeGoogleUploadStatus(int|string|null $status): int
    {
        if (is_int($status)) {
            return array_key_exists($status, self::LABEL_BY_STATUS)
                ? $status
                : self::STATUS_PENDING;
        }

        if (is_string($status)) {
            $normalized = strtolower(trim($status));

            if (array_key_exists($normalized, self::STATUS_BY_LABEL)) {
                return self::STATUS_BY_LABEL[$normalized];
            }

            if (is_numeric($normalized)) {
                $numeric = (int) $normalized;
                return array_key_exists($numeric, self::LABEL_BY_STATUS)
                    ? $numeric
                    : self::STATUS_PENDING;
            }
        }

        return self::STATUS_PENDING;
    }

    public static function googleUploadStatusLabel(int|string|null $status): string
    {
        $normalized = self::normalizeGoogleUploadStatus($status);
        return self::LABEL_BY_STATUS[$normalized] ?? 'pending';
    }

    public static function googleUploadStatusDisplayLabel(int|string|null $status): string
    {
        $slug = self::googleUploadStatusLabel($status);
        return self::DISPLAY_LABEL_BY_SLUG[$slug] ?? ucfirst($slug);
    }

    /* =====================================================
     | Relationships
     ===================================================== */

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function pageview()
    {
        return $this->belongsTo(Pageview::class);
    }

    /* =====================================================
     | Scopes
     ===================================================== */

    /**
     * Conversões prontas para envio ao Google Ads
     */
    public function scopePendingGoogleUpload($query)
    {
        return $query
            ->where('google_upload_status', self::STATUS_PENDING)
            ->whereNotNull('gclid');
    }

    /**
     * Conversões enviadas com sucesso
     */
    public function scopeGoogleUploaded($query)
    {
        return $query->where('google_upload_status', self::STATUS_SUCCESS);
    }

    /**
     * Conversões que falharam no envio
     */
    public function scopeGoogleUploadError($query)
    {
        return $query->where('google_upload_status', self::STATUS_ERROR);
    }

    /* =====================================================
     | Helpers / Domain logic
     ===================================================== */

    /**
     * Marca a conversão como enviada com sucesso
     */
    public function markAsUploaded(): void
    {
        $this->update([
            'google_upload_status' => self::STATUS_SUCCESS,
            'google_uploaded_at'   => now(),
            'google_upload_error'  => null,
        ]);
    }

    /**
     * Marca erro no envio para o Google
     */
    public function markAsUploadError(string $error): void
    {
        $this->update([
            'google_upload_status' => self::STATUS_ERROR,
            'google_upload_error'  => $error,
        ]);
    }

    /**
     * Retorna a data da conversão no formato exigido pelo Google Ads
     * Ex: 2026-02-09 12:41:26-03:00
     */
    public function getGoogleConversionDateTime(): string
    {
        return Carbon::parse($this->conversion_event_time)
            ->format('Y-m-d H:i:sP');
    }

    /**
     * Indica se esta conversão pode ser enviada ao Google
     */
    public function canBeUploadedToGoogle(): bool
    {
        return self::normalizeGoogleUploadStatus($this->getRawOriginal('google_upload_status')) === self::STATUS_PENDING
            && !empty($this->gclid)
            && !empty($this->conversion_event_time);
    }
}
