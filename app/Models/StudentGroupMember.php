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

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}

