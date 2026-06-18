<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanyPortalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Use the existing default company from migration (where schools are assigned)
        // This ensures the company admin can see all existing schools
        $company = DB::table('companies')->where('code', 'DEFAULT')->first();
        
        if (!$company) {
            // If DEFAULT company doesn't exist, create it
            $companyId = DB::table('companies')->insertGetId([
                'name' => 'Default Company',
                'code' => 'DEFAULT',
                'email' => 'admin@default.com',
                'phone' => '0000000000',
                'status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } else {
            $companyId = $company->id;
        }
        
        // Check if company admin user already exists
        $existingAdmin = DB::table('users')
            ->where('email', 'company.admin@demo.com')
            ->where('user_type', 'CompanyAdmin')
            ->first();
        
        if ($existingAdmin) {
            // Update company_id to match the default company (where schools are)
            if ($existingAdmin->company_id != $companyId) {
                DB::table('users')
                    ->where('id', $existingAdmin->id)
                    ->update(['company_id' => $companyId]);
                $this->command->info('Updated company admin company_id to match default company.');
            }
            $this->command->info('Company admin user already exists.');
            $this->command->info('Email: company.admin@demo.com');
            $this->command->info('Password: Admin@123');
            $this->command->info('Company ID: ' . $companyId);
            return;
        }

        // Create company admin user
        // Note: Using 'SuperAdmin' role since 'CompanyAdmin' is not in the role enum
        // The user_type field distinguishes this as a CompanyAdmin
        $adminId = DB::table('users')->insertGetId([
            'first_name' => 'Company',
            'last_name' => 'Admin',
            'email' => 'company.admin@demo.com',
            'phone' => '+1234567891',
            'password' => Hash::make('Admin@123'), // Password: Admin@123
            'role' => 'SuperAdmin', // Using SuperAdmin role (CompanyAdmin is in user_type)
            'user_type' => 'CompanyAdmin',
            'company_id' => $companyId,
            'branch_id' => null, // Company admins don't have a branch
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $companyInfo = DB::table('companies')->where('id', $companyId)->first();
        
        $this->command->info('Company Portal Setup Complete!');
        $this->command->info('================================');
        $this->command->info('Company:');
        $this->command->info('  - Name: ' . $companyInfo->name);
        $this->command->info('  - Code: ' . $companyInfo->code);
        $this->command->info('  - Email: ' . $companyInfo->email);
        $this->command->info('');
        $this->command->info('Company Admin User Created:');
        $this->command->info('  - Email: company.admin@demo.com');
        $this->command->info('  - Password: Admin@123');
        $this->command->info('  - User Type: CompanyAdmin');
        $this->command->info('  - Company ID: ' . $companyId);
        $this->command->info('');
        $this->command->info('You can now login to the company portal at: /company-portal/login');
        $this->command->info('================================');
    }
}

