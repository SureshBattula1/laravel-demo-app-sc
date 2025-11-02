<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Get all users with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $role = $request->get('role');
        $branch = $request->get('branch_id');

        $query = User::with(['branch']);

        // OPTIMIZED Search filter - prefix search for better index usage
        if ($search) {
            $search = strip_tags($search);
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "{$search}%")
                  ->orWhere('last_name', 'like', "{$search}%")
                  ->orWhere('email', 'like', "{$search}%");
            });
        }

        // Apply role filter
        if ($role) {
            $query->where('role', $role);
        }

        // Apply branch filter
        if ($branch) {
            $query->where('branch_id', $branch);
        }

        $users = $query->latest()->paginate($perPage);

        // OPTIMIZED: Cache roles array to avoid N+1 queries
        $rolesMap = DB::table('roles')->pluck('id', 'name')->toArray();

        // Add role_id to each user (no extra queries!)
        foreach ($users->items() as $user) {
            $user->role_id = $rolesMap[$user->role] ?? null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get all users without pagination
     */
    public function all()
    {
        $users = User::with(['branch'])->get();

        // OPTIMIZED: Cache roles array to avoid N+1 queries
        $rolesMap = DB::table('roles')->pluck('id', 'name')->toArray();

        // Add role_id to each user (no extra queries!)
        foreach ($users as $user) {
            $user->role_id = $rolesMap[$user->role] ?? null;
        }

        return response()->json([
            'success' => true,
            'message' => 'Users retrieved successfully',
            'data' => $users
        ]);
    }

    /**
     * Get single user
     */
    public function show($id)
    {
        $user = User::with(['branch'])->findOrFail($id);

        // OPTIMIZED: Single query for role_id
        $roleRecord = DB::table('roles')->where('name', $user->role)->select('id')->first();
        $user->role_id = $roleRecord ? $roleRecord->id : null;

        return response()->json([
            'success' => true,
            'message' => 'User retrieved successfully',
            'data' => $user
        ]);
    }

    /**
     * Create new user
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8|confirmed',
            'role_id' => 'required|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get role name from role_id
        $role = DB::table('roles')->where('id', $request->role_id)->first();
        $roleName = $role ? $role->name : 'Student';

        $user = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $roleName,
            'branch_id' => $request->branch_id,
            'is_active' => $request->get('is_active', true),
            'email_verified_at' => now()
        ]);

        // Add role_id to response for frontend
        $userData = $user->toArray();
        $userData['role_id'] = $request->role_id;

        return response()->json([
            'success' => true,
            'message' => 'User created successfully',
            'data' => $userData
        ], 201);
    }

    /**
     * Update user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', Rule::unique('users')->ignore($id)],
            'password' => 'sometimes|string|min:8|confirmed',
            'role_id' => 'sometimes|exists:roles,id',
            'branch_id' => 'nullable|exists:branches,id',
            'is_active' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $request->only(['first_name', 'last_name', 'email', 'branch_id', 'is_active']);

        // Convert role_id to role name
        if ($request->has('role_id')) {
            $role = DB::table('roles')->where('id', $request->role_id)->first();
            if ($role) {
                // Map role name to the enum values allowed in users table
                $roleMapping = [
                    'Super Admin' => 'SuperAdmin',
                    'Branch Admin' => 'BranchAdmin',
                    'Admin' => 'SuperAdmin',
                    'BranchAdmin' => 'BranchAdmin',
                    'SuperAdmin' => 'SuperAdmin'
                ];
                $data['role'] = $roleMapping[$role->name] ?? $user->role;
            }
        }

        if ($request->has('password')) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        $userData = $user->fresh()->toArray();
        // Add role_id to response
        if ($request->has('role_id')) {
            $userData['role_id'] = $request->role_id;
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => $userData
        ]);
    }

    /**
     * Delete user
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        // Prevent deleting yourself
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot delete your own account'
            ], 403);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }

    /**
     * Toggle user active status
     */
    public function toggleStatus($id)
    {
        $user = User::findOrFail($id);

        // Prevent toggling your own status
        if ($user->id === auth()->id()) {
            return response()->json([
                'success' => false,
                'message' => 'You cannot toggle your own status'
            ], 403);
        }

        $user->is_active = !$user->is_active;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'User status updated successfully',
            'data' => $user
        ]);
    }

    /**
     * Reset user password
     */
    public function resetPassword(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully'
        ]);
    }

    /**
     * Get user's permissions (from roles + user-specific overrides)
     */
    public function getPermissions($id)
    {
        $user = User::with(['roles.permissions', 'permissions'])->findOrFail($id);

        // Get all permissions with their status
        $allPermissions = DB::table('permissions')
            ->join('modules', 'permissions.module_id', '=', 'modules.id')
            ->select(
                'permissions.id',
                'permissions.name',
                'permissions.slug',
                'permissions.action',
                'modules.name as module_name',
                'modules.slug as module_slug',
                'modules.icon as module_icon',
                'modules.order as module_order'
            )
            ->orderBy('modules.order')
            ->orderBy('permissions.id')
            ->get();

        // Get permissions from roles
        $rolePermissionIds = DB::table('role_permissions')
            ->join('user_roles', 'role_permissions.role_id', '=', 'user_roles.role_id')
            ->where('user_roles.user_id', $id)
            ->pluck('role_permissions.permission_id')
            ->unique()
            ->toArray();

        // Get user-specific permission overrides
        $userPermissions = DB::table('user_permissions')
            ->where('user_id', $id)
            ->get()
            ->keyBy('permission_id');

        // Build permission list with status
        $permissionsData = [];
        foreach ($allPermissions as $permission) {
            $hasFromRole = in_array($permission->id, $rolePermissionIds);
            $userOverride = $userPermissions->get($permission->id);

            // Determine final status
            $granted = $hasFromRole; // Default from role
            $overridden = false;

            if ($userOverride) {
                $granted = (bool) $userOverride->granted;
                $overridden = true;
            }

            $permissionsData[] = [
                'id' => $permission->id,
                'name' => $permission->name,
                'slug' => $permission->slug,
                'action' => $permission->action,
                'module_name' => $permission->module_name,
                'module_slug' => $permission->module_slug,
                'module_icon' => $permission->module_icon,
                'module_order' => $permission->module_order,
                'granted' => $granted,
                'from_role' => $hasFromRole,
                'overridden' => $overridden,
            ];
        }

        // Group by module
        $groupedPermissions = [];
        foreach ($permissionsData as $perm) {
            $moduleSlug = $perm['module_slug'];
            if (!isset($groupedPermissions[$moduleSlug])) {
                $groupedPermissions[$moduleSlug] = [
                    'module_name' => $perm['module_name'],
                    'module_slug' => $moduleSlug,
                    'module_icon' => $perm['module_icon'],
                    'module_order' => $perm['module_order'],
                    'permissions' => []
                ];
            }
            $groupedPermissions[$moduleSlug]['permissions'][] = $perm;
        }

        // Sort by module order
        usort($groupedPermissions, function($a, $b) {
            return $a['module_order'] <=> $b['module_order'];
        });

        return response()->json([
            'success' => true,
            'message' => 'User permissions retrieved successfully',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->full_name,
                    'email' => $user->email,
                    'role' => $user->role,
                    'roles' => $user->roles->map(function($role) {
                        return [
                            'id' => $role->id,
                            'name' => $role->name,
                            'slug' => $role->slug
                        ];
                    })
                ],
                'permissions' => $permissionsData,
                'grouped_permissions' => array_values($groupedPermissions)
            ]
        ]);
    }

    /**
     * Assign/Update user permissions
     */
    public function updatePermissions(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'permissions' => 'required|array',
            'permissions.*.permission_id' => 'required|exists:permissions,id',
            'permissions.*.granted' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);

        DB::beginTransaction();
        try {
            // Clear existing user permissions
            DB::table('user_permissions')->where('user_id', $id)->delete();

            // Insert new permissions
            $permissionsToInsert = [];
            foreach ($request->permissions as $perm) {
                $permissionsToInsert[] = [
                    'user_id' => $id,
                    'permission_id' => $perm['permission_id'],
                    'granted' => $perm['granted'],
                    'branch_id' => $user->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            if (!empty($permissionsToInsert)) {
                DB::table('user_permissions')->insert($permissionsToInsert);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User permissions updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Assign roles to user
     */
    public function assignRoles(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
            'primary_role_id' => 'nullable|exists:roles,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($id);
        $primaryRoleId = $request->primary_role_id ?? $request->role_ids[0];

        DB::beginTransaction();
        try {
            // Clear existing user roles
            DB::table('user_roles')->where('user_id', $id)->delete();

            // Insert new roles
            $rolesToInsert = [];
            foreach ($request->role_ids as $roleId) {
                $rolesToInsert[] = [
                    'user_id' => $id,
                    'role_id' => $roleId,
                    'is_primary' => $roleId == $primaryRoleId,
                    'branch_id' => $user->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            DB::table('user_roles')->insert($rolesToInsert);

            // Update user's role field with primary role name
            $primaryRole = DB::table('roles')->where('id', $primaryRoleId)->first();
            if ($primaryRole) {
                $user->role = $primaryRole->name;
                $user->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'User roles updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update roles: ' . $e->getMessage()
            ], 500);
        }
    }
}

