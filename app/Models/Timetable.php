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
        'duration_minutes',
        'room_number',
        'schedule_mode',
        'is_default_weekly',
        'effective_date',
        'active_days',
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
        'duration_minutes' => 'integer',
        'is_default_weekly' => 'boolean',
        'effective_date' => 'date:Y-m-d',
        'active_days' => 'array',
        'schedule' => 'array',
        'is_active' => 'boolean',
    ];

    public static function calculateDurationMinutes(?string $startTime, ?string $endTime): ?int
    {
        if (! $startTime || ! $endTime) {
            return null;
        }
        $startParts = explode(':', $startTime);
        $endParts = explode(':', $endTime);
        if (count($startParts) < 2 || count($endParts) < 2) {
            return null;
        }
        $startMins = ((int) $startParts[0]) * 60 + ((int) $startParts[1]);
        $endMins = ((int) $endParts[0]) * 60 + ((int) $endParts[1]);
        $diff = $endMins - $startMins;

        return $diff > 0 ? $diff : null;
    }

    public static function dayOrderForName(string $day): int
    {
        return match (strtolower(trim($day))) {
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 7,
            default => 1,
        };
    }

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
