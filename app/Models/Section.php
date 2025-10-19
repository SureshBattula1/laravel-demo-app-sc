<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Section extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'grade_level',
        'capacity',
        'current_strength',
        'room_number',
        'class_teacher_id',
        'description',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
            'current_strength' => 'integer',
        ];
    }

    /**
     * Attributes to append to JSON
     */
    protected $appends = ['grade_label', 'actual_strength'];

    /**
     * Get grade label (e.g., "Grade 5" instead of "5")
     */
    public function getGradeLabelAttribute(): ?string
    {
        if ($this->grade_level) {
            // Get grade details from grades table
            $grade = DB::table('grades')->where('value', $this->grade_level)->first();
            if ($grade) {
                return $grade->label;
            }
            return 'Grade ' . $this->grade_level;
        }
        return 'All Grades';
    }

    /**
     * Get full grade details
     */
    public function getGradeDetailsAttribute(): ?array
    {
        if ($this->grade_level) {
            $grade = DB::table('grades')->where('value', $this->grade_level)->first();
            if ($grade) {
                return [
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description ?? null,
                    'is_active' => (bool) $grade->is_active
                ];
            }
            // Fallback if grade not found in grades table
            return [
                'value' => $this->grade_level,
                'label' => 'Grade ' . $this->grade_level,
                'description' => null,
                'is_active' => true
            ];
        }
        return null;
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    /**
     * Get actual student strength by counting students in this section
     */
    public function getActualStrengthAttribute(): int
    {
        return DB::table('students')
            ->where('branch_id', $this->branch_id)
            ->where('grade', $this->grade_level)
            ->where('section', $this->name)
            ->where('student_status', 'Active')
            ->count();
    }

    public function students()
    {
        // Note: This relationship won't work with current schema where students have grade/section as strings
        // Use the actualStrength accessor instead
        return $this->hasMany(User::class, 'section_id')->where('user_type', 'Student');
    }

    /**
     * Get associated class (if exists)
     */
    public function class()
    {
        return $this->hasOne(\App\Models\ClassModel::class, 'section', 'name')
            ->where('grade', $this->grade_level)
            ->where('branch_id', $this->branch_id);
    }

    // Helper methods
    public function getAvailableSeats()
    {
        return $this->capacity - $this->current_strength;
    }

    public function isFull()
    {
        return $this->current_strength >= $this->capacity;
    }
}

