<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\BranchFeatureSeedService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the full demo feature set onto one additional Green Valley branch.
 * Existing main-campus data is left untouched.
 *
 * php artisan db:seed --class=DemoNewBranchSeeder
 */
class DemoNewBranchSeeder extends Seeder
{
    public const BRANCH_CODE = 'GV-DEMO-EAST';

    public function run(): void
    {
        $school = School::query()->where('code', 'GV-DEMO')->first();
        if (! $school) {
            $this->command?->error('Demo school missing. Run DemoOneSchoolSeeder first.');

            return;
        }

        $branch = Branch::query()->where('code', self::BRANCH_CODE)->first();
        if (! $branch) {
            $branch = Branch::create([
                'name' => 'Green Valley Demo School - East Campus',
                'code' => self::BRANCH_CODE,
                'school_id' => $school->id,
                'parent_branch_id' => $school->main_branch_id,
                'branch_type' => 'School',
                'address' => '88 East Canal Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '411014',
                'phone' => '+919810000011',
                'email' => 'east@greenvalley.demo',
                'website' => 'https://greenvalley.demo',
                'principal_name' => 'Kiran Deshmukh',
                'principal_contact' => '+919810000012',
                'principal_email' => 'branchadmin.east@greenvalley.demo',
                'is_main_branch' => false,
                'status' => 'Active',
                'is_active' => true,
                'current_enrollment' => 0,
            ]);
            $this->command?->info('Created branch '.self::BRANCH_CODE);
        }

        $admin = User::updateOrCreate(
            ['email' => 'branchadmin.east@greenvalley.demo'],
            [
                'first_name' => 'Kiran',
                'last_name' => 'Deshmukh',
                'password' => Hash::make(BranchFeatureSeedService::PASSWORD),
                'phone' => '+919810000012',
                'role' => 'BranchAdmin',
                'user_type' => 'SchoolUser',
                'branch_id' => $branch->id,
                'company_id' => $school->company_id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $roleId = Role::where('slug', 'branch-admin')->value('id');
        if ($roleId && ! $admin->roles()->where('roles.id', $roleId)->exists()) {
            $admin->roles()->attach($roleId, [
                'is_primary' => true,
                'branch_id' => $branch->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        app(BranchFeatureSeedService::class)->seed($branch, $admin);

        $this->command?->info('Seeded feature data for '.$branch->name.' only.');
        $this->command?->info('  Teachers: teacher01.gv-demo-east@greenvalley.demo / '.BranchFeatureSeedService::PASSWORD);
        $this->command?->info('  Students: student.g1a.01.gv-demo-east@greenvalley.demo / '.BranchFeatureSeedService::PASSWORD);
    }
}
