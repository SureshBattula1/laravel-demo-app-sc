<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ModuleController extends Controller
{
    /**
     * Get all modules
     */
    public function index()
    {
        $modules = DB::table('modules')
            ->orderBy('order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'message' => 'Modules retrieved successfully',
            'data' => $modules
        ]);
    }

    /**
     * Get single module
     */
    public function show($id)
    {
        $module = DB::table('modules')->where('id', $id)->first();

        if (!$module) {
            return response()->json([
                'success' => false,
                'message' => 'Module not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Module retrieved successfully',
            'data' => $module
        ]);
    }

    /**
     * Create new module
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'route' => 'nullable|string',
            'order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate slug
        $slug = $this->generateSlug($request->name);

        $moduleId = DB::table('modules')->insertGetId([
            'name' => $request->name,
            'slug' => $slug,
            'description' => $request->description,
            'icon' => $request->icon,
            'route' => $request->route,
            'order' => $request->order ?? 0,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $module = DB::table('modules')->where('id', $moduleId)->first();

        return response()->json([
            'success' => true,
            'message' => 'Module created successfully',
            'data' => $module
        ], 201);
    }

    /**
     * Update module
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string',
            'route' => 'nullable|string',
            'order' => 'nullable|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = ['updated_at' => now()];

        if ($request->has('name')) {
            $updateData['name'] = $request->name;
            $updateData['slug'] = $this->generateSlug($request->name);
        }
        if ($request->has('description')) {
            $updateData['description'] = $request->description;
        }
        if ($request->has('icon')) {
            $updateData['icon'] = $request->icon;
        }
        if ($request->has('route')) {
            $updateData['route'] = $request->route;
        }
        if ($request->has('order')) {
            $updateData['order'] = $request->order;
        }

        DB::table('modules')->where('id', $id)->update($updateData);

        $module = DB::table('modules')->where('id', $id)->first();

        return response()->json([
            'success' => true,
            'message' => 'Module updated successfully',
            'data' => $module
        ]);
    }

    /**
     * Delete module
     */
    public function destroy($id)
    {
        // Check if module has permissions
        $permissionsCount = DB::table('permissions')->where('module_id', $id)->count();

        if ($permissionsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "Cannot delete module. It has {$permissionsCount} permission(s)"
            ], 422);
        }

        DB::table('modules')->where('id', $id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Module deleted successfully'
        ]);
    }

    /**
     * Generate slug from name
     */
    private function generateSlug($name)
    {
        $slug = strtolower($name);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');
        
        $originalSlug = $slug;
        $counter = 1;
        
        while (DB::table('modules')->where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
}

