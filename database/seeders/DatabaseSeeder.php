<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * This seeder creates:
     * - 6 Roles (Super Admin, Branch Admin, Teacher, Staff, Accountant, Student)
     * - 2 Super Admin users
     * - NO other users (teachers/students will be created by application)
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🌱 Seeding database...');
            
            // Step 1: Create Roles
            $this->createRoles();
            
            // Step 2: Create Super Admin Users
            $this->createSuperAdmins();
            
            DB::commit();
            
            $this->command->info('✅ Database seeding completed successfully!');
            $this->command->info('');
            $this->command->info('📋 Summary:');
            $this->command->info('   - 6 Roles created');
            $this->command->info('   - 2 Super Admin users created');
            $this->command->info('');
            $this->command->info('🔑 Super Admin Credentials:');
            $this->command->info('   Email: superadmin@school.com | Password: Admin@123');
            $this->command->info('   Email: admin@school.com | Password: Admin@123');
            $this->command->info('');
            $this->command->info('💡 Note: Teacher and Student roles are automatically assigned');
            $this->command->info('   when teachers/students are created via the application.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding database: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create system roles
     */
    private function createRoles(): void
    {
        $this->command->info('📝 Creating roles...');
        
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access with all permissions',
                'level' => 1,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Branch Admin',
                'slug' => 'branch-admin',
                'description' => 'Branch-level administration access',
                'level' => 2,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Teacher',
                'slug' => 'teacher',
                'description' => 'Teaching staff with student and academic access',
                'level' => 3,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Administrative staff access',
                'level' => 4,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Accounting and finance management',
                'level' => 4,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Student access to view own information',
                'level' => 5,
                'is_system_role' => true,
                'is_active' => true
            ],
        ];
        
        foreach ($roles as $roleData) {
            Role::create($roleData);
            $this->command->info("   ✓ Created role: {$roleData['name']}");
        }
    }
    
    /**
     * Create 2 Super Admin users
     */
    private function createSuperAdmins(): void
    {
        $this->command->info('👤 Creating Super Admin users...');
        
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        
        if (!$superAdminRole) {
            $this->command->error('   ❌ Super Admin role not found!');
            return;
        }
        
        $superAdmins = [
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'superadmin@school.com',
                'phone' => '1234567890',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'user_type' => 'Admin',
                'is_active' => true,
            ],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'email' => 'admin@school.com',
                'phone' => '0987654321',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'user_type' => 'Admin',
                'is_active' => true,
            ],
        ];
        
        foreach ($superAdmins as $adminData) {
            // Create user
            $user = User::create($adminData);
            
            // Assign Super Admin role
            $user->roles()->attach($superAdminRole->id, [
                'is_primary' => true,
                'branch_id' => null, // Super Admin has access to all branches
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->command->info("   ✓ Created Super Admin: {$adminData['email']}");
        }
    }
}
