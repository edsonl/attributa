<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaskNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'task_id', 'description', 'date', 'time_minutes', 'value','paid','done','user_id',
    ];

    protected $casts = [
        'date' => 'datetime',
        'paid' => 'boolean',
        'done' => 'boolean',
    ];

    //protected $casts = [
        //'paid' => 'boolean',
    //];

    // Relações
    public function task()
    {
        return $this->belongsTo(Task::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Acessores
    public function getFormattedTimeAttribute(): string
    {
        if (!$this->time_minutes) return '';
        $h = floor($this->time_minutes / 60);
        $m = $this->time_minutes % 60;
        return sprintf('%02dh %02dm', $h, $m);
    }
}
