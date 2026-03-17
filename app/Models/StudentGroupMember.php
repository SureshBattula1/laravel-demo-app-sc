<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGroupMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'student_id',
        'joined_date',
        'role',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'joined_date' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function group()
    {
        return $this->belongsTo(StudentGroup::class, 'group_id');
    }

    /**
     * Get the User (for display) via Student - student_id references students.id, not users.id
     */
    public function student()
    {
        return $this->hasOneThrough(
            User::class,
            Student::class,
            'id',           // Student.id = student_group_members.student_id
            'id',           // User.id = students.user_id
            'student_id',
            'user_id'
        );
    }

    /**
     * Get the Student record
     */
    public function studentRecord()
    {
        return $this->belongsTo(Student::class, 'student_id');
    }
}

