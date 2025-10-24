<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamAttendance extends Model
{
    protected $fillable = [
        'exam_schedule_id',
        'student_id',
        'status',
        'arrival_time',
        'remarks'
    ];

    protected function casts(): array
    {
        return [
            'arrival_time' => 'datetime',
        ];
    }

    // Relationships
    public function examSchedule(): BelongsTo
    {
        return $this->belongsTo(ExamSchedule::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}

