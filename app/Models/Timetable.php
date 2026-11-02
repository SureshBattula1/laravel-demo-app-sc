<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Timetable extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'school_id',
        'grade',
        'grade_level',
        'section',
        'subject_id',
        'teacher_id',
        'day',
        'day_order',
        'period_number',
        'start_time',
        'end_time',
        'room_number',
        'schedule',
        'academic_year',
        'academic_year_id',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'day_order' => 'integer',
        'period_number' => 'integer',
        'schedule' => 'array',
        'is_active' => 'boolean',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function scopeForAcademicYear($query, $yearOrId)
    {
        if (is_numeric($yearOrId)) {
            return $query->where('academic_year_id', (int) $yearOrId);
        }

        return $query->where('academic_year', $yearOrId);
    }
}
