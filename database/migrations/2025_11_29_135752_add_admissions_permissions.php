<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create Admissions Module
        $admissionsModule = Module::firstOrCreate(
            ['slug' => 'admissions'],
            [
                'name' => 'Admissions',
                'icon' => 'how_to_reg',
                'route' => '/admissions',
                'order' => 3, // After Branches, before Teachers
                'is_active' => true
            ]
        );

        // Create Admissions Permissions
        $permissions = [
            ['action' => 'view', 'name' => 'View Admissions', 'slug' => 'admissions.view'],
            ['action' => 'create', 'name' => 'Create Admissions', 'slug' => 'admissions.create'],
            ['action' => 'edit', 'name' => 'Edit Admissions', 'slug' => 'admissions.edit'],
            ['action' => 'delete', 'name' => 'Delete Admissions', 'slug' => 'admissions.delete'],
            ['action' => 'export', 'name' => 'Export Admissions', 'slug' => 'admissions.export']
        ];

        foreach ($permissions as $permData) {
            Permission::firstOrCreate(
                [
                    'slug' => $permData['slug']
                ],
                [
                    'module_id' => $admissionsModule->id,
                    'name' => $permData['name'],
                    'action' => $permData['action'],
                    'is_system_permission' => true
                ]
            );
        }

        // Assign permissions to SuperAdmin and BranchAdmin roles
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $branchAdminRole = Role::where('slug', 'branch-admin')->first();

        if ($superAdminRole) {
            $admissionPermissionIds = Permission::where('module_id', $admissionsModule->id)->pluck('id')->toArray();
            $superAdminRole->permissions()->syncWithoutDetaching($admissionPermissionIds);
        }

        if ($branchAdminRole) {
            $admissionPermissionIds = Permission::where('module_id', $admissionsModule->id)->pluck('id')->toArray();
            $branchAdminRole->permissions()->syncWithoutDetaching($admissionPermissionIds);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Find and delete admissions permissions
        $admissionsModule = Module::where('slug', 'admissions')->first();
        
        if ($admissionsModule) {
            // Remove from role_permissions
            $permissionIds = Permission::where('module_id', $admissionsModule->id)->pluck('id')->toArray();
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
            
            // Delete permissions
            Permission::where('module_id', $admissionsModule->id)->delete();
            
            // Delete module
            $admissionsModule->delete();
        }
    }
};
