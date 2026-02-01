<?php

/**
 * Script to create test BranchAdmin, SuperAdmin, and Staff users for a school
 * 
 * Usage: php create_test_admin_users.php <school_id> <branch_id>
 * Example: php create_test_admin_users.php 1 1
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Models\School;
use App\Models\Branch;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

if ($argc < 3) {
    echo "Usage: php create_test_admin_users.php <school_id> <branch_id>\n";
    echo "Example: php create_test_admin_users.php 1 1\n";
    exit(1);
}

$schoolId = (int)$argv[1];
$branchId = (int)$argv[2];

// Verify school and branch exist
$school = School::find($schoolId);
if (!$school) {
    echo "Error: School with ID {$schoolId} not found.\n";
    exit(1);
}

$branch = Branch::find($branchId);
if (!$branch) {
    echo "Error: Branch with ID {$branchId} not found.\n";
    exit(1);
}

if ($branch->school_id != $schoolId) {
    echo "Error: Branch {$branchId} does not belong to school {$schoolId}.\n";
    exit(1);
}

echo "Creating test users for School: {$school->name} (ID: {$schoolId}), Branch: {$branch->name} (ID: {$branchId})\n\n";

// Users to create
$usersToCreate = [
    [
        'first_name' => 'Branch',
        'last_name' => 'Admin',
        'email' => "branchadmin.{$schoolId}.{$branchId}@test.com",
        'phone' => '1111111111',
        'role' => 'BranchAdmin',
        'user_type' => 'SchoolUser',
        'branch_id' => $branchId,
        'company_id' => $school->company_id,
        'is_active' => true,
    ],
    [
        'first_name' => 'Super',
        'last_name' => 'Admin',
        'email' => "superadmin.{$schoolId}.{$branchId}@test.com",
        'phone' => '2222222222',
        'role' => 'SuperAdmin',
        'user_type' => 'Admin',
        'branch_id' => $branchId,
        'company_id' => $school->company_id,
        'is_active' => true,
    ],
    [
        'first_name' => 'Staff',
        'last_name' => 'Member',
        'email' => "staff.{$schoolId}.{$branchId}@test.com",
        'phone' => '3333333333',
        'role' => 'Staff',
        'user_type' => 'SchoolUser',
        'branch_id' => $branchId,
        'company_id' => $school->company_id,
        'is_active' => true,
    ],
];

DB::beginTransaction();

try {
    foreach ($usersToCreate as $userData) {
        // Check if user already exists
        $existingUser = User::where('email', $userData['email'])->first();
        
        if ($existingUser) {
            echo "User {$userData['email']} already exists. Updating...\n";
            $existingUser->update($userData);
            $user = $existingUser;
        } else {
            $userData['password'] = Hash::make('Admin@123');
            $user = User::create($userData);
            echo "Created user: {$userData['email']} (Role: {$userData['role']})\n";
        }
        
        echo "  - Name: {$user->full_name}\n";
        echo "  - Email: {$user->email}\n";
        echo "  - Password: Admin@123\n";
        echo "  - Role: {$user->role}\n";
        echo "  - Branch ID: {$user->branch_id}\n";
        echo "  - Company ID: {$user->company_id}\n\n";
    }
    
    DB::commit();
    echo "✅ Successfully created/updated test users!\n";
    echo "\nYou can now test the 'Access School' feature and should see BranchAdmin, SuperAdmin, and Staff users.\n";
    
} catch (\Exception $e) {
    DB::rollBack();
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

