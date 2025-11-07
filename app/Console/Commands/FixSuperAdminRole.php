<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FixSuperAdminRole extends Command
{
    protected $signature = 'fix:super-admin';
    protected $description = 'Fix Super Admin role assignment after running PermissionSeeder';

    public function handle()
    {
        $this->info('🔍 Finding Super Admin users and assigning roles...');
        $this->newLine();

        // Find super-admin role
        $superAdminRole = DB::table('roles')->where('slug', 'super-admin')->first();
        
        if (!$superAdminRole) {
            $this->error('❌ Super Admin role not found! Please run PermissionSeeder first.');
            return 1;
        }

        $this->info("✅ Found Super Admin Role (ID: {$superAdminRole->id})");
        $this->newLine();

        // Find all users with role='SuperAdmin'
        $superAdminUsers = DB::table('users')
            ->where('role', 'SuperAdmin')
            ->get(['id', 'first_name', 'last_name', 'email', 'role', 'branch_id']);

        if ($superAdminUsers->isEmpty()) {
            $this->warn('⚠️  No users with role="SuperAdmin" found.');
            $this->newLine();
            
            // Show all users
            $this->info('📋 All users in database:');
            $allUsers = DB::table('users')->get(['id', 'first_name', 'last_name', 'email', 'role']);
            
            $headers = ['ID', 'Name', 'Email', 'Role'];
            $rows = $allUsers->map(function($user) {
                return [
                    $user->id,
                    "{$user->first_name} {$user->last_name}",
                    $user->email,
                    $user->role
                ];
            });
            
            $this->table($headers, $rows);
            $this->newLine();
            
            $this->comment('Please provide the user ID to assign Super Admin role:');
            $userId = $this->ask('Enter User ID');
            
            $user = DB::table('users')->where('id', $userId)->first();
            
            if (!$user) {
                $this->error("❌ User with ID {$userId} not found!");
                return 1;
            }
            
            $superAdminUsers = collect([$user]);
        }

        // Assign role to Super Admin users
        $assigned = 0;
        $alreadyHas = 0;

        foreach ($superAdminUsers as $user) {
            // Check if role already assigned
            $existing = DB::table('user_roles')
                ->where('user_id', $user->id)
                ->where('role_id', $superAdminRole->id)
                ->first();
            
            if ($existing) {
                $this->info("ℹ️  {$user->email} already has Super Admin role");
                $alreadyHas++;
            } else {
                DB::table('user_roles')->insert([
                    'user_id' => $user->id,
                    'role_id' => $superAdminRole->id,
                    'is_primary' => true,
                    'branch_id' => $user->branch_id ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $this->info("✅ Assigned Super Admin role to {$user->email}");
                $assigned++;
            }
        }

        $this->newLine();
        $this->info('📊 Summary:');
        $this->info("  - Roles assigned: {$assigned}");
        $this->info("  - Already had role: {$alreadyHas}");
        $this->newLine();
        
        if ($assigned > 0) {
            $this->info('🎉 Done! Now logout and login again to see the menus.');
        } else {
            $this->info('✅ All Super Admin users already have roles assigned.');
        }

        return 0;
    }
}

