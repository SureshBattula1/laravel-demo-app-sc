<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'phone',
        'mobile',
        'password',
        'role',
        'user_type',
        'user_type_id',
        'branch_id',
        'avatar',
        'is_active',
        'last_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    // Relationships
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get roles assigned to this user
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles')
                    ->withPivot(['is_primary', 'branch_id'])
                    ->withTimestamps();
    }

    /**
     * Get user-specific permission overrides
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions')
                    ->withPivot(['granted', 'branch_id'])
                    ->withTimestamps();
    }

    // Accessors
    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Helper methods
    public function isSuperAdmin()
    {
        return $this->role === 'SuperAdmin';
    }

    public function isTeacher()
    {
        return $this->role === 'Teacher';
    }

    public function isStudent()
    {
        return $this->role === 'Student';
    }

    public function isParent()
    {
        return $this->role === 'Parent';
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission(string $permissionSlug, ?int $branchId = null): bool
    {
        // SuperAdmin has all permissions
        if ($this->isSuperAdmin()) {
            return true;
        }

        // Check user-specific permission overrides first
        $userPermission = $this->permissions()
            ->where('slug', $permissionSlug)
            ->when($branchId, fn($q) => $q->where('user_permissions.branch_id', $branchId))
            ->first();

        if ($userPermission) {
            return $userPermission->pivot->granted;
        }

        // Check role-based permissions
        return $this->roles()
            ->whereHas('permissions', function($q) use ($permissionSlug) {
                $q->where('slug', $permissionSlug);
            })
            ->when($branchId, fn($q) => $q->where('user_roles.branch_id', $branchId))
            ->exists();
    }

    /**
     * Check if user has any of the given permissions
     */
    public function hasAnyPermission(array $permissions, ?int $branchId = null): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission, $branchId)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all given permissions
     */
    public function hasAllPermissions(array $permissions, ?int $branchId = null): bool
    {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission, $branchId)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get all user permissions (from roles + overrides)
     */
    public function getAllPermissions(?int $branchId = null): Collection
    {
        if ($this->isSuperAdmin()) {
            return Permission::all();
        }

        // Get permissions from roles
        $rolePermissions = Permission::whereHas('roles', function($q) use ($branchId) {
            $q->whereHas('users', function($q2) use ($branchId) {
                $q2->where('users.id', $this->id)
                   ->when($branchId, fn($q3) => $q3->where('user_roles.branch_id', $branchId));
            });
        })->get();

        // Apply user-specific overrides
        $overrides = $this->permissions()
            ->when($branchId, fn($q) => $q->where('user_permissions.branch_id', $branchId))
            ->get();

        $permissions = $rolePermissions->keyBy('id');

        foreach ($overrides as $override) {
            if ($override->pivot->granted) {
                $permissions[$override->id] = $override;
            } else {
                unset($permissions[$override->id]);
            }
        }

        return $permissions->values();
    }

    /**
     * Get all permission slugs for this user
     */
    public function getPermissionSlugs(?int $branchId = null): array
    {
        return $this->getAllPermissions($branchId)->pluck('slug')->toArray();
    }
}
