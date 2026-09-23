<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

class Role extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'level',
        'is_system_role',
        'is_active'
    ];

    protected $casts = [
        'is_system_role' => 'boolean',
        'is_active' => 'boolean',
        'level' => 'integer'
    ];

    /**
     * Get permissions assigned to this role
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions')
                    ->withTimestamps();
    }

    /**
     * Get users with this role
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_roles')
                    ->withPivot(['is_primary', 'branch_id'])
                    ->withTimestamps();
    }

    /**
     * Check if role has a specific permission
     */
    public function hasPermission(string $permissionSlug): bool
    {
        return $this->permissions()
                    ->where('slug', $permissionSlug)
                    ->exists();
    }

    /**
     * Grant a permission to this role
     */
    public function grantPermission(Permission $permission): void
    {
        $this->permissions()->syncWithoutDetaching($permission);
    }

    /**
     * Revoke a permission from this role
     */
    public function revokePermission(Permission $permission): void
    {
        $this->permissions()->detach($permission);
    }

    /**
     * Sync permissions for this role
     */
    public function syncPermissions(array $permissionIds): void
    {
        $this->permissions()->sync($permissionIds);
    }

    /**
     * Get all permission slugs for this role
     */
    public function getPermissionSlugs(): Collection
    {
        return $this->permissions()->pluck('slug');
    }

    /**
     * Resolve a role by slug, including Super Admin aliases (super-admin / super-admin-1).
     */
    public static function findBySlug(string $slug): ?self
    {
        $role = static::query()->where('slug', $slug)->first();
        if ($role) {
            return $role;
        }

        if (in_array($slug, ['super-admin', 'super-admin-1'], true)) {
            $role = static::query()
                ->where(function ($query) {
                    $query->whereIn('slug', ['super-admin', 'super-admin-1'])
                        ->orWhere('name', 'Super Admin');
                })
                ->orderBy('id')
                ->first();

            if ($role && $role->slug !== 'super-admin') {
                $role->update(['slug' => 'super-admin']);
            }

            return $role;
        }

        return null;
    }

    /**
     * Scope: Active roles only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Order by level (hierarchy)
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('level', 'asc');
    }
}

