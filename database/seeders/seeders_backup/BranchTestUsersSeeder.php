<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class BranchTestUsersSeeder extends Seeder
{
    /**
     * Create test users for both branches with different roles
     */
    public function run(): void
    {
        $this->command->info('🚀 Creating test users for branch filtering...');
        
        // Get branch IDs
        $branch1 = DB::table('branches')->where('code', 'MAIN')->first();
        $branch2 = DB::table('branches')->where('code', 'TEST')->first();
        
        if (!$branch1 || !$branch2) {
            $this->command->error('❌ Branches not found! Make sure you have 2 branches.');
            return;
        }
        
        $this->command->info("Branch 1: {$branch1->name} (ID: {$branch1->id})");
        $this->command->info("Branch 2: {$branch2->name} (ID: {$branch2->id})");
        
        // Default password for all test users
        $password = Hash::make('password123');
        
        // Test users to create
        $users = [
            // BRANCH 1 USERS (Main Campus)
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@school.com',
                'role' => 'SuperAdmin',
                'branch_id' => $branch1->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch1',
                'last_name' => 'Admin',
                'email' => 'branch1.admin@school.com',
                'role' => 'BranchAdmin',
                'branch_id' => $branch1->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch1',
                'last_name' => 'Teacher',
                'email' => 'branch1.teacher@school.com',
                'role' => 'Teacher',
                'branch_id' => $branch1->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch1',
                'last_name' => 'Staff',
                'email' => 'branch1.staff@school.com',
                'role' => 'Staff',
                'branch_id' => $branch1->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch1',
                'last_name' => 'Student',
                'email' => 'branch1.student@school.com',
                'role' => 'Student',
                'branch_id' => $branch1->id,
                'password' => $password,
                'is_active' => true,
            ],
            
            // BRANCH 2 USERS (TECH BRANCH)
            [
                'first_name' => 'Branch2',
                'last_name' => 'Admin',
                'email' => 'branch2.admin@school.com',
                'role' => 'BranchAdmin',
                'branch_id' => $branch2->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch2',
                'last_name' => 'Teacher',
                'email' => 'branch2.teacher@school.com',
                'role' => 'Teacher',
                'branch_id' => $branch2->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch2',
                'last_name' => 'Staff',
                'email' => 'branch2.staff@school.com',
                'role' => 'Staff',
                'branch_id' => $branch2->id,
                'password' => $password,
                'is_active' => true,
            ],
            [
                'first_name' => 'Branch2',
                'last_name' => 'Student',
                'email' => 'branch2.student@school.com',
                'role' => 'Student',
                'branch_id' => $branch2->id,
                'password' => $password,
                'is_active' => true,
            ],
        ];
        
        foreach ($users as $userData) {
            // Check if user already exists
            $existing = DB::table('users')->where('email', $userData['email'])->first();
            
            if ($existing) {
                // Update existing user
                DB::table('users')
                    ->where('email', $userData['email'])
                    ->update([
                        'first_name' => $userData['first_name'],
                        'last_name' => $userData['last_name'],
                        'role' => $userData['role'],
                        'branch_id' => $userData['branch_id'],
                        'password' => $userData['password'],
                        'is_active' => $userData['is_active'],
                        'updated_at' => now()
                    ]);
                $this->command->info("✅ Updated: {$userData['email']} ({$userData['role']}) - Branch {$userData['branch_id']}");
            } else {
                // Create new user
                DB::table('users')->insert(array_merge($userData, [
                    'created_at' => now(),
                    'updated_at' => now(),
                    'email_verified_at' => now()
                ]));
                $this->command->info("✅ Created: {$userData['email']} ({$userData['role']}) - Branch {$userData['branch_id']}");
            }
        }
        
        $this->command->info("\n" . str_repeat('=', 70));
        $this->command->info('🎉 Test users created successfully!');
        $this->command->info(str_repeat('=', 70));
        
        // Display credentials
        $this->command->info("\n📋 TEST USER CREDENTIALS:");
        $this->command->info(str_repeat('-', 70));
        $this->command->info("\n🏢 BRANCH 1 ({$branch1->name}):");
        $this->command->info("  1. SuperAdmin:     admin@school.com           / password123");
        $this->command->info("  2. BranchAdmin:    branch1.admin@school.com   / password123");
        $this->command->info("  3. Teacher:        branch1.teacher@school.com / password123");
        $this->command->info("  4. Staff:          branch1.staff@school.com   / password123");
        $this->command->info("  5. Student:        branch1.student@school.com / password123");
        
        $this->command->info("\n🏢 BRANCH 2 ({$branch2->name}):");
        $this->command->info("  1. BranchAdmin:    branch2.admin@school.com   / password123");
        $this->command->info("  2. Teacher:        branch2.teacher@school.com / password123");
        $this->command->info("  3. Staff:          branch2.staff@school.com   / password123");
        $this->command->info("  4. Student:        branch2.student@school.com / password123");
        
        $this->command->info("\n" . str_repeat('=', 70));
        $this->command->info("🧪 TESTING GUIDE:");
        $this->command->info(str_repeat('-', 70));
        $this->command->info("1. Clear browser cache: localStorage.clear() in console");
        $this->command->info("2. Login as branch1.teacher@school.com");
        $this->command->info("   ✅ Should see: 4 sections from Branch 1");
        $this->command->info("   ❌ Should NOT see: 1 section from Branch 2");
        $this->command->info("");
        $this->command->info("3. Logout, clear cache, login as branch2.teacher@school.com");
        $this->command->info("   ✅ Should see: 1 section from Branch 2");
        $this->command->info("   ❌ Should NOT see: 4 sections from Branch 1");
        $this->command->info("");
        $this->command->info("4. Logout, clear cache, login as admin@school.com");
        $this->command->info("   ✅ Should see: ALL 5 sections from both branches");
        $this->command->info(str_repeat('=', 70) . "\n");
    }
}

