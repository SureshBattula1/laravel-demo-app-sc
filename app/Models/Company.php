<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'email',
        'phone',
        'address',
        'city',
        'state',
        'country',
        'pincode',
        'tax_id',
        'website',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    // Relationships
    public function schools()
    {
        return $this->hasMany(School::class);
    }

    public function activeSchools()
    {
        return $this->hasMany(School::class)->where('status', 'Active');
    }

    public function companyAdmins()
    {
        return $this->hasMany(User::class)->where('user_type', 'CompanyAdmin');
    }

    public function supportStaff()
    {
        return $this->hasMany(User::class)->where('user_type', 'SupportStaff');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function getTotalSchoolsCount(): int
    {
        return $this->schools()->count();
    }

    public function getActiveSchoolsCount(): int
    {
        return $this->activeSchools()->count();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }
}

