<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Branch;
use App\Models\User;

class ClassModel extends Model
{
    use HasFactory;

    protected $table = 'classes';

    protected $fillable = [
        'branch_id',
        'grade',
        'section',
        'class_name',
        'academic_year',
        'class_teacher_id',
        'capacity',
        'current_strength',
        'room_number',
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
}

