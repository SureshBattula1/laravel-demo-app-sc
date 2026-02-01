<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class School extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'code',
        'main_branch_id',
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
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function mainBranch()
    {
        return $this->belongsTo(Branch::class, 'main_branch_id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class);
    }

    public function activeBranches()
    {
        return $this->hasMany(Branch::class)->where('is_active', true);
    }

    public function students()
    {
        return $this->hasManyThrough(
            \App\Models\User::class,
            Branch::class,
            'school_id', // Foreign key on branches table
            'branch_id', // Foreign key on users table
            'id', // Local key on schools table
            'id' // Local key on branches table
        )->where('role', 'Student');
    }

    public function teachers()
    {
        return $this->hasManyThrough(
            \App\Models\User::class,
            Branch::class,
            'school_id', // Foreign key on branches table
            'branch_id', // Foreign key on users table
            'id', // Local key on schools table
            'id' // Local key on branches table
        )->where('role', 'Teacher');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'Active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'Inactive';
    }

    public function canBeActivated(): bool
    {
        return in_array($this->status, ['Inactive', 'Suspended', 'UnderConstruction']);
    }

    public function activate(): void
    {
        $this->update(['status' => 'Active']);
    }

    public function deactivate(): void
    {
        $this->update(['status' => 'Inactive']);
    }

    public function getTotalBranchesCount(): int
    {
        return $this->branches()->count();
    }

    public function getActiveBranchesCount(): int
    {
        return $this->activeBranches()->count();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'Active');
    }

    public function scopeInactive($query)
    {
        return $query->where('status', 'Inactive');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }
}

