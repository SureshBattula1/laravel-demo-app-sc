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
        'grade',
        'section',
        'exam_date',
        'start_time',
        'end_time',
        'duration',
        'total_marks',
        'passing_marks',
        'room_number',
        'invigilator_id'
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'total_marks' => 'decimal:2',
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
}

