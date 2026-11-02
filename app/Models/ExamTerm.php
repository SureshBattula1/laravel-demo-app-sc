<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamTerm extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'school_id',
        'academic_year',
        'academic_year_id',
        'start_date',
        'end_date',
        'weightage',
        'description',
        'is_active',
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

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'exam_term_id');
    }

    public function scopeForAcademicYear($query, $yearOrId)
    {
        if (is_numeric($yearOrId)) {
            return $query->where('academic_year_id', (int) $yearOrId);
        }

        return $query->where('academic_year', $yearOrId);
    }
}
