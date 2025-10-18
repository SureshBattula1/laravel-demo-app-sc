<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PermissionController extends Controller
{
    /**
     * Get all roles with their permissions
     */
    public function getRoles()
    {
        try {
            $roles = Role::with(['permissions.module'])
                ->where('is_active', true)
                ->orderBy('level', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $roles
            ]);
        } catch (\Exception $e) {
            Log::error('Get roles error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch roles',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get all modules with permissions
     */
    public function getModules()
    {
        try {
            $modules = Module::with('permissions')
                ->active()
                ->ordered()
                ->get();

            return response()->json([
                'success' => true,
                'data' => $modules
            ]);
        } catch (\Exception $e) {
            Log::error('Get modules error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch modules',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get all permissions grouped by module
     */
    public function getPermissions()
    {
        try {
            $modules = Module::with('permissions')
                ->active()
                ->ordered()
                ->get()
                ->map(function($module) {
                    return [
                        'module' => $module->only(['id', 'name', 'slug', 'icon', 'route']),
                        'permissions' => $module->permissions->map(function($perm) {
                            return [
                                'id' => $perm->id,
                                'name' => $perm->name,
                                'slug' => $perm->slug,
                                'action' => $perm->action
                            ];
                        })
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $modules
            ]);
        } catch (\Exception $e) {
            Log::error('Get permissions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch permissions',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get user's effective permissions
     */
    public function getUserPermissions($userId, Request $request)
    {
        try {
            $user = User::findOrFail($userId);
            $branchId = $request->input('branch_id');

            $permissions = $user->getAllPermissions($branchId);
            
            // Group by module for easier frontend consumption
            $groupedPermissions = $permissions->groupBy('module_id')->map(function($perms) {
                return $perms->map(function($perm) {
                    return [
                        'id' => $perm->id,
                        'name' => $perm->name,
                        'slug' => $perm->slug,
                        'action' => $perm->action
                    ];
                });
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user->only(['id', 'first_name', 'last_name', 'email', 'role']),
                    'permissions' => $groupedPermissions,
                    'permission_slugs' => $permissions->pluck('slug')->toArray()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get user permissions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user permissions',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Sync role permissions
     */
    public function syncRolePermissions(Request $request, $roleId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'permission_ids' => 'required|array',
                'permission_ids.*' => 'exists:permissions,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $role = Role::findOrFail($roleId);

            if ($role->is_system_role && !auth()->user()->isSuperAdmin()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot modify system roles'
                ], 403);
            }

            DB::beginTransaction();

            $role->syncPermissions($request->permission_ids);

            DB::commit();

            Log::info('Role permissions updated', [
                'role_id' => $roleId,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Role permissions updated successfully',
                'data' => $role->load('permissions')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sync role permissions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update permissions',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Grant user-specific permission
     */
    public function grantUserPermission(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'permission_id' => 'required|exists:permissions,id',
                'branch_id' => 'nullable|exists:branches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            $user->permissions()->syncWithoutDetaching([
                $request->permission_id => [
                    'granted' => true,
                    'branch_id' => $request->branch_id
                ]
            ]);

            DB::commit();

            Log::info('Permission granted to user', [
                'user_id' => $userId,
                'permission_id' => $request->permission_id,
                'granted_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission granted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Grant user permission error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to grant permission',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Revoke user-specific permission
     */
    public function revokeUserPermission(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'permission_id' => 'required|exists:permissions,id',
                'branch_id' => 'nullable|exists:branches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $user = User::findOrFail($userId);

            DB::beginTransaction();

            $user->permissions()->syncWithoutDetaching([
                $request->permission_id => [
                    'granted' => false,
                    'branch_id' => $request->branch_id
                ]
            ]);

            DB::commit();

            Log::info('Permission revoked from user', [
                'user_id' => $userId,
                'permission_id' => $request->permission_id,
                'revoked_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Permission revoked successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Revoke user permission error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to revoke permission',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create a new role
     */
    public function createRole(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:roles',
                'slug' => 'required|string|max:255|unique:roles',
                'description' => 'nullable|string',
                'level' => 'required|integer|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $role = Role::create($request->only(['name', 'slug', 'description', 'level']));

            DB::commit();

            Log::info('Role created', ['role_id' => $role->id, 'created_by' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'Role created successfully',
                'data' => $role
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create role error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create role',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create a new module
     */
    public function createModule(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'slug' => 'required|string|max:255|unique:modules',
                'description' => 'nullable|string',
                'icon' => 'nullable|string|max:50',
                'route' => 'nullable|string|max:255',
                'order' => 'nullable|integer',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $module = Module::create($request->only(['name', 'slug', 'description', 'icon', 'route', 'order']));

            DB::commit();

            Log::info('Module created', ['module_id' => $module->id, 'created_by' => auth()->id()]);

            return response()->json([
                'success' => true,
                'message' => 'Module created successfully',
                'data' => $module
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create module error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create module',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

