<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StudentLeave extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'student_id',
        'branch_id',
        'from_date',
        'to_date',
        'total_days',
        'leave_type',
        'status',
        'reason',
        'remarks',
        'attachment',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'from_date' => 'date',
        'to_date' => 'date',
        'approved_at' => 'datetime',
        'total_days' => 'integer'
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            // Calculate total days if not set
            if (empty($model->total_days) && $model->from_date && $model->to_date) {
                $from = \Carbon\Carbon::parse($model->from_date);
                $to = \Carbon\Carbon::parse($model->to_date);
                $model->total_days = $from->diffInDays($to) + 1;
            }
        });

        static::updating(function ($model) {
            // Recalculate total days if dates change
            if ($model->isDirty(['from_date', 'to_date'])) {
                $from = \Carbon\Carbon::parse($model->from_date);
                $to = \Carbon\Carbon::parse($model->to_date);
                $model->total_days = $from->diffInDays($to) + 1;
            }
        });
    }

    // Relationships
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
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

