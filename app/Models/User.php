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
        $collection = collect();
        
        // Step 1: Get all roles assigned to this user
        $userRoles = $this->roles()
            ->when($branchId, fn($q) => $q->where('user_roles.branch_id', $branchId))
            ->with('permissions') // Eager load permissions
            ->get();
        
        // Step 2: Get all permissions from all user's roles
        foreach ($userRoles as $role) {
            // Get permissions for this role using the relationship
            $rolePermissions = $role->permissions()->get();
            
            foreach ($rolePermissions as $permission) {
                // Add to collection if not already present
                if (!$collection->contains('id', $permission->id)) {
                    $collection->push($permission);
                }
            }
        }

        // Step 3: Apply user-specific permission overrides
        $overrides = $this->permissions()
            ->when($branchId, fn($q) => $q->where('user_permissions.branch_id', $branchId))
            ->get();

        \Log::info('User permission overrides:', [
            'user_id' => $this->id,
            'overrides_count' => $overrides->count(),
            'override_details' => $overrides->map(fn($p) => [
                'permission_id' => $p->id,
                'slug' => $p->slug,
                'granted' => $p->pivot->granted
            ])->toArray()
        ]);

        // Convert collection to keyed array for easier merging
        $finalPermissions = $collection->keyBy('id');

        // Apply overrides - add or remove based on 'granted' flag
        foreach ($overrides as $override) {
            if ($override->pivot->granted) {
                // Grant this permission (add to collection)
                $finalPermissions[$override->id] = $override;
            } else {
                // Revoke this permission (remove from collection)
                unset($finalPermissions[$override->id]);
            }
        }

        \Log::info('Final permissions after overrides:', [
            'user_id' => $this->id,
            'total_permissions' => $finalPermissions->count(),
            'sample_slugs' => $finalPermissions->pluck('slug')->slice(0, 10)->toArray()
        ]);

        return $finalPermissions->values();
    }

    /**
     * Get all permission slugs for this user
     */
    public function getPermissionSlugs(?int $branchId = null): array
    {
        return $this->getAllPermissions($branchId)->pluck('slug')->toArray();
    }

    /**
     * Check if user has cross-branch access permission
     * This allows them to bypass branch restrictions
     */
    public function hasCrossBranchAccess(): bool
    {
        // SuperAdmin always has cross-branch access
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Check for specific cross-branch permissions
        return $this->hasAnyPermission([
            'system.cross_branch_access',
            'system.manage_all_branches',
            'system.view_all_branches'
        ]);
    }
    
    /**
     * Check if user can manage (edit/delete) across branches
     */
    public function canManageAllBranches(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->hasAnyPermission([
            'system.cross_branch_access',
            'system.manage_all_branches'
        ]);
    }
    
    /**
     * Check if user can view all branches (read-only)
     */
    public function canViewAllBranches(): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        return $this->hasAnyPermission([
            'system.cross_branch_access',
            'system.manage_all_branches',
            'system.view_all_branches'
        ]);
    }
}
