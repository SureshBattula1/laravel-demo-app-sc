<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\BranchGradeService;
use App\Services\SchoolGradeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class CompanyWithSchoolSeeder extends Seeder
{
    /**
     * Seed 3 companies, each owning exactly one school WITH its related data,
     * mirroring the real "create school" flow in CompanyPortal\SchoolController@store:
     *
     *   company -> school -> default grades -> main branch -> branch grades
     *           -> school admin user -> super-admin role assignment
     *
     * Idempotent: keyed on the unique codes/emails via updateOrCreate, so re-running
     * updates the same records instead of creating duplicates.
     */
    public function run(): void
    {
        $companies = [
            [
                'name'    => 'Vidya Group of Institutions',
                'code'    => 'VIDYA',
                'email'   => 'contact@vidyagroup.edu.in',
                'phone'   => '+91 40 2345 6789',
                'address' => 'Plot 14, Jubilee Hills',
                'city'    => 'Hyderabad',
                'state'   => 'Telangana',
                'country' => 'India',
                'pincode' => '500033',
                'tax_id'  => 'GSTIN36AAACV1234A1Z5',
                'website' => 'https://www.vidyagroup.edu.in',
                'school'  => [
                    'name' => 'Vidya Vihar Public School',
                    'code' => 'VIDYA-VVPS',
                ],
                'branch'  => [
                    'name'    => 'Vidya Vihar Public School - Main Campus',
                    'code'    => 'VIDYA-VVPS-MAIN',
                    'address' => 'Plot 14, Jubilee Hills',
                    'city'    => 'Hyderabad',
                    'state'   => 'Telangana',
                    'country' => 'India',
                    'pincode' => '500033',
                    'phone'   => '+91 40 2345 6790',
                    'email'   => 'office@vidyavihar.edu.in',
                    'website' => 'https://www.vidyavihar.edu.in',
                ],
                'admin'   => [
                    'first_name' => 'Ramesh',
                    'last_name'  => 'Iyer',
                    'email'      => 'principal@vidyavihar.edu.in',
                    'phone'      => '+91 98480 11122',
                ],
            ],
            [
                'name'    => 'Sunrise Education Trust',
                'code'    => 'SUNRISE',
                'email'   => 'info@sunrisetrust.edu.in',
                'phone'   => '+91 80 4012 8800',
                'address' => '22 MG Road, Indiranagar',
                'city'    => 'Bengaluru',
                'state'   => 'Karnataka',
                'country' => 'India',
                'pincode' => '560038',
                'tax_id'  => 'GSTIN29AABCS5678B1Z2',
                'website' => 'https://www.sunrisetrust.edu.in',
                'school'  => [
                    'name' => 'Sunrise International School',
                    'code' => 'SUNRISE-SIS',
                ],
                'branch'  => [
                    'name'    => 'Sunrise International School - Main Campus',
                    'code'    => 'SUNRISE-SIS-MAIN',
                    'address' => '22 MG Road, Indiranagar',
                    'city'    => 'Bengaluru',
                    'state'   => 'Karnataka',
                    'country' => 'India',
                    'pincode' => '560038',
                    'phone'   => '+91 80 4012 8801',
                    'email'   => 'office@sunriseintl.edu.in',
                    'website' => 'https://www.sunriseintl.edu.in',
                ],
                'admin'   => [
                    'first_name' => 'Anita',
                    'last_name'  => 'Nair',
                    'email'      => 'principal@sunriseintl.edu.in',
                    'phone'      => '+91 99000 22233',
                ],
            ],
            [
                'name'    => 'Greenwood Learning Pvt Ltd',
                'code'    => 'GREENWOOD',
                'email'   => 'hello@greenwoodlearning.in',
                'phone'   => '+91 22 6655 4400',
                'address' => '8 Hill Road, Bandra West',
                'city'    => 'Mumbai',
                'state'   => 'Maharashtra',
                'country' => 'India',
                'pincode' => '400050',
                'tax_id'  => 'GSTIN27AAFCG9012C1Z9',
                'website' => 'https://www.greenwoodlearning.in',
                'school'  => [
                    'name' => 'Greenwood High School',
                    'code' => 'GREENWOOD-GHS',
                ],
                'branch'  => [
                    'name'    => 'Greenwood High School - Main Campus',
                    'code'    => 'GREENWOOD-GHS-MAIN',
                    'address' => '8 Hill Road, Bandra West',
                    'city'    => 'Mumbai',
                    'state'   => 'Maharashtra',
                    'country' => 'India',
                    'pincode' => '400050',
                    'phone'   => '+91 22 6655 4401',
                    'email'   => 'office@greenwoodhigh.edu.in',
                    'website' => 'https://www.greenwoodhigh.edu.in',
                ],
                'admin'   => [
                    'first_name' => 'Vikram',
                    'last_name'  => 'Desai',
                    'email'      => 'principal@greenwoodhigh.edu.in',
                    'phone'      => '+91 98200 33344',
                ],
            ],
        ];

        $superAdminRole = Role::where('slug', 'super-admin')->first();
        if (! $superAdminRole) {
            $this->command->warn('  ! Role "super-admin" not found - run the permissions seeder first. Users will still be created.');
        }

        foreach ($companies as $data) {
            DB::transaction(function () use ($data, $superAdminRole) {
                // 1) Company
                $company = Company::updateOrCreate(
                    ['code' => $data['code']],
                    [
                        'name'    => $data['name'],
                        'email'   => $data['email'],
                        'phone'   => $data['phone'],
                        'address' => $data['address'],
                        'city'    => $data['city'],
                        'state'   => $data['state'],
                        'country' => $data['country'],
                        'pincode' => $data['pincode'],
                        'tax_id'  => $data['tax_id'],
                        'website' => $data['website'],
                        'status'  => 'Active',
                    ],
                );

                // 2) School
                $school = School::updateOrCreate(
                    ['code' => $data['school']['code']],
                    [
                        'company_id' => $company->id,
                        'name'       => $data['school']['name'],
                        'status'     => 'Active',
                    ],
                );

                // 3) Default grades for the school (1..12)
                app(SchoolGradeService::class)->ensureDefaults((int) $school->id);

                // 4) Main branch
                $b = $data['branch'];
                $branch = Branch::updateOrCreate(
                    ['code' => $b['code']],
                    [
                        'name'               => $b['name'],
                        'school_id'          => $school->id,
                        'branch_type'        => 'School',
                        'address'            => $b['address'],
                        'city'               => $b['city'],
                        'state'              => $b['state'],
                        'country'            => $b['country'],
                        'pincode'            => $b['pincode'],
                        'phone'              => $b['phone'],
                        'email'              => $b['email'],
                        'website'            => $b['website'],
                        'is_main_branch'     => true,
                        'status'             => 'Active',
                        'is_active'          => true,
                        'current_enrollment' => 0,
                    ],
                );

                // 5) Link school -> main branch, and branch default grades
                $school->update(['main_branch_id' => $branch->id]);
                app(BranchGradeService::class)->ensureDefaults((int) $branch->id);

                // 6) School admin user (SuperAdmin role, SchoolUser type, tied to the branch)
                $a = $data['admin'];
                $adminUser = User::updateOrCreate(
                    ['email' => $a['email']],
                    [
                        'first_name' => $a['first_name'],
                        'last_name'  => $a['last_name'],
                        'password'   => Hash::make('Admin@123'),
                        'phone'      => $a['phone'],
                        'role'       => 'SuperAdmin',
                        'user_type'  => 'SchoolUser',
                        'branch_id'  => $branch->id,
                        'company_id' => $company->id,
                        'is_active'  => true,
                    ],
                );

                // 7) Assign the super-admin role via user_roles (idempotent)
                if ($superAdminRole) {
                    $adminUser->roles()->syncWithoutDetaching([
                        $superAdminRole->id => [
                            'is_primary' => true,
                            'branch_id'  => $branch->id,
                        ],
                    ]);
                }

                $this->command->info("  ✓ {$company->name}  →  {$school->name}  →  admin {$a['email']}");
            });
        }

        $this->command->info('Seeded ' . count($companies) . ' companies, each with 1 school, main branch, grades and an admin user (password: Admin@123).');
    }
}
