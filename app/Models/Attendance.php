<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Attendance extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'id',
        'branch_id',
        'user_id',
        'user_type',
        'attendance_date',
        'status',
        'check_in_time',
        'check_out_time',
        'total_hours',
        'remarks',
        'marked_by',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'check_in_time' => 'datetime',
        'check_out_time' => 'datetime',
        'total_hours' => 'decimal:2'
    ];

    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
            // Calculate total hours if check-in and check-out are set
            if ($model->check_in_time && $model->check_out_time) {
                $model->total_hours = $model->check_in_time->diffInHours($model->check_out_time, true);
            }
        });
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markedBy()
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
