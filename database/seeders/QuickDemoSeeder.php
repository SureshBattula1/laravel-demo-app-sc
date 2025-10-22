<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;
use Carbon\Carbon;

class QuickDemoSeeder extends Seeder
{
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🚀 Quick Demo Setup...');
            
            // Create branches
            $mainBranch = Branch::firstOrCreate(
                ['code' => 'MAIN001'],
                [
                    'name' => 'Main Campus',
                    'address' => '123 Education Street',
                    'city' => 'New York',
                    'state' => 'New York',
                    'country' => 'USA',
                    'pincode' => '10001',
                    'phone' => '+1-234-567-8900',
                    'email' => 'main@excellenceacademy.com',
                    'principal_name' => 'Dr. John Smith',
                    'principal_contact' => '+1-234-567-8901',
                    'principal_email' => 'principal@excellenceacademy.com',
                    'established_date' => '2010-01-01',
                    'affiliation_number' => 'AFF-2010-001',
                    'is_main_branch' => true,
                    'is_active' => true,
                    'total_capacity' => 1500,
                    'current_enrollment' => 0,
                ]
            );
            
            $this->command->info('✓ Created Main Branch');
            
            // Create grades
            for ($i = 1; $i <= 12; $i++) {
                DB::table('grades')->insertOrIgnore([
                    'value' => (string)$i,
                    'label' => 'Grade ' . $i,
                    'description' => 'Grade ' . $i . ' curriculum',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            $this->command->info('✓ Created grades 1-12');
            
            // Create login users
            $users = [
                [
                    'email' => 'admin@myschool.com',
                    'first_name' => 'Super',
                    'last_name' => 'Admin',
                    'role' => 'SuperAdmin',
                    'password' => 'Admin@123'
                ],
                [
                    'email' => 'manager@myschool.com',
                    'first_name' => 'Branch',
                    'last_name' => 'Manager',
                    'role' => 'BranchAdmin',
                    'password' => 'Manager@123'
                ],
                [
                    'email' => 'teacher@myschool.com',
                    'first_name' => 'Demo',
                    'last_name' => 'Teacher',
                    'role' => 'Teacher',
                    'password' => 'Teacher@123'
                ],
                [
                    'email' => 'student@myschool.com',
                    'first_name' => 'Demo',
                    'last_name' => 'Student',
                    'role' => 'Student',
                    'password' => 'Student@123'
                ],
                [
                    'email' => 'parent@myschool.com',
                    'first_name' => 'Demo',
                    'last_name' => 'Parent',
                    'role' => 'Parent',
                    'password' => 'Parent@123'
                ]
            ];
            
            foreach ($users as $userData) {
                $password = $userData['password'];
                unset($userData['password']);
                
                User::firstOrCreate(
                    ['email' => $userData['email']],
                    array_merge($userData, [
                        'phone' => '+1-555-' . rand(1000, 9999),
                        'password' => Hash::make($password),
                        'branch_id' => $mainBranch->id,
                        'is_active' => true,
                        'email_verified_at' => now()
                    ])
                );
            }
            
            $this->command->info('✓ Created login users');
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('✅ Quick Demo Setup Complete!');
            $this->command->info('');
            $this->command->info('🔑 LOGIN CREDENTIALS:');
            $this->command->info('═══════════════════════════════════════════');
            $this->command->info('Super Admin:  admin@myschool.com / Admin@123');
            $this->command->info('Manager:      manager@myschool.com / Manager@123');
            $this->command->info('Teacher:      teacher@myschool.com / Teacher@123');
            $this->command->info('Student:      student@myschool.com / Student@123');
            $this->command->info('Parent:       parent@myschool.com / Parent@123');
            $this->command->info('═══════════════════════════════════════════');
            $this->command->info('');
            $this->command->info('💡 You can now login and add teachers/students through the UI');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }
}

