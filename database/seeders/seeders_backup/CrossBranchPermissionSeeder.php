<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CrossBranchPermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create System module
        $systemModule = DB::table('modules')->where('slug', 'system')->first();
        
        if (!$systemModule) {
            $moduleId = DB::table('modules')->insertGetId([
                'name' => 'System',
                'slug' => 'system',
                'description' => 'System-level permissions',
                'icon' => 'settings',
                'route' => '/system',
                'order' => 999,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $moduleId = $systemModule->id;
        }
        
        // Create cross-branch access permission
        $permissionExists = DB::table('permissions')
            ->where('slug', 'system.cross_branch_access')
            ->exists();
            
        if (!$permissionExists) {
            DB::table('permissions')->insert([
                'module_id' => $moduleId,
                'name' => 'Cross-Branch Access',
                'slug' => 'system.cross_branch_access',
                'action' => 'cross_branch_access',
                'description' => 'Allows user to access and manage data across all branches',
                'is_system_permission' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->command->info('✅ Created system.cross_branch_access permission');
        } else {
            $this->command->info('ℹ️  system.cross_branch_access permission already exists');
        }
        
        // Create granular permissions
        $granularPermissions = [
            [
                'name' => 'View All Branches Data',
                'slug' => 'system.view_all_branches',
                'action' => 'view_all_branches',
                'description' => 'Can view data from all branches (read-only)',
            ],
            [
                'name' => 'Manage All Branches Data',
                'slug' => 'system.manage_all_branches',
                'action' => 'manage_all_branches',
                'description' => 'Can create, edit, and delete data across all branches',
            ]
        ];
        
        foreach ($granularPermissions as $perm) {
            $exists = DB::table('permissions')->where('slug', $perm['slug'])->exists();
            if (!$exists) {
                DB::table('permissions')->insert([
                    'module_id' => $moduleId,
                    'name' => $perm['name'],
                    'slug' => $perm['slug'],
                    'action' => $perm['action'],
                    'description' => $perm['description'],
                    'is_system_permission' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $this->command->info("✅ Created {$perm['slug']} permission");
            }
        }
    }
}

