<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Carbon;

class AdsConversion extends Model
{
    use HasFactory;

    protected $table = 'ads_conversions';

    /**
     * Campos que podem ser atribuídos em massa
     */
    protected $fillable = [
        'user_id',
        'campaign_id',
        'pageview_id',
        'gclid',
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
    ];

    /**
     * Valores padrão
     */
    protected $attributes = [
        'conversion_value'      => 1.00,
        'currency_code'         => 'USD',
        'google_upload_status'  => 'pending',
    ];

    /* =====================================================
     | Relationships
     ===================================================== */

    public function campaign()
    {
        return $this->belongsTo(Campaign::class);
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
            ->where('google_upload_status', 'pending')
            ->whereNotNull('gclid');
    }

    /**
     * Conversões enviadas com sucesso
     */
    public function scopeGoogleUploaded($query)
    {
        return $query->where('google_upload_status', 'success');
    }

    /**
     * Conversões que falharam no envio
     */
    public function scopeGoogleUploadError($query)
    {
        return $query->where('google_upload_status', 'error');
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
            'google_upload_status' => 'success',
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
            'google_upload_status' => 'error',
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
        return $this->google_upload_status === 'pending'
            && !empty($this->gclid)
            && !empty($this->conversion_event_time);
    }
}
