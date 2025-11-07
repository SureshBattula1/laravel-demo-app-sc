<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            // Create Main Branch
            $mainBranch = Branch::firstOrCreate(
                ['code' => 'MAIN001'],
                [
                'name' => 'Main Campus',
                'address' => '123 Education Street',
                'city' => 'New York',
                'state' => 'New York',
                'country' => 'USA',
                'pincode' => '10001',
                'phone' => '+1234567890',
                'email' => 'main@myschool.com',
                'principal_name' => 'Dr. John Smith',
                'principal_contact' => '+1234567891',
                'principal_email' => 'principal@myschool.com',
                'established_date' => '2010-01-01',
                'affiliation_number' => 'AFF-2010-001',
                'is_main_branch' => true,
                'is_active' => true,
                'settings' => [
                    'academicYearStart' => 'April',
                    'academicYearEnd' => 'March',
                    'workingDays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                    'schoolTimings' => [
                        'startTime' => '08:00',
                        'endTime' => '15:00'
                    ],
                    'currency' => 'USD',
                    'language' => 'English',
                    'timezone' => 'America/New_York'
                ]
                ]
            );

            // Create Second Branch
            $secondBranch = Branch::firstOrCreate(
                ['code' => 'EAST001'],
                [
                'name' => 'East Campus',
                'address' => '456 Learning Avenue',
                'city' => 'Boston',
                'state' => 'Massachusetts',
                'country' => 'USA',
                'pincode' => '02101',
                'phone' => '+1234567892',
                'email' => 'east@myschool.com',
                'principal_name' => 'Dr. Jane Doe',
                'principal_contact' => '+1234567893',
                'principal_email' => 'principal.east@myschool.com',
                'established_date' => '2015-06-01',
                'affiliation_number' => 'AFF-2015-002',
                'is_main_branch' => false,
                'is_active' => true,
                'settings' => [
                    'academicYearStart' => 'April',
                    'academicYearEnd' => 'March',
                    'workingDays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                    'schoolTimings' => [
                        'startTime' => '08:30',
                        'endTime' => '15:30'
                    ],
                    'currency' => 'USD',
                    'language' => 'English',
                    'timezone' => 'America/New_York'
                ]
                ]
            );

            // Get roles (must exist from PermissionSeeder)
            $superAdminRole = Role::where('slug', 'super-admin')->first();
            $branchAdminRole = Role::where('slug', 'branch-admin')->first();
            $teacherRole = Role::where('slug', 'teacher')->first();
            $studentRole = Role::where('slug', 'student')->first();
            $parentRole = Role::where('slug', 'parent')->first();

            // Create Super Admin User
            $adminUser = User::firstOrCreate(
                ['email' => 'admin@myschool.com'],
                [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@myschool.com',
                'phone' => '+1234567899',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'email_verified_at' => now()
                ]
            );
            // Assign role in user_roles table
            if ($superAdminRole && !$adminUser->roles()->where('roles.id', $superAdminRole->id)->exists()) {
                $adminUser->roles()->attach($superAdminRole->id, [
                    'is_primary' => true,
                    'branch_id' => $mainBranch->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create Branch Admin for Main Campus
            $managerUser = User::firstOrCreate(
                ['email' => 'manager@myschool.com'],
                [
                'first_name' => 'John',
                'last_name' => 'Manager',
                'email' => 'manager@myschool.com',
                'phone' => '+1234567898',
                'password' => Hash::make('Manager@123'),
                'role' => 'BranchAdmin',
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'email_verified_at' => now()
                ]
            );
            // Assign role in user_roles table
            if ($branchAdminRole && !$managerUser->roles()->where('roles.id', $branchAdminRole->id)->exists()) {
                $managerUser->roles()->attach($branchAdminRole->id, [
                    'is_primary' => true,
                    'branch_id' => $mainBranch->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create Sample Teacher
            $teacherUser = User::firstOrCreate(
                ['email' => 'teacher@myschool.com'],
                [
                'first_name' => 'Sarah',
                'last_name' => 'Teacher',
                'email' => 'teacher@myschool.com',
                'phone' => '+1234567897',
                'password' => Hash::make('Teacher@123'),
                'role' => 'Teacher',
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'email_verified_at' => now()
                ]
            );
            // Assign role in user_roles table
            if ($teacherRole && !$teacherUser->roles()->where('roles.id', $teacherRole->id)->exists()) {
                $teacherUser->roles()->attach($teacherRole->id, [
                    'is_primary' => true,
                    'branch_id' => $mainBranch->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create Sample Student
            $studentUser = User::firstOrCreate(
                ['email' => 'student@myschool.com'],
                [
                'first_name' => 'Alice',
                'last_name' => 'Student',
                'email' => 'student@myschool.com',
                'phone' => '+1234567896',
                'password' => Hash::make('Student@123'),
                'role' => 'Student',
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'email_verified_at' => now()
                ]
            );
            // Assign role in user_roles table
            if ($studentRole && !$studentUser->roles()->where('roles.id', $studentRole->id)->exists()) {
                $studentUser->roles()->attach($studentRole->id, [
                    'is_primary' => true,
                    'branch_id' => $mainBranch->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create Sample Parent
            $parentUser = User::firstOrCreate(
                ['email' => 'parent@myschool.com'],
                [
                'first_name' => 'Bob',
                'last_name' => 'Parent',
                'email' => 'parent@myschool.com',
                'phone' => '+1234567895',
                'password' => Hash::make('Parent@123'),
                'role' => 'Parent',
                'branch_id' => $mainBranch->id,
                'is_active' => true,
                'email_verified_at' => now()
                ]
            );
            // Assign role in user_roles table
            if ($parentRole && !$parentUser->roles()->where('roles.id', $parentRole->id)->exists()) {
                $parentUser->roles()->attach($parentRole->id, [
                    'is_primary' => true,
                    'branch_id' => $mainBranch->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            // Seed comprehensive demo data
            $this->call([
                DemoDataSeeder::class,
                // DashboardDataSeeder::class, // Optional: uncomment if needed
            ]);

            $this->command->info('✅ Database seeded successfully!');
            $this->command->info('');
            $this->command->info('Default Login Credentials:');
            $this->command->info('================================');
            $this->command->info('Super Admin:');
            $this->command->info('Email: admin@myschool.com');
            $this->command->info('Password: Admin@123');
            $this->command->info('');
            $this->command->info('Branch Admin:');
            $this->command->info('Email: manager@myschool.com');
            $this->command->info('Password: Manager@123');
            $this->command->info('');
            $this->command->info('Teacher:');
            $this->command->info('Email: teacher@myschool.com');
            $this->command->info('Password: Teacher@123');
            $this->command->info('');
            $this->command->info('Student:');
            $this->command->info('Email: student@myschool.com');
            $this->command->info('Password: Student@123');

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }
}
