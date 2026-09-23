<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Company;
use App\Models\Role;
use App\Models\School;
use App\Models\User;
use App\Services\BranchClassSeedService;
use App\Services\BranchGradeService;
use App\Services\CompanyAcademicYearService;
use App\Services\SchoolGradeService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * One demo school under the existing company-portal company:
 * SuperAdmin + BranchAdmin, 10 teachers, 10 non-teaching staff, 2 accountants,
 * grades 1–2 × sections A/B/C × 25 students (150 students).
 *
 * Password for every created school user: Admin@123
 *
 * php artisan db:seed --class=DemoOneSchoolSeeder
 */
class DemoOneSchoolSeeder extends Seeder
{
    private const PASSWORD = 'Admin@123';

    private const SCHOOL_CODE = 'GV-DEMO';

    private const BRANCH_CODE = 'GV-DEMO-MAIN';

    public function run(): void
    {
        $company = Company::query()->where('code', 'DEFAULT')->first()
            ?? Company::query()->first();

        if (! $company) {
            $this->command->error('No company found. Run CompanyPortalSeeder first.');

            return;
        }

        $roles = [
            'super-admin' => Role::where('slug', 'super-admin')->first(),
            'branch-admin' => Role::where('slug', 'branch-admin')->first(),
            'teacher' => Role::where('slug', 'teacher')->first(),
            'staff' => Role::where('slug', 'staff')->first(),
            'accountant' => Role::where('slug', 'accountant')->first(),
            'student' => Role::where('slug', 'student')->first(),
        ];

        DB::transaction(function () use ($company, $roles) {
            [$school, $branch, $ay] = $this->seedSchoolAndAdmins($company, $roles);
            $deptIds = $this->seedDepartments($school, $branch);
            $teacherUserIds = $this->seedEmployees($school, $branch, $deptIds, $roles);
            $this->seedClassesAndStudents($school, $branch, $ay, $teacherUserIds, $roles['student']?->id);

            $this->command->info('Demo school seeded.');
            $this->command->info('  School: Green Valley Demo School ('.self::SCHOOL_CODE.')');
            $this->command->info('  Super Admin: superadmin@greenvalley.demo / '.self::PASSWORD);
            $this->command->info('  Branch Admin: branchadmin@greenvalley.demo / '.self::PASSWORD);
            $this->command->info('  Teachers/Staff/Accountants/Students password: '.self::PASSWORD);
            $this->command->info('  10 teachers, 10 non-teaching, 2 accountants, 150 students (G1–G2 × A/B/C × 25).');
            $this->command->info('Next: php artisan db:seed --class=DemoSchoolOpsSeeder (subjects, exams, leaves, fees, holidays).');
        });
    }

    /**
     * @return array{0: School, 1: Branch, 2: object}
     */
    private function seedSchoolAndAdmins(Company $company, array $roles): array
    {
        $school = School::updateOrCreate(
            ['code' => self::SCHOOL_CODE],
            [
                'company_id' => $company->id,
                'name' => 'Green Valley Demo School',
                'status' => 'Active',
            ]
        );

        app(SchoolGradeService::class)->ensureDefaults((int) $school->id);
        $ay = app(CompanyAcademicYearService::class)->ensureCurrentIndianYear((int) $company->id);

        $branch = Branch::updateOrCreate(
            ['code' => self::BRANCH_CODE],
            [
                'name' => 'Green Valley Demo School - Main Campus',
                'school_id' => $school->id,
                'branch_type' => 'School',
                'address' => '12 Lake View Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'country' => 'India',
                'pincode' => '411001',
                'phone' => '+919810000001',
                'email' => 'office@greenvalley.demo',
                'website' => 'https://greenvalley.demo',
                'principal_name' => 'Anita Sharma',
                'principal_contact' => '+919810000003',
                'principal_email' => 'branchadmin@greenvalley.demo',
                'is_main_branch' => true,
                'status' => 'Active',
                'is_active' => true,
                'current_enrollment' => 0,
            ]
        );

        $school->update(['main_branch_id' => $branch->id]);
        app(BranchGradeService::class)->ensureDefaults((int) $branch->id);
        app(BranchClassSeedService::class)->ensureDefaultsForBranch((int) $branch->id);

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@greenvalley.demo'],
            [
                'first_name' => 'Rahul',
                'last_name' => 'Mehta',
                'password' => Hash::make(self::PASSWORD),
                'phone' => '+919810000002',
                'role' => 'SuperAdmin',
                'user_type' => 'SchoolUser',
                'branch_id' => $branch->id,
                'company_id' => $company->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $this->attachRole($superAdmin, $roles['super-admin']?->id, (int) $branch->id);
        DB::table('users')->where('id', $superAdmin->id)->update(['email_verified_at' => now()]);

        $branchAdmin = User::updateOrCreate(
            ['email' => 'branchadmin@greenvalley.demo'],
            [
                'first_name' => 'Anita',
                'last_name' => 'Sharma',
                'password' => Hash::make(self::PASSWORD),
                'phone' => '+919810000003',
                'role' => 'BranchAdmin',
                'user_type' => 'SchoolUser',
                'branch_id' => $branch->id,
                'company_id' => $company->id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $this->attachRole($branchAdmin, $roles['branch-admin']?->id, (int) $branch->id);
        DB::table('users')->where('id', $branchAdmin->id)->update(['email_verified_at' => now()]);

        return [$school->fresh(), $branch->fresh(), $ay];
    }

    /**
     * @return array<string, int>
     */
    private function seedDepartments(School $school, Branch $branch): array
    {
        $names = ['Science', 'Mathematics', 'Languages', 'Administration', 'Accounts'];
        $ids = [];

        foreach ($names as $name) {
            $payload = [
                'head' => 'Head of '.$name,
                'description' => $name.' department',
                'is_active' => true,
                'updated_at' => now(),
            ];
            if (Schema::hasColumn('departments', 'school_id')) {
                $payload['school_id'] = $school->id;
            }

            $query = DB::table('departments')->where('branch_id', $branch->id)->where('name', $name);
            $existing = $query->first();
            if ($existing) {
                $query->update($payload);
                $ids[$name] = (int) $existing->id;
            } else {
                $ids[$name] = (int) DB::table('departments')->insertGetId(array_merge($payload, [
                    'branch_id' => $branch->id,
                    'name' => $name,
                    'created_at' => now(),
                ]));
            }
        }

        return $ids;
    }

    /**
     * @return int[] teacher user ids (teaching only, for class-teacher assignment)
     */
    private function seedEmployees(School $school, Branch $branch, array $deptIds, array $roles): array
    {
        $teachers = [
            ['Suresh', 'Kumar', 'Male', 'Science', 'PGT Physics'],
            ['Lakshmi', 'Menon', 'Female', 'Science', 'TGT Biology'],
            ['Arjun', 'Reddy', 'Male', 'Mathematics', 'PGT Mathematics'],
            ['Priya', 'Sharma', 'Female', 'Languages', 'TGT English'],
            ['Mohan', 'Rao', 'Male', 'Languages', 'TGT Hindi'],
            ['Kavita', 'Joshi', 'Female', 'Science', 'TGT Chemistry'],
            ['Nikhil', 'Patel', 'Male', 'Mathematics', 'TGT Mathematics'],
            ['Meera', 'Iyer', 'Female', 'Languages', 'PGT English'],
            ['Ravi', 'Singh', 'Male', 'Science', 'TGT Physics'],
            ['Sneha', 'Gupta', 'Female', 'Mathematics', 'PRT Mathematics'],
        ];

        $staff = [
            ['Deepak', 'Nair', 'Male', 'Office Superintendent'],
            ['Pooja', 'Verma', 'Female', 'Front Desk Executive'],
            ['Amit', 'Shah', 'Male', 'Lab Assistant'],
            ['Neha', 'Kapoor', 'Female', 'Librarian'],
            ['Vikas', 'Yadav', 'Male', 'IT Support'],
            ['Ritu', 'Malhotra', 'Female', 'Counselor'],
            ['Sanjay', 'Joshi', 'Male', 'Transport Coordinator'],
            ['Anjali', 'Desai', 'Female', 'HR Executive'],
            ['Kiran', 'Bose', 'Male', 'Store Keeper'],
            ['Farah', 'Khan', 'Female', 'Receptionist'],
        ];

        $accountants = [
            ['Manish', 'Agarwal', 'Male', 'Senior Accountant'],
            ['Divya', 'Pillai', 'Female', 'Junior Accountant'],
        ];

        $teacherUserIds = [];
        foreach ($teachers as $i => $row) {
            $teacherUserIds[] = $this->upsertEmployee($school, $branch, $roles['teacher']?->id, [
                'seq' => $i + 1,
                'prefix' => 'T',
                'email' => sprintf('teacher%02d@greenvalley.demo', $i + 1),
                'first' => $row[0],
                'last' => $row[1],
                'gender' => $row[2],
                'department_id' => $deptIds[$row[3]] ?? null,
                'designation' => $row[4],
                'category_type' => 'Teaching',
                'role' => 'Teacher',
                'user_type' => 'Teacher',
                'phone' => sprintf('+91981100%04d', $i + 1),
            ]);
        }

        foreach ($staff as $i => $row) {
            $this->upsertEmployee($school, $branch, $roles['staff']?->id, [
                'seq' => $i + 1,
                'prefix' => 'S',
                'email' => sprintf('staff%02d@greenvalley.demo', $i + 1),
                'first' => $row[0],
                'last' => $row[1],
                'gender' => $row[2],
                'department_id' => $deptIds['Administration'] ?? null,
                'designation' => $row[3],
                'category_type' => 'Staff',
                'role' => 'Staff',
                'user_type' => 'Teacher',
                'phone' => sprintf('+91981200%04d', $i + 1),
            ]);
        }

        foreach ($accountants as $i => $row) {
            $this->upsertEmployee($school, $branch, $roles['accountant']?->id, [
                'seq' => $i + 1,
                'prefix' => 'A',
                'email' => sprintf('accountant%02d@greenvalley.demo', $i + 1),
                'first' => $row[0],
                'last' => $row[1],
                'gender' => $row[2],
                'department_id' => $deptIds['Accounts'] ?? null,
                'designation' => $row[3],
                'category_type' => 'Account',
                'role' => 'Accountant',
                'user_type' => 'Teacher',
                'phone' => sprintf('+91981300%04d', $i + 1),
            ]);
        }

        return $teacherUserIds;
    }

    private function upsertEmployee(School $school, Branch $branch, ?int $roleId, array $data): int
    {
        $user = User::updateOrCreate(
            ['email' => $data['email']],
            [
                'first_name' => $data['first'],
                'last_name' => $data['last'],
                'password' => Hash::make(self::PASSWORD),
                'phone' => $data['phone'],
                'role' => $data['role'],
                'user_type' => $data['user_type'],
                'branch_id' => $branch->id,
                'company_id' => $school->company_id,
                'is_active' => true,
                'email_verified_at' => now(),
            ]
        );
        $this->attachRole($user, $roleId, (int) $branch->id);

        $employeeId = sprintf('GV-%s-%02d', $data['prefix'], $data['seq']);
        $teacherPayload = [
            'user_id' => $user->id,
            'branch_id' => $branch->id,
            'department_id' => $data['department_id'],
            'category_type' => $data['category_type'],
            'joining_date' => '2022-06-01',
            'designation' => $data['designation'],
            'employee_type' => 'Permanent',
            'experience_years' => 4,
            'date_of_birth' => sprintf('198%u-0%u-15', $data['seq'] % 10, ($data['seq'] % 8) + 1),
            'gender' => $data['gender'],
            'nationality' => 'Indian',
            'current_address' => $branch->address,
            'city' => $branch->city,
            'state' => $branch->state,
            'pincode' => $branch->pincode,
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '+919800000000',
            'emergency_contact_relation' => 'Spouse',
            'basic_salary' => $data['category_type'] === 'Teaching' ? 45000 : 32000,
            'teacher_status' => 'Active',
        ];
        if (Schema::hasColumn('teachers', 'school_id')) {
            $teacherPayload['school_id'] = $school->id;
        }

        $existing = DB::table('teachers')->where('employee_id', $employeeId)->first();
        if ($existing) {
            DB::table('teachers')->where('id', $existing->id)->update(array_merge($teacherPayload, ['updated_at' => now()]));
            $teacherId = (int) $existing->id;
        } else {
            $teacherId = (int) DB::table('teachers')->insertGetId(array_merge($teacherPayload, [
                'employee_id' => $employeeId,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }

        DB::table('users')->where('id', $user->id)->update([
            'user_type_id' => $teacherId,
            'email_verified_at' => now(),
        ]);

        return (int) $user->id;
    }

    private function seedClassesAndStudents(School $school, Branch $branch, $ay, array $teacherUserIds, ?int $studentRoleId): void
    {
        $firstNames = [
            'Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan',
            'Kabir', 'Atharv', 'Dhruv', 'Ryan', 'Shaurya', 'Ananya', 'Diya', 'Aadhya', 'Saanvi', 'Pari',
            'Anika', 'Navya', 'Myra', 'Sara', 'Riya',
        ];
        $lastNames = ['Sharma', 'Verma', 'Gupta', 'Reddy', 'Nair', 'Iyer', 'Patel', 'Rao', 'Singh', 'Mehta'];
        $grades = ['1', '2'];
        $sections = ['A', 'B', 'C'];
        $ayName = $ay->name ?? '2026-2027';
        $ayId = (int) $ay->id;
        $admissionSeq = 0;
        $sectionIndex = 0;

        foreach ($grades as $grade) {
            foreach ($sections as $section) {
                $classTeacherId = $teacherUserIds[$sectionIndex] ?? null;
                $sectionCode = 'GV-G'.$grade.'-'.$section;

                $sectionPayload = [
                    'name' => $section,
                    'grade_level' => $grade,
                    'capacity' => 40,
                    'current_strength' => 25,
                    'room_number' => 'R-'.$grade.$section,
                    'class_teacher_id' => $classTeacherId,
                    'is_active' => true,
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('sections', 'school_id')) {
                    $sectionPayload['school_id'] = $school->id;
                }

                $existingSection = DB::table('sections')
                    ->where('branch_id', $branch->id)
                    ->where('name', $section)
                    ->where('grade_level', $grade)
                    ->first();
                if ($existingSection) {
                    DB::table('sections')->where('id', $existingSection->id)->update($sectionPayload);
                } else {
                    DB::table('sections')->insert(array_merge($sectionPayload, [
                        'branch_id' => $branch->id,
                        'code' => $sectionCode,
                        'created_at' => now(),
                    ]));
                }

                $classPayload = [
                    'class_name' => 'Grade '.$grade.'-'.$section,
                    'capacity' => 40,
                    'current_strength' => 25,
                    'room_number' => 'R-'.$grade.$section,
                    'class_teacher_id' => $classTeacherId,
                    'is_active' => true,
                    'updated_at' => now(),
                ];
                if (Schema::hasColumn('classes', 'school_id')) {
                    $classPayload['school_id'] = $school->id;
                }

                $existingClass = DB::table('classes')
                    ->where('branch_id', $branch->id)
                    ->where('grade', $grade)
                    ->where('section', $section)
                    ->where('academic_year', $ayName)
                    ->first();
                if ($existingClass) {
                    DB::table('classes')->where('id', $existingClass->id)->update($classPayload);
                } else {
                    DB::table('classes')->insert(array_merge($classPayload, [
                        'branch_id' => $branch->id,
                        'grade' => $grade,
                        'section' => $section,
                        'academic_year' => $ayName,
                        'created_at' => now(),
                    ]));
                }

                if ($classTeacherId) {
                    DB::table('teachers')->where('user_id', $classTeacherId)->update([
                        'is_class_teacher' => true,
                        'class_teacher_of_grade' => $grade,
                        'class_teacher_of_section' => $section,
                        'updated_at' => now(),
                    ]);
                }

                for ($s = 1; $s <= 25; $s++) {
                    $admissionSeq++;
                    $idx = ($sectionIndex * 25) + ($s - 1);
                    $first = $firstNames[$idx % count($firstNames)];
                    $last = $lastNames[$idx % count($lastNames)];
                    $gender = ($idx % 2 === 0) ? 'Male' : 'Female';
                    $seq = str_pad((string) $s, 2, '0', STR_PAD_LEFT);
                    $email = sprintf('student.g%s%s.%s@greenvalley.demo', $grade, strtolower($section), $seq);
                    $admissionNumber = sprintf('GV/%s/G%s%s/%s', $ayName, $grade, $section, $seq);
                    $roll = $grade.$section.$seq;
                    $birthYear = ((int) now()->format('Y')) - (5 + (int) $grade);

                    $user = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $first,
                            'last_name' => $last,
                            'password' => Hash::make(self::PASSWORD),
                            'phone' => sprintf('+91982%07d', $admissionSeq),
                            'role' => 'Student',
                            'user_type' => 'Student',
                            'branch_id' => $branch->id,
                            'company_id' => $school->company_id,
                            'is_active' => true,
                            'email_verified_at' => now(),
                        ]
                    );
                    $this->attachRole($user, $studentRoleId, (int) $branch->id);

                    $studentPayload = [
                        'user_id' => $user->id,
                        'branch_id' => $branch->id,
                        'admission_date' => $ay->start_date ?? now()->toDateString(),
                        'roll_number' => $roll,
                        'grade' => $grade,
                        'section' => $section,
                        'academic_year' => $ayName,
                        'date_of_birth' => sprintf('%04d-%02d-%02d', $birthYear, ($s % 12) + 1, min(($s % 27) + 1, 28)),
                        'gender' => $gender,
                        'nationality' => 'Indian',
                        'current_address' => $branch->address,
                        'city' => $branch->city,
                        'state' => $branch->state,
                        'country' => 'India',
                        'pincode' => $branch->pincode,
                        'father_name' => 'Mr. '.$last,
                        'father_phone' => sprintf('+91987%07d', $admissionSeq),
                        'mother_name' => 'Mrs. '.$last,
                        'emergency_contact_name' => 'Mr. '.$last,
                        'emergency_contact_phone' => sprintf('+91987%07d', $admissionSeq),
                        'emergency_contact_relation' => 'Father',
                        'student_status' => 'Active',
                        'admission_status' => 'Admitted',
                    ];
                    if (Schema::hasColumn('students', 'school_id')) {
                        $studentPayload['school_id'] = $school->id;
                    }
                    if (Schema::hasColumn('students', 'academic_year_id')) {
                        $studentPayload['academic_year_id'] = $ayId;
                    }

                    $existingStudent = DB::table('students')->where('admission_number', $admissionNumber)->first();
                    if ($existingStudent) {
                        DB::table('students')->where('id', $existingStudent->id)->update(array_merge($studentPayload, ['updated_at' => now()]));
                        $studentId = (int) $existingStudent->id;
                    } else {
                        $studentId = (int) DB::table('students')->insertGetId(array_merge($studentPayload, [
                            'admission_number' => $admissionNumber,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]));
                    }

                    DB::table('users')->where('id', $user->id)->update([
                        'user_type_id' => $studentId,
                        'email_verified_at' => now(),
                    ]);

                    $enrollment = [
                        'school_id' => $school->id,
                        'branch_id' => $branch->id,
                        'grade' => $grade,
                        'section' => $section,
                        'roll_number' => $roll,
                        'status' => 'Active',
                        'updated_at' => now(),
                    ];
                    $existingEnrollment = DB::table('student_enrollments')
                        ->where('student_id', $studentId)
                        ->where('academic_year_id', $ayId)
                        ->first();
                    if ($existingEnrollment) {
                        DB::table('student_enrollments')->where('id', $existingEnrollment->id)->update($enrollment);
                    } else {
                        DB::table('student_enrollments')->insert(array_merge($enrollment, [
                            'student_id' => $studentId,
                            'academic_year_id' => $ayId,
                            'created_at' => now(),
                        ]));
                    }
                }

                $sectionIndex++;
            }
        }

        $branch->update(['current_enrollment' => 150]);
    }

    private function attachRole(User $user, ?int $roleId, int $branchId): void
    {
        if (! $roleId) {
            return;
        }

        $exists = DB::table('user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $roleId)
            ->where('branch_id', $branchId)
            ->exists();

        if (! $exists) {
            $user->roles()->attach($roleId, [
                'is_primary' => true,
                'branch_id' => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
