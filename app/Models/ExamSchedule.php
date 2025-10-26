<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamSchedule extends Model
{
    protected $fillable = [
        'exam_id',
        'subject_id',
        'branch_id',
        'grade_level',
        'section',
        'exam_date',
        'start_time',
        'end_time',
        'duration',
        'total_marks',
        'passing_marks',
        'room_number',
        'invigilator_id',
        'instructions',
        'status',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'is_active' => 'boolean',
            'duration' => 'integer',
            'total_marks' => 'decimal:2',
            'passing_marks' => 'decimal:2',
        ];
    }

    // Relationships
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function invigilator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invigilator_id');
    }

    public function marks(): HasMany
    {
        return $this->hasMany(ExamMark::class);
    }

    public function attendance(): HasMany
    {
        return $this->hasMany(ExamAttendance::class);
    }
}

