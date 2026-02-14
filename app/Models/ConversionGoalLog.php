<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConversionGoalLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id',
        'message',
    ];

    public function conversionGoal()
    {
        return $this->belongsTo(ConversionGoal::class, 'goal_id');
    }
}
