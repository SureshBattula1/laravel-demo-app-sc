<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'code',
        'type',
        'academic_year',
        'description',
        'is_active'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function members()
    {
        return $this->hasMany(StudentGroupMember::class, 'group_id');
    }

    public function students()
    {
        return $this->belongsToMany(User::class, 'student_group_members', 'group_id', 'student_id')
                    ->where('users.role', 'Student')
                    ->withPivot(['joined_date', 'role', 'is_active'])
                    ->withTimestamps();
    }

    // Helper methods
    public function isActive()
    {
        return $this->is_active;
    }

    public function getMemberCount()
    {
        return $this->members()->where('is_active', true)->count();
    }
}

