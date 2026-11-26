<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Check if user has permission to perform role actions
     */
    protected function checkRolePermission(Request $request, array|string $permissions): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        $perms = (array) $permissions;

        return $user->hasAnyPermission($perms);
    }

    /**
     * Check if the authenticated user can access/manage the given role or role ID based on hierarchy
     */
    protected function canAccessRole(Request $request, $roleOrId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        $role = is_object($roleOrId) ? $roleOrId : DB::table('roles')->where('id', $roleOrId)->first();
        if (! $role) {
            return false;
        }

        // Super Admin role (or slug super-admin) is strictly exclusive to Super Admin
        if (isset($role->slug) && $role->slug === 'super-admin') {
            return false;
        }

        $actorLevel = $this->getRoleLevelForUser($user);
        if ($actorLevel === null) {
            return false;
        }

        $targetLevel = isset($role->level) ? (int) $role->level : 999;

        return $targetLevel >= $actorLevel;
    }

    /**
     * Get all roles with pagination
     */
    public function index(Request $request)
    {
        if (! $this->checkRolePermission($request, ['roles.view', 'roles.create', 'roles.edit', 'roles.update'])) {
            return $this->forbiddenResponse('You do not have permission to view roles');
        }

        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $level = $request->get('level');
        $isSystemRole = $request->get('is_system_role');
        $slug = $request->get('slug');
        $sortBy = $request->get('sort_by', 'id');
        $sortDirection = $request->get('sort_direction', 'asc');

        $query = DB::table('roles')
            ->select('roles.*')
            ->selectRaw('(SELECT COUNT(*) FROM role_permissions WHERE role_id = roles.id) as permissions_count');

        // Apply role hierarchy filtering: Non-SuperAdmins cannot see SuperAdmin role or roles with higher level
        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        if (! $isSuperAdmin) {
            $actorLevel = $this->getRoleLevelForUser($user);
            if ($actorLevel !== null) {
                $query->where('level', '>=', $actorLevel);
            }
            $query->where('slug', '!=', 'super-admin');
        }

        // OPTIMIZED Search filter - prefix search for better index usage
        if ($search) {
            $search = strip_tags($search);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "{$search}%")
                    ->orWhere('slug', 'like', "{$search}%")
                    ->orWhere('description', 'like', "{$search}%");
            });
        }

        // Apply level filter
        if ($level) {
            $query->where('level', $level);
        }

        // Apply system role filter
        if ($isSystemRole !== null && $isSystemRole !== '') {
            $query->where('is_system_role', (bool) $isSystemRole);
        }

        // Apply slug filter - OPTIMIZED: prefix search
        if ($slug) {
            $slug = strip_tags($slug);
            $query->where('slug', 'like', "{$slug}%");
        }

        // Apply sorting
        $query->orderBy($sortBy, $sortDirection);

        $roles = $query->paginate($perPage);

        // OPTIMIZED: Batch load permissions to avoid N+1 queries
        $roleIds = collect($roles->items())->pluck('id')->toArray();

        if (! empty($roleIds)) {
            $allPermissions = DB::table('role_permissions')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
                ->whereIn('role_permissions.role_id', $roleIds)
                ->select(
                    'role_permissions.role_id',
                    'permissions.id',
                    'permissions.name',
                    'permissions.slug as display_name',
                    'permissions.description',
                    'permissions.action',
                    'modules.name as module',
                    'modules.slug as module_slug',
                    'permissions.created_at',
                    'permissions.updated_at'
                )
                ->get()
                ->groupBy('role_id');

            foreach ($roles->items() as $role) {
                $role->permissions = $allPermissions->get($role->id, []);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles,
        ]);
    }

    /**
     * Get all roles without pagination
     */
    public function all(Request $request)
    {
        if (! $this->checkRolePermission($request, ['roles.view', 'roles.create', 'roles.edit', 'roles.update'])) {
            return $this->forbiddenResponse('You do not have permission to view roles');
        }

        $query = DB::table('roles');

        $user = $request->user();
        $isSuperAdmin = $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
        if (! $isSuperAdmin) {
            $actorLevel = $this->getRoleLevelForUser($user);
            if ($actorLevel !== null) {
                $query->where('level', '>=', $actorLevel);
            }
            $query->where('slug', '!=', 'super-admin');
        }

        $roles = $query->orderBy('level')->orderBy('name')->get();

        $roleIds = collect($roles)->pluck('id')->toArray();

        // OPTIMIZED: Batch load permissions to avoid N+1 queries
        if (! empty($roleIds)) {
            $allPermissions = DB::table('role_permissions')
                ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
                ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
                ->whereIn('role_permissions.role_id', $roleIds)
                ->select(
                    'role_permissions.role_id',
                    'permissions.id',
                    'permissions.name',
                    'permissions.slug as display_name',
                    'permissions.description',
                    'permissions.action',
                    'modules.name as module',
                    'modules.slug as module_slug',
                    'permissions.created_at',
                    'permissions.updated_at'
                )
                ->get()
                ->groupBy('role_id');

            foreach ($roles as $role) {
                $role->permissions = $allPermissions->get($role->id, []);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Roles retrieved successfully',
            'data' => $roles,
        ]);
    }

    /**
     * Get single role
     */
    public function show(Request $request, $id)
    {
        if (! $this->checkRolePermission($request, ['roles.view', 'roles.create', 'roles.edit', 'roles.update'])) {
            return $this->forbiddenResponse('You do not have permission to view role details');
        }

        if (! $this->canAccessRole($request, $id)) {
            return $this->forbiddenResponse('You do not have permission to access this role');
        }

        $role = DB::table('roles')->where('id', $id)->first();

        if (! $role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found',
            ], 404);
        }

        $role->permissions = $this->getRolePermissions($id);

        return response()->json([
            'success' => true,
            'message' => 'Role retrieved successfully',
            'data' => $role,
        ]);
    }

    /**
     * Create new role
     */
    public function store(Request $request)
    {
        if (! $this->checkRolePermission($request, 'roles.create')) {
            return $this->forbiddenResponse('You do not have permission to create roles');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|unique:roles,name|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Generate slug from name
        $slug = $this->generateSlug($request->name);

        // Assign default level based on actor level (cannot create higher-rank role than actor)
        $user = $request->user();
        $actorLevel = $this->getRoleLevelForUser($user) ?? 3;

        $roleId = DB::table('roles')->insertGetId([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'level' => max($actorLevel, 3),
            'is_system_role' => false,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $role = DB::table('roles')->where('id', $roleId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role,
        ], 201);
    }

    /**
     * Update role
     */
    public function update(Request $request, $id)
    {
        if (! $this->checkRolePermission($request, ['roles.edit', 'roles.update'])) {
            return $this->forbiddenResponse('You do not have permission to update roles');
        }

        if (! $this->canAccessRole($request, $id)) {
            return $this->forbiddenResponse('You do not have permission to modify this role');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255|unique:roles,name,'.$id,
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $updateData = [
            'description' => $request->description,
            'updated_at' => now(),
        ];

        // If name is being updated, regenerate slug
        if ($request->has('name')) {
            $updateData['name'] = $request->name;
            $updateData['slug'] = $this->generateSlug($request->name);
        }

        DB::table('roles')->where('id', $id)->update($updateData);

        $role = DB::table('roles')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role,
        ]);
    }

    /**
     * Delete role
     */
    public function destroy(Request $request, $id)
    {
        if (! $this->checkRolePermission($request, 'roles.delete')) {
            return $this->forbiddenResponse('You do not have permission to delete roles');
        }

        if (! $this->canAccessRole($request, $id)) {
            return $this->forbiddenResponse('You do not have permission to delete this role');
        }

        $role = DB::table('roles')->where('id', $id)->first();
        if ($role && $role->is_system_role) {
            return response()->json([
                'success' => false,
                'message' => 'System roles cannot be deleted',
            ], 422);
        }

        // Check if role is being used
        $usersCount = DB::table('users')->where('role_id', $id)->count();

        if ($usersCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete role. It is assigned to {$usersCount} user(s)",
            ], 422);
        }

        // Delete role permissions
        DB::table('role_permissions')->where('role_id', $id)->delete();

        // Delete role
        DB::table('roles')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    /**
     * Assign permissions to role
     *
     * IMPORTANT: When role permissions change, ALL users with this role
     * automatically get the updated permissions (no user update needed).
     * User-specific overrides still take precedence over role permissions.
     */
    public function assignPermissions(Request $request, $id)
    {
        if (! $this->checkRolePermission($request, ['roles.edit', 'roles.update'])) {
            return $this->forbiddenResponse('You do not have permission to update role permissions');
        }

        if (! $this->canAccessRole($request, $id)) {
            return $this->forbiddenResponse('You do not have permission to modify permissions for this role');
        }

        $request->merge([
            'permissions' => $this->permissionIds($request->input('permissions', [])),
        ]);

        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*' => 'integer|exists:permissions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        DB::beginTransaction();
        try {
            // Delete existing role permissions
            DB::table('role_permissions')->where('role_id', $id)->delete();

            // Insert new permissions
            $permissions = collect($request->permissions)->map(function ($permissionId) use ($id) {
                return [
                    'role_id' => $id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            });

            if (! $permissions->isEmpty()) {
                DB::table('role_permissions')->insert($permissions->toArray());
            }

            // Get affected users count (for logging/notification)
            $affectedUsersCount = DB::table('user_roles')
                ->where('role_id', $id)
                ->distinct('user_id')
                ->count();

            DB::commit();

            $role = DB::table('roles')->where('id', $id)->first();
            $role->permissions = $this->getRolePermissions($id);

            return response()->json([
                'success' => true,
                'message' => "Permissions updated successfully. {$affectedUsersCount} user(s) will inherit these permissions.",
                'data' => [
                    'role' => $role,
                    'affected_users_count' => $affectedUsersCount,
                    'permissions_count' => count($request->permissions),
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions',
            ], 500);
        }
    }

    /**
     * Permission ids arrive as hash tokens. Decode them before the exists check.
     *
     * @param  mixed  $permissions
     * @return array<int, mixed>
     */
    private function permissionIds(mixed $permissions): array
    {
        if (! is_array($permissions)) {
            return [];
        }

        $hasher = app(\App\Support\IdHasher::class);

        return array_map(function ($permissionId) use ($hasher) {
            if (is_int($permissionId) || (is_string($permissionId) && ctype_digit($permissionId))) {
                return (int) $permissionId;
            }

            if ($hasher->enabled() && is_string($permissionId)) {
                $decoded = $hasher->decode($permissionId);
                if ($decoded !== null) {
                    return $decoded;
                }
            }

            return $permissionId;
        }, $permissions);
    }

    /**
     * Get role permissions with module information
     */
    public function getRolePermissions($roleId)
    {
        return DB::table('permissions')
            ->join('role_permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
            ->where('role_permissions.role_id', $roleId)
            ->select(
                'permissions.id',
                'permissions.name',
                'permissions.slug as display_name',
                'permissions.description',
                'permissions.action',
                'modules.name as module',
                'modules.slug as module_slug',
                'permissions.created_at',
                'permissions.updated_at'
            )
            ->get();
    }

    /**
     * Get permissions for a role
     */
    public function permissions(Request $request, $id)
    {
        if (! $this->checkRolePermission($request, ['roles.view', 'roles.create', 'roles.edit', 'roles.update'])) {
            return $this->forbiddenResponse('You do not have permission to view role permissions');
        }

        if (! $this->canAccessRole($request, $id)) {
            return $this->forbiddenResponse('You do not have permission to view permissions for this role');
        }

        $permissions = $this->getRolePermissions($id);

        return response()->json([
            'success' => true,
            'message' => 'Role permissions retrieved successfully',
            'data' => $permissions,
        ]);
    }

    /**
     * Generate URL-friendly slug from name
     */
    private function generateSlug($name)
    {
        // Convert to lowercase
        $slug = strtolower($name);

        // Replace spaces and special characters with hyphens
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);

        // Remove leading/trailing hyphens
        $slug = trim($slug, '-');

        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;

        while (DB::table('roles')->where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }
}
