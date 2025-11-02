<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamTerm extends Model
{
    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'academic_year',
        'start_date',
        'end_date',
        'weightage',
        'description',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'is_active' => 'boolean',
            'weightage' => 'integer',
        ];
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'exam_term_id');
    }
}

