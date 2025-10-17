<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function classTeacher()
    {
        return $this->belongsTo(User::class, 'class_teacher_id');
    }

    public function students()
    {
        return $this->hasMany(User::class, 'section_id')->where('user_type', 'Student');
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

