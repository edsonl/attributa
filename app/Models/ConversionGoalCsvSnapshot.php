<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionGoalCsvSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id',
        'rows_count',
        'snapshot_json',
    ];

    protected $casts = [
        'rows_count' => 'integer',
        'snapshot_json' => 'array',
    ];

    public function goal()
    {
        return $this->belongsTo(ConversionGoal::class, 'goal_id');
    }
}
