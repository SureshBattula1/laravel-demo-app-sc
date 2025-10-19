<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('════════════════════════════════════════════════════════════════');
        $this->command->info('  🚀 MySchool Management System - Database Seeder');
        $this->command->info('════════════════════════════════════════════════════════════════');
        $this->command->info('');
        
        $choice = $this->command->choice(
            'Which seeder would you like to run?',
            [
                '1' => 'Quick Seeder (Admin + 2 Branches + Sample Data)',
                '2' => 'Comprehensive Seeder (1 Main + 5 Branches + Full Realistic Data)',
                '3' => 'Both (Quick first, then Comprehensive)'
            ],
            '2' // Default to comprehensive
        );
        
        DB::beginTransaction();
        
        try {
            if ($choice === '1' || $choice === '3') {
                $this->runQuickSeeder();
            }
            
            if ($choice === '2' || $choice === '3') {
                $this->call([
                    RealtimeComprehensiveSeeder::class,
                ]);
            }
            
            DB::commit();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }
    
    private function runQuickSeeder()
    {
        $this->command->info('');
        $this->command->info('📍 Running Quick Seeder...');
        $this->command->info('');
        
        // Create Main Branch
        $mainBranch = DB::table('branches')->insertGetId([
            'name' => 'MySchool Headquarters',
            'code' => 'MS-HQ',
            'branch_type' => 'Headquarters',
            'parent_id' => null,
            'address' => '123 Education Street',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'United States',
            'postal_code' => '10001',
            'phone' => '+1234567890',
            'email' => 'hq@myschool.com',
            'website' => 'www.myschool.com',
            'principal_name' => 'Dr. John Smith',
            'principal_email' => 'principal@myschool.com',
            'principal_phone' => '+1234567891',
            'established_date' => '2010-01-01',
            'student_capacity' => 2000,
            'current_strength' => 0,
            'affiliation_board' => 'International Baccalaureate',
            'status' => 'Active',
            'is_active' => true,
            'timezone' => 'America/New_York',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create East Branch
        DB::table('branches')->insert([
            'name' => 'MySchool East Campus',
            'code' => 'MS-EAST',
            'branch_type' => 'Branch',
            'parent_id' => $mainBranch,
            'address' => '456 Learning Avenue',
            'city' => 'Boston',
            'state' => 'MA',
            'country' => 'United States',
            'postal_code' => '02101',
            'phone' => '+1234567892',
            'email' => 'east@myschool.com',
            'website' => 'www.myschool.com/east',
            'principal_name' => 'Dr. Jane Doe',
            'principal_email' => 'principal.east@myschool.com',
            'principal_phone' => '+1234567893',
            'established_date' => '2015-06-01',
            'student_capacity' => 1500,
            'current_strength' => 0,
            'affiliation_board' => 'Cambridge International',
            'status' => 'Active',
            'is_active' => true,
            'timezone' => 'America/New_York',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Create admin users
        DB::table('users')->insert([
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@myschool.com',
                'phone' => '+1234567899',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'John',
                'last_name' => 'Manager',
                'email' => 'manager@myschool.com',
                'phone' => '+1234567898',
                'password' => Hash::make('Manager@123'),
                'role' => 'BranchAdmin',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Sarah',
                'last_name' => 'Teacher',
                'email' => 'teacher@myschool.com',
                'phone' => '+1234567897',
                'password' => Hash::make('Teacher@123'),
                'role' => 'Teacher',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Alice',
                'last_name' => 'Student',
                'email' => 'student@myschool.com',
                'phone' => '+1234567896',
                'password' => Hash::make('Student@123'),
                'role' => 'Student',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'first_name' => 'Bob',
                'last_name' => 'Parent',
                'email' => 'parent@myschool.com',
                'phone' => '+1234567895',
                'password' => Hash::make('Parent@123'),
                'role' => 'Parent',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);
        
        $this->command->info('✅ Quick seeder completed!');
        $this->command->info('');
        $this->command->info('Quick Login Credentials:');
        $this->command->info('  Admin: admin@myschool.com / Admin@123');
        $this->command->info('  Manager: manager@myschool.com / Manager@123');
        $this->command->info('  Teacher: teacher@myschool.com / Teacher@123');
        $this->command->info('  Student: student@myschool.com / Student@123');
        $this->command->info('  Parent: parent@myschool.com / Parent@123');
        $this->command->info('');
    }
}
