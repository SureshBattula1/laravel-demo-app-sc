<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PermissionManagementController extends Controller
{
    /**
     * Get all permissions with pagination
     */
    public function index(Request $request)
    {
        $perPage = $request->get('per_page', 10);
        $search = $request->get('search');
        $module = $request->get('module');
        $action = $request->get('action');
        $isSystemPermission = $request->get('is_system_permission');
        $slug = $request->get('slug');
        $sortBy = $request->get('sort_by', 'permissions.id');
        $sortDirection = $request->get('sort_direction', 'asc');

        $query = DB::table('permissions')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
            ->select(
                'permissions.id',
                'permissions.name',
                'permissions.slug',
                'permissions.slug as display_name',
                'permissions.description',
                'permissions.action',
                'permissions.is_system_permission',
                'modules.name as module',
                'modules.slug as module_slug',
                'permissions.created_at',
                'permissions.updated_at'
            );

        // Apply search filter
        if ($search) {
            $query->where(function($q) use ($search) {
                $q->where('permissions.name', 'like', "%{$search}%")
                  ->orWhere('permissions.slug', 'like', "%{$search}%")
                  ->orWhere('modules.name', 'like', "%{$search}%")
                  ->orWhere('permissions.action', 'like', "%{$search}%");
            });
        }

        // Apply module filter
        if ($module) {
            $query->where('modules.slug', $module);
        }

        // Apply action filter
        if ($action) {
            $query->where('permissions.action', $action);
        }

        // Apply system permission filter
        if ($isSystemPermission !== null && $isSystemPermission !== '') {
            $query->where('permissions.is_system_permission', (bool)$isSystemPermission);
        }

        // Apply slug filter
        if ($slug) {
            $query->where('permissions.slug', 'like', "%{$slug}%");
        }

        // Apply sorting
        if ($sortBy === 'module') {
            $query->orderBy('modules.name', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }

        $permissions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions
        ]);
    }

    /**
     * Get all permissions without pagination
     */
    public function all()
    {
        $permissions = DB::table('permissions')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
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
            ->orderBy('modules.name')
            ->orderBy('permissions.name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Permissions retrieved successfully',
            'data' => $permissions
        ]);
    }

    /**
     * Get permissions grouped by module
     */
    public function byModule()
    {
        $permissions = DB::table('permissions')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
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
            ->orderBy('modules.name')
            ->orderBy('permissions.name')
            ->get();

        $grouped = $permissions->groupBy('module');

        return response()->json([
            'success' => true,
            'message' => 'Permissions grouped by module',
            'data' => $grouped
        ]);
    }

    /**
     * Get single permission
     */
    public function show($id)
    {
        $permission = DB::table('permissions')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
            ->where('permissions.id', $id)
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
            ->first();

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Permission retrieved successfully',
            'data' => $permission
        ]);
    }

    /**
     * Create new permission
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'module_id' => 'required|exists:modules,id',
            'action' => 'required|string|max:255',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate slug from module and action
        $slug = $this->generatePermissionSlug($request->module_id, $request->action, $request->name);

        $permissionId = DB::table('permissions')->insertGetId([
            'module_id' => $request->module_id,
            'name' => $request->name,
            'slug' => $slug,
            'action' => $request->action,
            'description' => $request->description,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $permission = DB::table('permissions')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
            ->where('permissions.id', $permissionId)
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
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission
        ], 201);
    }

    /**
     * Update permission
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'module_id' => 'sometimes|exists:modules,id',
            'action' => 'sometimes|string|max:255',
            'description' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = [
            'updated_at' => now()
        ];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('module_id')) {
            $updateData['module_id'] = $request->module_id;
        }
        if ($request->has('action')) {
            $updateData['action'] = $request->action;
        }

        // Regenerate slug if module_id or action changed
        if ($request->has('module_id') || $request->has('action')) {
            $current = DB::table('permissions')->where('id', $id)->first();
            $moduleId = $request->module_id ?? $current->module_id;
            $action = $request->action ?? $current->action;
            $name = $request->name ?? $current->name;
            $updateData['slug'] = $this->generatePermissionSlug($moduleId, $action, $name);
        }

        DB::table('permissions')->where('id', $id)->update($updateData);

        $permission = DB::table('permissions')
            ->leftJoin('modules', 'permissions.module_id', '=', 'modules.id')
            ->where('permissions.id', $id)
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
            ->first();

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission
        ]);
    }

    /**
     * Delete permission
     */
    public function destroy($id)
    {
        // Check if permission is being used
        $rolesCount = DB::table('role_permissions')->where('permission_id', $id)->count();

        if ($rolesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete permission. It is assigned to {$rolesCount} role(s)"
            ], 422);
        }

        DB::table('permissions')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully'
        ]);
    }

    /**
     * Generate permission slug
     */
    private function generatePermissionSlug($moduleId, $action, $name)
    {
        // Get module slug
        $module = DB::table('modules')->where('id', $moduleId)->first();
        $moduleSlug = $module ? $module->slug : 'general';
        
        // Create base slug: module.action (e.g., students.view)
        $slug = $moduleSlug . '.' . strtolower($action);
        
        // Ensure uniqueness
        $originalSlug = $slug;
        $counter = 1;
        
        while (DB::table('permissions')->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}

