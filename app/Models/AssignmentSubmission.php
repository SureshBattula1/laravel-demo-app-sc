<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentSubmission extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'assignment_id',
        'student_id',
        'submission_text',
        'attachments',
        'submitted_at',
        'marks_obtained',
        'feedback',
        'graded_at',
        'graded_by',
        'status'
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'graded_at' => 'datetime',
        'attachments' => 'array',
        'marks_obtained' => 'decimal:2'
    ];

    // Relationships
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function gradedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    // Scopes
    public function scopeSubmitted($query)
    {
        return $query->whereNotNull('submitted_at');
    }

    public function scopeGraded($query)
    {
        return $query->whereNotNull('graded_at');
    }

    public function scopePending($query)
    {
        return $query->whereNull('submitted_at');
    }
}
