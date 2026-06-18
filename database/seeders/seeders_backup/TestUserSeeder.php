<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class TestUserSeeder extends Seeder
{
    /**
     * Create test users with proper role assignments
     */
    public function run(): void
    {
        // Create a default branch if it doesn't exist
        $branch = DB::table('branches')->first();
        
        if (!$branch) {
            $this->command->info('Creating default branch...');
            $branchId = DB::table('branches')->insertGetId([
                'name' => 'Main Campus',
                'code' => 'MAIN',
                'branch_type' => 'HeadOffice',
                'address' => '123 School Street',
                'city' => 'Mumbai',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400001',
                'phone' => '1234567890',
                'email' => 'info@school.com',
                'status' => 'Active',
                'is_active' => true,
                'is_main_branch' => true,
                'total_capacity' => 1000,
                'current_enrollment' => 0,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $this->command->info('✅ Default branch created (ID: ' . $branchId . ')');
        } else {
            $branchId = $branch->id;
            $this->command->info('Using existing branch (ID: ' . $branchId . ')');
        }
        
        // Get roles
        $studentRole = Role::where('slug', 'student')->first();
        $teacherRole = Role::where('slug', 'teacher')->first();
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        $accountantRole = Role::where('slug', 'accountant')->first();
        $branchAdminRole = Role::where('slug', 'branch-admin')->first();
        
        // Create/update SuperAdmin user
        $superAdmin = User::updateOrCreate(
            ['email' => 'admin@school.com'],
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'role' => 'SuperAdmin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'branch_id' => $branchId
            ]
        );
        
        // Assign role
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $superAdmin->id, 'role_id' => $superAdminRole->id],
            ['is_primary' => true, 'branch_id' => $branchId, 'created_at' => now(), 'updated_at' => now()]
        );
        
        // Create/update Student user
        $student = User::updateOrCreate(
            ['email' => 'student@school.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'Student',
                'role' => 'Student',
                'password' => Hash::make('password'),
                'is_active' => true,
                'branch_id' => $branchId
            ]
        );
        
        // Assign role
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $student->id, 'role_id' => $studentRole->id],
            ['is_primary' => true, 'branch_id' => $branchId, 'created_at' => now(), 'updated_at' => now()]
        );
        
        // Create/update Teacher user
        $teacher = User::updateOrCreate(
            ['email' => 'teacher@school.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'Teacher',
                'role' => 'Teacher',
                'password' => Hash::make('password'),
                'is_active' => true,
                'branch_id' => $branchId
            ]
        );
        
        // Assign role
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $teacher->id, 'role_id' => $teacherRole->id],
            ['is_primary' => true, 'branch_id' => $branchId, 'created_at' => now(), 'updated_at' => now()]
        );
        
        // Create/update Accountant user (using Staff role)
        $accountant = User::updateOrCreate(
            ['email' => 'accountant@school.com'],
            [
                'first_name' => 'Test',
                'last_name' => 'Accountant',
                'role' => 'Staff', // Using Staff role in users table
                'password' => Hash::make('password'),
                'is_active' => true,
                'branch_id' => $branchId
            ]
        );
        
        // Assign role
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $accountant->id, 'role_id' => $accountantRole->id],
            ['is_primary' => true, 'branch_id' => $branchId, 'created_at' => now(), 'updated_at' => now()]
        );
        
        // Create/update Branch Admin user
        $branchAdmin = User::updateOrCreate(
            ['email' => 'branchadmin@school.com'],
            [
                'first_name' => 'Branch',
                'last_name' => 'Admin',
                'role' => 'BranchAdmin',
                'password' => Hash::make('password'),
                'is_active' => true,
                'branch_id' => $branchId
            ]
        );
        
        // Assign role
        DB::table('user_roles')->updateOrInsert(
            ['user_id' => $branchAdmin->id, 'role_id' => $branchAdminRole->id],
            ['is_primary' => true, 'branch_id' => $branchId, 'created_at' => now(), 'updated_at' => now()]
        );
        
        $this->command->info('✅ Test users created successfully!');
        $this->command->info('');
        $this->command->info('📋 Login credentials (all passwords: password):');
        $this->command->info('  🔑 SuperAdmin: admin@school.com');
        $this->command->info('  🎓 Student: student@school.com');
        $this->command->info('  👨‍🏫 Teacher: teacher@school.com');
        $this->command->info('  💰 Accountant: accountant@school.com');
        $this->command->info('  🏢 BranchAdmin: branchadmin@school.com');
        $this->command->info('');
        $this->command->info('🔐 All users have been assigned their respective roles!');
    }
}

