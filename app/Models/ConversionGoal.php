<?php

namespace App\Models;

use App\Traits\HasHashid;
use Hashids\Hashids;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConversionGoal extends Model
{
    use HasFactory;
    use HasHashid;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'user_slug_id',
        'googleads_password',
        'timezone_id',
        'goal_code',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    protected $appends = [
        'hashid',
    ];

    public function campaigns()
    {
        return $this->hasMany(Campaign::class);
    }

    public function timezone()
    {
        return $this->belongsTo(Timezone::class);
    }

    public function logs()
    {
        return $this->hasMany(ConversionGoalLog::class, 'goal_id');
    }

    protected static function booted(): void
    {
        static::saving(function (ConversionGoal $conversionGoal) {
            if (!$conversionGoal->user_id) {
                return;
            }

            if (!$conversionGoal->isDirty('user_id') && !empty($conversionGoal->user_slug_id)) {
                return;
            }

            $hashids = new Hashids(
                (string) config('app.hashids.salt', 'attributa-2026'),
                (int) config('app.hashids.min_length', 6)
            );

            $conversionGoal->user_slug_id = $hashids->encode((int) $conversionGoal->user_id);
        });

        static::creating(function (ConversionGoal $conversionGoal) {
            if (empty($conversionGoal->googleads_password)) {
                $conversionGoal->googleads_password = Str::random(25);
            }
        });

        static::saving(function (ConversionGoal $conversionGoal) {
            if (empty($conversionGoal->googleads_password)) {
                $conversionGoal->googleads_password = Str::random(25);
            }
        });
    }

    public function setGoalCodeAttribute($value): void
    {
        $this->attributes['goal_code'] = trim((string) $value);
    }

    protected function resolveHashidRouteBindingQuery(Builder $query, int $id): Builder
    {
        return $query
            ->whereKey($id)
            ->where('user_id', (int) auth()->id());
    }
}
