<?php
namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;


class Task extends Model
{
    use HasFactory, SoftDeletes;


    protected $fillable = [
        'title', 'description', 'status', 'priority', 'due_date',
        'assigned_to_id', 'created_by_id','company_id'
    ];


    protected $casts = [
        'due_date' => 'date',
    ];


    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_DONE = 'done';


    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';


    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }


    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function notes()
    {
        return $this->hasMany(TaskNote::class)->latest();
    }

    public function getTotalTimeFormattedAttribute()
    {
        if (!$this->total_time_minutes) return null;
        $h = floor($this->total_time_minutes / 60);
        $m = $this->total_time_minutes % 60;
        return sprintf('%02dh %02dm', $h, $m);
    }

    public function getTotalValueFormattedAttribute()
    {
        return $this->total_value ? number_format($this->total_value, 2, ',', '.') : null;
    }



}
