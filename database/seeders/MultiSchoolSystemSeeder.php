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

class MultiSchoolSystemSeeder extends Seeder
{
    /**
     * Multi-School Multi-Branch System Seeder
     * Creates: 3 Companies × 3 Schools each = 9 Schools total
     * Each School (Branch): 1 BranchAdmin, 6 Teachers, 30 Students
     *
     * Structure:
     * Company 1 (Vidya) → School 1 (Hyderabad) → 6 Teachers + 30 Students
     *                  → School 2 (Vijayawada) → 6 Teachers + 30 Students
     *                  → School 3 (Chennai) → 6 Teachers + 30 Students
     * Company 2 (Sunrise) → School 1 (Bangalore) → 6 Teachers + 30 Students
     *                    → School 2 (Mumbai) → 6 Teachers + 30 Students
     *                    → School 3 (Delhi) → 6 Teachers + 30 Students
     * Company 3 (Greenwood) → School 1 (Pune) → 6 Teachers + 30 Students
     *                      → School 2 (Kolkata) → 6 Teachers + 30 Students
     *                      → School 3 (Ahmedabad) → 6 Teachers + 30 Students
     */
    public function run(): void
    {
        $branchAdminRole = Role::where('slug', 'branch-admin')->first();
        $teacherRole = Role::where('slug', 'teacher')->first();
        $studentRole = Role::where('slug', 'student')->first();

        if (!$branchAdminRole || !$teacherRole || !$studentRole) {
            $this->command->warn('Roles not found - run DatabaseSeeder first');
            return;
        }

        $companies = [
            [
                'name' => 'Vidya Group of Institutions',
                'code' => 'VIDYA',
                'email' => 'contact@vidyagroup.edu.in',
                'phone' => '+91 40 2345 6789',
                'city' => 'Hyderabad',
                'schools' => [
                    [
                        'name' => 'Vidya Vihar - Hyderabad',
                        'code' => 'VIDYA-HYD',
                        'city' => 'Hyderabad',
                        'state' => 'Telangana',
                        'admin_email' => 'principal-hyd@vidya.edu.in',
                        'admin_name' => 'Ramesh Iyer',
                    ],
                    [
                        'name' => 'Vidya Vihar - Vijayawada',
                        'code' => 'VIDYA-VJA',
                        'city' => 'Vijayawada',
                        'state' => 'Andhra Pradesh',
                        'admin_email' => 'principal-vja@vidya.edu.in',
                        'admin_name' => 'Suresh Kumar',
                    ],
                    [
                        'name' => 'Vidya Vihar - Chennai',
                        'code' => 'VIDYA-CHE',
                        'city' => 'Chennai',
                        'state' => 'Tamil Nadu',
                        'admin_email' => 'principal-che@vidya.edu.in',
                        'admin_name' => 'Rajesh Menon',
                    ],
                ],
            ],
            [
                'name' => 'Sunrise Education Trust',
                'code' => 'SUNRISE',
                'email' => 'info@sunrisetrust.edu.in',
                'phone' => '+91 80 4012 8800',
                'city' => 'Bangalore',
                'schools' => [
                    [
                        'name' => 'Sunrise International - Bangalore',
                        'code' => 'SUNRISE-BNG',
                        'city' => 'Bangalore',
                        'state' => 'Karnataka',
                        'admin_email' => 'principal-bng@sunrise.edu.in',
                        'admin_name' => 'Anita Nair',
                    ],
                    [
                        'name' => 'Sunrise International - Mumbai',
                        'code' => 'SUNRISE-MUM',
                        'city' => 'Mumbai',
                        'state' => 'Maharashtra',
                        'admin_email' => 'principal-mum@sunrise.edu.in',
                        'admin_name' => 'Priya Sharma',
                    ],
                    [
                        'name' => 'Sunrise International - Delhi',
                        'code' => 'SUNRISE-DEL',
                        'city' => 'Delhi',
                        'state' => 'Delhi',
                        'admin_email' => 'principal-del@sunrise.edu.in',
                        'admin_name' => 'Neha Verma',
                    ],
                ],
            ],
            [
                'name' => 'Greenwood Learning Pvt Ltd',
                'code' => 'GREENWOOD',
                'email' => 'hello@greenwoodlearning.in',
                'phone' => '+91 22 6655 4400',
                'city' => 'Pune',
                'schools' => [
                    [
                        'name' => 'Greenwood High - Pune',
                        'code' => 'GREENWOOD-PUN',
                        'city' => 'Pune',
                        'state' => 'Maharashtra',
                        'admin_email' => 'principal-pun@greenwood.edu.in',
                        'admin_name' => 'Vikram Desai',
                    ],
                    [
                        'name' => 'Greenwood High - Kolkata',
                        'code' => 'GREENWOOD-KOL',
                        'city' => 'Kolkata',
                        'state' => 'West Bengal',
                        'admin_email' => 'principal-kol@greenwood.edu.in',
                        'admin_name' => 'Arjun Banerjee',
                    ],
                    [
                        'name' => 'Greenwood High - Ahmedabad',
                        'code' => 'GREENWOOD-AHM',
                        'city' => 'Ahmedabad',
                        'state' => 'Gujarat',
                        'admin_email' => 'principal-ahm@greenwood.edu.in',
                        'admin_name' => 'Kavita Patel',
                    ],
                ],
            ],
        ];

        foreach ($companies as $companyData) {
            DB::transaction(function () use ($companyData, $branchAdminRole, $teacherRole, $studentRole) {
                // Create company
                $company = Company::updateOrCreate(
                    ['code' => $companyData['code']],
                    [
                        'name' => $companyData['name'],
                        'email' => $companyData['email'],
                        'phone' => $companyData['phone'],
                        'status' => 'Active',
                    ]
                );

                // Create 3 schools per company
                foreach ($companyData['schools'] as $schoolIdx => $schoolData) {
                    // Create school
                    $school = School::updateOrCreate(
                        ['code' => $schoolData['code']],
                        [
                            'company_id' => $company->id,
                            'name' => $schoolData['name'],
                            'status' => 'Active',
                        ]
                    );

                    // Create branch (main branch for the school)
                    $branch = Branch::updateOrCreate(
                        ['code' => $schoolData['code']],
                        [
                            'name' => $schoolData['name'],
                            'school_id' => $school->id,
                            'branch_type' => 'School',
                            'address' => $schoolData['city'],
                            'city' => $schoolData['city'],
                            'state' => $schoolData['state'],
                            'country' => 'India',
                            'pincode' => '500000',
                            'phone' => '+91 98765 00001',
                            'email' => 'office.' . strtolower($schoolData['code']) . '@' . strtolower($companyData['code']) . '.edu.in',
                            'is_main_branch' => true,
                            'status' => 'Active',
                            'is_active' => true,
                        ]
                    );

                    // Update school's main branch
                    $school->update(['main_branch_id' => $branch->id]);

                    // Create default grades
                    app(SchoolGradeService::class)->ensureDefaults((int) $school->id);
                    app(BranchGradeService::class)->ensureDefaults((int) $branch->id);

                    // Create BranchAdmin
                    $adminUser = User::updateOrCreate(
                        ['email' => $schoolData['admin_email']],
                        [
                            'first_name' => explode(' ', $schoolData['admin_name'])[0],
                            'last_name' => explode(' ', $schoolData['admin_name'])[1] ?? '',
                            'password' => Hash::make('Admin@123'),
                            'phone' => '+91 98765 ' . str_pad($schoolIdx + 1, 5, '0', STR_PAD_LEFT),
                            'role' => 'BranchAdmin',
                            'user_type' => 'SchoolUser',
                            'branch_id' => $branch->id,
                            'company_id' => $company->id,
                            'is_active' => true,
                        ]
                    );

                    // Assign BranchAdmin role
                    $adminUser->roles()->syncWithoutDetaching([
                        $branchAdminRole->id => [
                            'is_primary' => true,
                            'branch_id' => $branch->id,
                        ],
                    ]);

                    // Create 6 teachers
                    $teacherNames = [
                        ['Suresh', 'Kumar'],
                        ['Lakshmi', 'Menon'],
                        ['Arjun', 'Reddy'],
                        ['Priya', 'Sharma'],
                        ['Mohan', 'Rao'],
                        ['Kavita', 'Joshi'],
                    ];

                    foreach ($teacherNames as $idx => $name) {
                        $teacherEmail = strtolower($name[0]) . '.' . strtolower($name[1]) . '.' . $schoolData['code'] . '@' . strtolower($companyData['code']) . '.edu.in';

                        $teacher = User::updateOrCreate(
                            ['email' => $teacherEmail],
                            [
                                'first_name' => $name[0],
                                'last_name' => $name[1],
                                'password' => Hash::make('Password@123'),
                                'phone' => '+91 90000 ' . str_pad($idx + 1, 5, '0', STR_PAD_LEFT),
                                'role' => 'Teacher',
                                'user_type' => 'Teacher',
                                'branch_id' => $branch->id,
                                'company_id' => $company->id,
                                'is_active' => true,
                            ]
                        );

                        $teacher->roles()->syncWithoutDetaching([
                            $teacherRole->id => [
                                'is_primary' => true,
                                'branch_id' => $branch->id,
                            ],
                        ]);
                    }

                    // Create 30 students
                    $firstNames = ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan',
                        'Ananya', 'Diya', 'Aadhya', 'Saanvi', 'Pari', 'Anika', 'Navya', 'Myra', 'Sara', 'Riya'];
                    $lastNames = ['Sharma', 'Verma', 'Gupta', 'Reddy', 'Nair', 'Iyer', 'Patel', 'Rao', 'Singh', 'Mehta'];

                    for ($i = 0; $i < 30; $i++) {
                        $firstName = $firstNames[$i % count($firstNames)];
                        $lastName = $lastNames[$i % count($lastNames)];
                        $studentEmail = 'student.' . strtolower($schoolData['code']) . '.' . str_pad($i + 1, 2, '0', STR_PAD_LEFT) . '@' . strtolower($companyData['code']) . '.edu.in';

                        $student = User::updateOrCreate(
                            ['email' => $studentEmail],
                            [
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'password' => Hash::make('Password@123'),
                                'phone' => null,
                                'role' => 'Student',
                                'user_type' => 'Student',
                                'branch_id' => $branch->id,
                                'company_id' => $company->id,
                                'is_active' => true,
                            ]
                        );

                        $student->roles()->syncWithoutDetaching([
                            $studentRole->id => [
                                'is_primary' => true,
                                'branch_id' => $branch->id,
                            ],
                        ]);
                    }

                    $this->command->info("✅ {$company->name} → {$schoolData['name']} (Admin: {$schoolData['admin_email']})");
                }
            });
        }

        $this->command->info('');
        $this->command->info('🎉 Multi-School System Seeding Complete!');
        $this->command->info('   9 Schools (3 per company)');
        $this->command->info('   9 BranchAdmins (1 per school)');
        $this->command->info('   54 Teachers (6 per school)');
        $this->command->info('   270 Students (30 per school)');
        $this->command->info('');
        $this->command->info('🔑 Login Credentials:');
        $this->command->info('   SuperAdmin: superadmin@school.com / Admin@123');
        $this->command->info('   BranchAdmins: principal-hyd@vidya.edu.in / Admin@123');
        $this->command->info('   Teachers: suresh.kumar.VIDYA-HYD@vidya.edu.in / Password@123');
        $this->command->info('   Students: student.VIDYA-HYD.01@vidya.edu.in / Password@123');
    }
}
