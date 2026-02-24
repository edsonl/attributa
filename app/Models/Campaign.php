<?php

namespace App\Models;

use App\Services\HashidService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Campaign extends Model
{
    use HasFactory;
    use HasHashid;

    protected $fillable = [
        'user_id',
        'campaign_status_id',
        'name',
        'product_url',
        'conversion_goal_id',
        'commission_value',
        'channel_id',
        'affiliate_platform_id',
        'external_campaign_id',
        'google_ads_account_id'
    ];

    protected $appends = [
        'hashid',
    ];

    protected $casts = [
        'commission_value' => 'decimal:2',
    ];

    /**
     * Canal da campanha
     */
    public function channel()
    {
        return $this->belongsTo(Channel::class);
    }

    /**
     * Plataforma (Affiliado) da campanha
     */
    public function affiliatePlatform()
    {
        return $this->belongsTo(AffiliatePlatform::class);
    }

    public function conversionGoal()
    {
        return $this->belongsTo(ConversionGoal::class);
    }

    public function campaignStatus()
    {
        return $this->belongsTo(CampaignStatus::class);
    }

    /**
     * Países associados à campanha
     */
    public function countries()
    {
        return $this->belongsToMany(
            Country::class,
            'campaign_country',
            'campaign_id',
            'country_id'
        );
    }

    public function pageviews(): HasMany
    {
        return $this->hasMany(Pageview::class);
    }

    public function visitors(): HasMany
    {
        return $this->hasMany(CampaignVisitor::class);
    }

    protected static function booted()
    {
        static::creating(function (Campaign $campaign) {
            if (!empty($campaign->code)) {
                return;
            }

            // Código temporário curto para satisfazer NOT NULL + UNIQUE no insert inicial.
            $campaign->code = 'TMP' . strtoupper(Str::random(17));
        });

        static::created(function (Campaign $campaign) {
            $finalCode = app(HashidService::class)->encode((int) $campaign->id);
            if ((string) $campaign->code === $finalCode) {
                return;
            }

            $campaign->code = $finalCode;
            $campaign->saveQuietly();
        });
    }

    public static function normalizeProductUrl(?string $value): ?string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return null;
        }

        $parts = parse_url($raw);
        if ($parts === false) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return null;
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return sprintf('%s://%s%s', $scheme, $host, $port);
    }

    public function setProductUrlAttribute($value): void
    {
        $raw = trim((string) $value);
        $this->attributes['product_url'] = $raw === '' ? null : $raw;
    }

    protected function resolveHashidRouteBindingQuery(Builder $query, int $id): Builder
    {
        return $query
            ->whereKey($id)
            ->where('user_id', (int) auth()->id());
    }

}
