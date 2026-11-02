<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClassModel extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'branch_id',
        'school_id',
        'grade',
        'section',
        'class_name',
        'academic_year',
        'academic_year_id',
        'class_teacher_id',
        'capacity',
        'current_strength',
        'room_number',
        'description',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'capacity' => 'integer',
            'current_strength' => 'integer',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear()
    {
        return $this->belongsTo(AcademicYear::class, 'academic_year_id');
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students()
    {
        return $this->hasMany(User::class, 'class_id')->where('user_type', 'Student');
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

    // Scopes
    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }

    public function scopeForAcademicYear($query, $yearOrId)
    {
        if (is_numeric($yearOrId)) {
            return $query->where('academic_year_id', (int) $yearOrId);
        }

        return $query->where('academic_year', $yearOrId);
    }
}
