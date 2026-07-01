<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use BelongsToTenant;
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'head', 'head_id', 'description', 'established_date',
        'branch_id', 'school_id', 'students_count', 'teachers_count', 'is_active'
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'established_date' => 'date',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function headOfDepartment()
    {
        return $this->belongsTo(User::class, 'head_id');
    }

    public function subjects()
    {
        return $this->hasMany(Subject::class);
    }

    // Scopes
    public function scopeForSchool($query, int $schoolId)
    {
        return $query->where('school_id', $schoolId);
    }
}
