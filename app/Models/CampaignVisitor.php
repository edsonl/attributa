<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CampaignVisitor extends Model
{
    protected $fillable = [
        'campaign_id',
        'visitor_id',
        'first_seen_at',
        'last_seen_at',
        'hits',
    ];

    protected $casts = [
        'visitor_id' => 'integer',
        'hits' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}
