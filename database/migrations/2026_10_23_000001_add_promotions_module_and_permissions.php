<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create Promotions Module
        $promotionsModule = Module::firstOrCreate(
            ['slug' => 'promotions'],
            [
                'name' => 'Promotions',
                'slug' => 'promotions',
                'icon' => 'trending_up',
                'route' => '/promotions',
                'order' => 32,
                'is_active' => true,
            ]
        );

        // 2. Create Permissions under Promotions
        $permissions = [
            [
                'action' => 'view',
                'name' => 'View Promotions',
                'slug' => 'promotions.view',
                'description' => 'View promotions dashboard, preview promotions, and view promotion history',
            ],
            [
                'action' => 'promote',
                'name' => 'Promote Students',
                'slug' => 'promotions.promote',
                'description' => 'Promote students to next grade with or without fee handling',
            ],
            [
                'action' => 'revert',
                'name' => 'Revert Promotion',
                'slug' => 'promotions.revert',
                'description' => 'Revert previously executed student promotions',
            ],
            [
                'action' => 'export',
                'name' => 'Export Promotions',
                'slug' => 'promotions.export',
                'description' => 'Export promotion summaries and reports',
            ],
        ];

        $createdPermissionIds = [];
        foreach ($permissions as $permData) {
            $permission = Permission::firstOrCreate(
                ['slug' => $permData['slug']],
                [
                    'module_id' => $promotionsModule->id,
                    'name' => $permData['name'],
                    'slug' => $permData['slug'],
                    'action' => $permData['action'],
                    'description' => $permData['description'],
                    'is_system_permission' => true,
                ]
            );
            $createdPermissionIds[] = $permission->id;
        }

        // 3. Assign to SuperAdmin and BranchAdmin
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if ($superAdminRole) {
            $superAdminRole->permissions()->syncWithoutDetaching($createdPermissionIds);
        }

        $branchAdminRoles = Role::where('slug', 'like', 'branch-admin%')
            ->orWhere('name', 'like', '%Branch Admin%')
            ->get();
        foreach ($branchAdminRoles as $branchAdminRole) {
            $branchAdminRole->permissions()->syncWithoutDetaching($createdPermissionIds);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $promotionsModule = Module::where('slug', 'promotions')->first();

        if ($promotionsModule) {
            $permissionIds = Permission::where('module_id', $promotionsModule->id)->pluck('id')->toArray();
            DB::table('role_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('user_permissions')->whereIn('permission_id', $permissionIds)->delete();
            Permission::where('module_id', $promotionsModule->id)->delete();
            $promotionsModule->delete();
        }
    }
};
