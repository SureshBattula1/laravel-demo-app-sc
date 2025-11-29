<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class AssignSuperAdminPermissions extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Find or create SuperAdmin role
        $superAdminRole = Role::firstOrCreate(
            ['slug' => 'super-admin'],
            [
                'name' => 'Super Admin',
                'description' => 'Full system access with all permissions',
                'level' => 1,
                'is_system_role' => true
            ]
        );

        // Assign ALL permissions to SuperAdmin role
        $allPermissions = Permission::all()->pluck('id')->toArray();
        $superAdminRole->syncPermissions($allPermissions);
        
        echo "✓ Assigned " . count($allPermissions) . " permissions to Super Admin role\n";

        // Find all SuperAdmin users and assign the role
        $superAdminUsers = User::where('role', 'SuperAdmin')->get();
        
        foreach ($superAdminUsers as $user) {
            // Check if user already has the role
            if (!$user->roles()->where('role_id', $superAdminRole->id)->exists()) {
                $user->roles()->attach($superAdminRole->id, [
                    'is_primary' => true,
                    'branch_id' => $user->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                echo "✓ Assigned Super Admin role to user: {$user->email}\n";
            } else {
                echo "  - User {$user->email} already has Super Admin role\n";
            }
            
            // Also directly assign all permissions to the user (belt and suspenders approach)
            DB::table('user_permissions')->where('user_id', $user->id)->delete();
            
            $userPermissions = [];
            foreach ($allPermissions as $permissionId) {
                $userPermissions[] = [
                    'user_id' => $user->id,
                    'permission_id' => $permissionId,
                    'branch_id' => null, // SuperAdmin has access to all branches
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            
            DB::table('user_permissions')->insert($userPermissions);
            echo "✓ Assigned " . count($userPermissions) . " direct permissions to user: {$user->email}\n";
        }
        
        echo "\n✅ SuperAdmin permissions assignment complete!\n";
        echo "   Total SuperAdmin users: " . $superAdminUsers->count() . "\n";
        echo "   Total permissions assigned: " . count($allPermissions) . "\n";
    }
}



