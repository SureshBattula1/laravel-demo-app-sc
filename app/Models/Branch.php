<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'code', 'address', 'city', 'state', 'country', 'pincode',
        'phone', 'email', 'principal_name', 'principal_contact', 'principal_email',
        'established_date', 'affiliation_number', 'logo', 'is_main_branch',
        'is_active', 'settings'
    ];

    protected function casts(): array
    {
        return [
            'is_main_branch' => 'boolean',
            'is_active' => 'boolean',
            'settings' => 'array',
            'established_date' => 'date',
        ];
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }

    public function students()
    {
        return $this->hasMany(User::class)->where('role', 'Student');
    }

    public function teachers()
    {
        return $this->hasMany(User::class)->where('role', 'Teacher');
    }
}
