<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AssignRolesAndPermissionsSeeder extends Seeder
{
    /**
     * Assign roles and permissions to test users
     */
    public function run(): void
    {
        $this->command->info('🔐 Assigning roles and permissions to test users...');
        
        // Get all roles
        $superAdminRole = DB::table('roles')->where('slug', 'super-admin')->first();
        $branchAdminRole = DB::table('roles')->where('slug', 'branch-admin')->first();
        $teacherRole = DB::table('roles')->where('slug', 'teacher')->first();
        $staffRole = DB::table('roles')->where('slug', 'staff')->first();
        $studentRole = DB::table('roles')->where('slug', 'student')->first();
        $parentRole = DB::table('roles')->where('slug', 'parent')->first();
        
        // If roles don't exist by slug, try by name
        if (!$teacherRole) {
            $teacherRole = DB::table('roles')->where('name', 'LIKE', '%Teacher%')->first();
        }
        if (!$staffRole) {
            $staffRole = DB::table('roles')->where('name', 'LIKE', '%Staff%')->first();
        }
        if (!$studentRole) {
            $studentRole = DB::table('roles')->where('name', 'LIKE', '%Student%')->first();
        }
        
        // User-Role mappings
        $userRoleMappings = [
            // SuperAdmin
            [
                'email' => 'admin@school.com',
                'role_id' => $superAdminRole?->id,
                'is_primary' => true
            ],
            
            // Branch 1 Users
            [
                'email' => 'branch1.admin@school.com',
                'role_id' => $branchAdminRole?->id,
                'is_primary' => true
            ],
            [
                'email' => 'branch1.teacher@school.com',
                'role_id' => $teacherRole?->id,
                'is_primary' => true
            ],
            [
                'email' => 'branch1.staff@school.com',
                'role_id' => $staffRole?->id,
                'is_primary' => true
            ],
            [
                'email' => 'branch1.student@school.com',
                'role_id' => $studentRole?->id,
                'is_primary' => true
            ],
            
            // Branch 2 Users
            [
                'email' => 'branch2.admin@school.com',
                'role_id' => $branchAdminRole?->id,
                'is_primary' => true
            ],
            [
                'email' => 'branch2.teacher@school.com',
                'role_id' => $teacherRole?->id,
                'is_primary' => true
            ],
            [
                'email' => 'branch2.staff@school.com',
                'role_id' => $staffRole?->id,
                'is_primary' => true
            ],
            [
                'email' => 'branch2.student@school.com',
                'role_id' => $studentRole?->id,
                'is_primary' => true
            ],
        ];
        
        foreach ($userRoleMappings as $mapping) {
            $user = DB::table('users')->where('email', $mapping['email'])->first();
            
            if (!$user) {
                $this->command->warn("⚠️  User not found: {$mapping['email']}");
                continue;
            }
            
            if (!$mapping['role_id']) {
                $this->command->warn("⚠️  Role not found for: {$mapping['email']}");
                continue;
            }
            
            // Check if role assignment already exists
            $existing = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role_id', $mapping['role_id'])
                ->first();
            
            if (!$existing) {
                // Assign role to user
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $mapping['role_id'],
                    'is_primary' => $mapping['is_primary'],
                    'branch_id' => $user->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $roleName = DB::table('roles')->where('id', $mapping['role_id'])->value('name');
                $this->command->info("✅ Assigned {$roleName} role to {$mapping['email']}");
            } else {
                $this->command->info("ℹ️  {$mapping['email']} already has role assigned");
            }
        }
        
        $this->command->info("\n" . str_repeat('=', 70));
        $this->command->info('🎉 Role assignment complete!');
        $this->command->info(str_repeat('=', 70));
        
        // Summary
        $this->command->info("\n📊 SUMMARY:");
        $this->command->info(str_repeat('-', 70));
        
        foreach ($userRoleMappings as $mapping) {
            $user = DB::table('users')->where('email', $mapping['email'])->first();
            if ($user) {
                $roles = DB::table('user_roles')
                    ->join('roles', 'user_roles.role_id', '=', 'roles.id')
                    ->where('user_roles.user_id', $user->id)
                    ->pluck('roles.name')
                    ->toArray();
                
                // Get permission count from roles
                $permCount = DB::table('role_permissions')
                    ->whereIn('role_id', function($q) use ($user) {
                        $q->select('role_id')
                          ->from('user_roles')
                          ->where('user_id', $user->id);
                    })
                    ->distinct()
                    ->count('permission_id');
                
                $rolesStr = implode(', ', $roles);
                $this->command->info("{$mapping['email']}: {$rolesStr} ({$permCount} permissions)");
            }
        }
        
        $this->command->info("\n✅ Users can now login and see menus!");
        $this->command->info("🔄 Remember to clear browser cache and logout/login!");
    }
}

