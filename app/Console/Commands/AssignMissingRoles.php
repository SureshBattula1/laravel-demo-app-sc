<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Role;

class AssignMissingRoles extends Command
{
    protected $signature = 'roles:assign-missing';
    protected $description = 'Assign roles to users who have role field but no role relationship';

    public function handle()
    {
        $this->info('🔧 Assigning missing roles to users...');
        
        // Get Teacher role
        $teacherRole = Role::where('slug', 'teacher')->first();
        $studentRole = Role::where('slug', 'student')->first();
        $parentRole = Role::where('slug', 'parent')->first();
        
        if (!$teacherRole || !$studentRole || !$parentRole) {
            $this->error('❌ Roles not found. Please run PermissionSeeder first.');
            return 1;
        }
        
        // Assign Teacher roles
        $teachers = User::where('role', 'Teacher')->doesntHave('roles')->get();
        $teacherCount = 0;
        foreach ($teachers as $teacher) {
            $teacher->roles()->attach($teacherRole->id, [
                'is_primary' => true,
                'branch_id' => $teacher->branch_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $teacherCount++;
        }
        $this->info("✓ Assigned Teacher role to {$teacherCount} users");
        
        // Assign Student roles
        $students = User::where('role', 'Student')->doesntHave('roles')->get();
        $studentCount = 0;
        foreach ($students as $student) {
            $student->roles()->attach($studentRole->id, [
                'is_primary' => true,
                'branch_id' => $student->branch_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $studentCount++;
        }
        $this->info("✓ Assigned Student role to {$studentCount} users");
        
        // Assign Parent roles
        $parents = User::where('role', 'Parent')->doesntHave('roles')->get();
        $parentCount = 0;
        foreach ($parents as $parent) {
            $parent->roles()->attach($parentRole->id, [
                'is_primary' => true,
                'branch_id' => $parent->branch_id,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $parentCount++;
        }
        $this->info("✓ Assigned Parent role to {$parentCount} users");
        
        $this->info('');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('✅ Role Assignment Complete!');
        $this->info("   Teachers: {$teacherCount}");
        $this->info("   Students: {$studentCount}");
        $this->info("   Parents: {$parentCount}");
        $this->info("   Total: " . ($teacherCount + $studentCount + $parentCount));
        $this->info('═══════════════════════════════════════════════════════');
        
        return 0;
    }
}

