<?php

namespace App\Models;

use App\Services\GenerateCampaignCode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Campaign extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'status',
        'commission_value',
        'channel_id',
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

            // Extrai somente letras do nome do canal
            $rawChannelName = optional($campaign->channel)->name ?? '';

            $lettersOnly = strtoupper(
                preg_replace('/[^A-Z]/i', '', $rawChannelName)
            );

            // Garante sempre 2 letras
            $channelCode = substr($lettersOnly, 0, 2);

            if (strlen($channelCode) < 2) {
                $channelCode = 'XX';
            }

            $campaign->code = app(GenerateCampaignCode::class)
                ->generate($channelCode);
        });

    }
}
