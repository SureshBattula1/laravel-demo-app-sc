<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamResult extends Model
{
    protected $fillable = [
        'exam_id',
        'student_id',
        'marks_obtained',
        'grade',
        'percentage',
        'rank',
        'remarks',
        'is_pass'
    ];

    protected $casts = [
        'marks_obtained' => 'decimal:2',
        'percentage' => 'decimal:2',
        'is_pass' => 'boolean'
    ];

    /**
     * Get the exam that owns the result
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    /**
     * Get the student that owns the result
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Get the subject (if exam has subject_id)
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
