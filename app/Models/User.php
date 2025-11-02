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

    /**
     * Get user preferences
     */
    public function preferences()
    {
        return $this->hasOne(UserPreference::class);
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
     * Priority: User-specific overrides > Role permissions
     */
    public function hasPermission(string $permissionSlug, ?int $branchId = null): bool
    {
        // REMOVED SuperAdmin bypass - SuperAdmin now follows permissions like everyone else
        // If you want SuperAdmin to have all permissions, assign them to the SuperAdmin role

        // STEP 1: Check user-specific permission overrides FIRST (highest priority)
        $userPermission = $this->permissions()
            ->where('slug', $permissionSlug)
            ->when($branchId, fn($q) => $q->where('user_permissions.branch_id', $branchId))
            ->first();

        // If user has explicit override, use it (granted=true means YES, granted=false means NO)
        if ($userPermission) {
            return (bool) $userPermission->pivot->granted;
        }

        // STEP 2: Check role-based permissions (if no user override)
        // This automatically updates when role permissions change
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
     * NO CACHING - Always fresh from database
     * 
     * Logic:
     * 1. Get all permissions from user's roles
     * 2. Apply user-specific overrides (granted=true adds, granted=false removes)
     * 
     * Note: SuperAdmin bypass removed - SuperAdmin must have permissions assigned to role
     */
    public function getAllPermissions(?int $branchId = null): Collection
    {
        // STEP 1: Get all permissions from all assigned roles
        $rolePermissionIds = \DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->where('user_roles.user_id', $this->id)
            ->when($branchId, fn($q) => $q->where('user_roles.branch_id', $branchId))
            ->distinct()
            ->pluck('role_permissions.permission_id')
            ->toArray();

        // Get permission objects
        $rolePermissions = \DB::table('permissions')
            ->whereIn('id', $rolePermissionIds)
            ->get();

        // Convert to keyed collection by permission ID
        $finalPermissions = collect($rolePermissions)->keyBy('id');

        // STEP 2: Apply user-specific permission overrides
        $userOverrides = \DB::table('user_permissions')
            ->where('user_id', $this->id)
            ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
            ->get();

        foreach ($userOverrides as $override) {
            if ($override->granted) {
                // GRANT: Add this permission (even if not in role)
                if (!$finalPermissions->has($override->permission_id)) {
                    $permission = \DB::table('permissions')
                        ->where('id', $override->permission_id)
                        ->first();
                    if ($permission) {
                        $finalPermissions[$override->permission_id] = $permission;
                    }
                }
            } else {
                // REVOKE: Remove this permission (even if in role)
                $finalPermissions->forget($override->permission_id);
            }
        }

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
        return $this->hasAnyPermission([
            'system.cross_branch_access',
            'system.manage_all_branches',
            'system.view_all_branches'
        ]);
    }
}
