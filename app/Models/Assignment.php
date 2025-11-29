<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Assignment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'grade',
        'section',
        'subject_id',
        'teacher_id',
        'title',
        'description',
        'instructions',
        'due_date',
        'max_marks',
        'assignment_type',
        'attachments',
        'is_published',
        'published_at',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'due_date' => 'datetime',
        'published_at' => 'datetime',
        'is_published' => 'boolean',
        'attachments' => 'array',
        'max_marks' => 'decimal:2'
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public function scopeByGrade($query, $grade)
    {
        return $query->where('grade', $grade);
    }

    public function scopeBySection($query, $section)
    {
        return $query->where('section', $section);
    }
}
