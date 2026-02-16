<?php

namespace App\Models;

use App\Services\GenerateCampaignCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasHashid;
use Illuminate\Database\Eloquent\Builder;

class Campaign extends Model
{
    use HasFactory;
    use HasHashid;

    protected $fillable = [
        'user_id',
        'name',
        'product_url',
        'status',
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
        'status' => 'boolean',
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

    protected static function booted()
    {
        static::creating(function (Campaign $campaign) {
            if ($campaign->code) {
                return;
            }

            $channelCode = self::resolveChannelCode(optional($campaign->channel)->name ?? null);

            $campaign->code = app(GenerateCampaignCode::class)
                ->generate($channelCode);
        });

    }

    protected static function resolveChannelCode(?string $rawChannelName): string
    {
        $normalized = trim((string) preg_replace('/[^A-Za-z]+/', ' ', (string) $rawChannelName));
        if ($normalized === '') {
            return 'XX';
        }

        $words = preg_split('/\s+/', $normalized) ?: [];
        if (count($words) >= 2) {
            $first = strtoupper(substr((string) $words[0], 0, 1));
            $second = strtoupper(substr((string) $words[1], 0, 1));
            $code = $first . $second;
        } else {
            $word = strtoupper((string) ($words[0] ?? ''));
            $code = substr($word, 0, 2);
        }

        $code = preg_replace('/[^A-Z]/', '', (string) $code) ?? '';
        if (strlen($code) < 2) {
            $code = str_pad($code, 2, 'X');
        }

        return substr($code, 0, 2);
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
