<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data for two existing schools (created by CompanyWithSchoolSeeder):
 *   - Vidya Vihar Public School  (VIDYA-VVPS)
 *   - Sunrise International School (SUNRISE-SIS)
 *
 * For each school it creates: departments, teachers (with user logins),
 * sections, and 10 students per section (with user logins + enrollments),
 * plus the relation rows (user_roles, student_enrollments) and the
 * multi-tenant scoping columns (school_id, branch_id, academic_year_id).
 *
 * Idempotent: every row is upserted on a natural unique key, so re-running
 * updates the same records instead of duplicating them.
 *
 * Default login password for every created teacher/student: Password@123
 */
class SchoolDemoDataSeeder extends Seeder
{
    private const PASSWORD = 'Password@123';

    private array $departments = ['Science', 'Mathematics', 'Languages', 'Physical Education'];

    private array $teachers = [
        ['first' => 'Suresh', 'last' => 'Kumar',  'gender' => 'Male',   'dept' => 'Science',            'designation' => 'PGT Physics'],
        ['first' => 'Lakshmi', 'last' => 'Menon', 'gender' => 'Female', 'dept' => 'Science',            'designation' => 'TGT Biology'],
        ['first' => 'Arjun',  'last' => 'Reddy',  'gender' => 'Male',   'dept' => 'Mathematics',        'designation' => 'PGT Mathematics'],
        ['first' => 'Priya',  'last' => 'Sharma', 'gender' => 'Female', 'dept' => 'Languages',          'designation' => 'TGT English'],
        ['first' => 'Mohan',  'last' => 'Rao',    'gender' => 'Male',   'dept' => 'Languages',          'designation' => 'TGT Hindi'],
        ['first' => 'Kavita', 'last' => 'Joshi',  'gender' => 'Female', 'dept' => 'Physical Education',  'designation' => 'Physical Education Teacher'],
    ];

    private array $firstNames = [
        'Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Reyansh', 'Ayaan', 'Krishna', 'Ishaan',
        'Ananya', 'Diya', 'Aadhya', 'Saanvi', 'Pari', 'Anika', 'Navya', 'Myra', 'Sara', 'Riya',
    ];

    private array $lastNames = [
        'Sharma', 'Verma', 'Gupta', 'Reddy', 'Nair', 'Iyer', 'Patel', 'Rao', 'Singh', 'Mehta',
    ];

    public function run(): void
    {
        // Resolve the current academic year (created by migrations/other seeders).
        $ay = DB::table('academic_years')->where('is_current', true)->first()
            ?? DB::table('academic_years')->first();

        if (! $ay) {
            $ayId = DB::table('academic_years')->insertGetId([
                'name' => '2026-2027', 'start_date' => '2026-06-01', 'end_date' => '2027-04-30',
                'is_current' => true, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $ay = DB::table('academic_years')->find($ayId);
        }

        $teacherRoleId = DB::table('roles')->where('slug', 'teacher')->value('id');
        $studentRoleId = DB::table('roles')->where('slug', 'student')->value('id');

        $schools = [
            ['code' => 'VIDYA-VVPS',   'short' => 'VVPS', 'domain' => 'vidyavihar.edu.in'],
            ['code' => 'SUNRISE-SIS',  'short' => 'SIS',  'domain' => 'sunriseintl.edu.in'],
        ];

        foreach ($schools as $cfg) {
            $school = DB::table('schools')->where('code', $cfg['code'])->first();
            if (! $school) {
                $this->command->warn("  ! School {$cfg['code']} not found - run CompanyWithSchoolSeeder first. Skipping.");
                continue;
            }

            $branch = DB::table('branches')->find($school->main_branch_id);
            if (! $branch) {
                $this->command->warn("  ! School {$cfg['code']} has no main branch. Skipping.");
                continue;
            }

            $this->seedSchool($cfg, $school, $branch, $ay, $teacherRoleId, $studentRoleId);
        }

        $this->command->info('School demo data seeding complete. Login password for all teachers/students: ' . self::PASSWORD);
    }

    private function seedSchool(array $cfg, object $school, object $branch, object $ay, ?int $teacherRoleId, ?int $studentRoleId): void
    {
        $companyId = $school->company_id;
        $branchId  = $branch->id;
        $schoolId  = $school->id;

        // ---- Departments ----
        $deptIds = [];
        foreach ($this->departments as $deptName) {
            $deptIds[$deptName] = $this->upsertGetId('departments',
                ['branch_id' => $branchId, 'name' => $deptName],
                [
                    'school_id'   => $schoolId,
                    'head'        => 'Head of ' . $deptName,
                    'description' => $deptName . ' department',
                    'is_active'   => true,
                ],
            );
        }

        // ---- Teachers (each with a user login) ----
        $teacherUserIds = [];
        foreach ($this->teachers as $i => $t) {
            $seq   = str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $email = strtolower($t['first'] . '.' . $t['last']) . '.' . strtolower($cfg['short']) . '@' . $cfg['domain'];

            $userId = $this->upsertUser($email, [
                'first_name' => $t['first'],
                'last_name'  => $t['last'],
                'phone'      => '+91 90000 ' . str_pad((string) ($i + 10), 5, '0', STR_PAD_LEFT),
                'role'       => 'Teacher',
                'user_type'  => 'Teacher',
                'branch_id'  => $branchId,
                'company_id' => $companyId,
            ]);
            $teacherUserIds[] = $userId;
            $this->attachRole($userId, $teacherRoleId, $branchId);

            $teacherId = $this->upsertGetId('teachers',
                ['employee_id' => $cfg['short'] . '-T-' . $seq],
                [
                    'user_id'                 => $userId,
                    'branch_id'               => $branchId,
                    'school_id'               => $schoolId,
                    'department_id'           => $deptIds[$t['dept']] ?? null,
                    'category_type'           => 'Teaching',
                    'joining_date'            => '2021-06-01',
                    'designation'             => $t['designation'],
                    'employee_type'           => 'Permanent',
                    'experience_years'        => 5,
                    'date_of_birth'           => sprintf('19%02d-03-15', 85 + $i),
                    'gender'                  => $t['gender'],
                    'nationality'             => 'Indian',
                    'current_address'         => $branch->address,
                    'city'                    => $branch->city,
                    'state'                   => $branch->state,
                    'pincode'                 => $branch->pincode,
                    'emergency_contact_name'  => 'Emergency Contact',
                    'emergency_contact_phone' => '+91 90000 00000',
                    'basic_salary'            => 45000,
                    'teacher_status'          => 'Active',
                ],
            );

            // The teacher record is also user_type_id on the user.
            DB::table('users')->where('id', $userId)->update(['user_type_id' => $teacherId]);
        }

        // ---- Sections (grades 1,2,3 - section A) + 10 students each ----
        $grades = ['1', '2', '3'];
        $admissionSeq = 0;

        foreach ($grades as $gi => $grade) {
            $sectionCode = $cfg['short'] . '-G' . $grade . '-A';
            $sectionId = $this->upsertGetId('sections',
                ['code' => $sectionCode],
                [
                    'branch_id'        => $branchId,
                    'school_id'        => $schoolId,
                    'name'             => 'A',
                    'grade_level'      => $grade,
                    'capacity'         => 40,
                    'current_strength' => 10,
                    'room_number'      => 'R-' . $grade . '01',
                    'class_teacher_id' => $teacherUserIds[$gi] ?? null,
                    'is_active'        => true,
                ],
            );

            for ($s = 1; $s <= 10; $s++) {
                $admissionSeq++;
                $idx   = ($gi * 10) + ($s - 1);
                $first = $this->firstNames[$idx % count($this->firstNames)];
                $last  = $this->lastNames[$idx % count($this->lastNames)];
                $gender = ($idx % count($this->firstNames)) < 10 ? 'Male' : 'Female';

                $seq   = str_pad((string) $s, 2, '0', STR_PAD_LEFT);
                $email = 'student.' . strtolower($cfg['short']) . '.g' . $grade . 'a.' . $seq . '@' . $cfg['domain'];
                $admissionNumber = $cfg['short'] . '/' . $ay->name . '/' . str_pad((string) $admissionSeq, 4, '0', STR_PAD_LEFT);

                $userId = $this->upsertUser($email, [
                    'first_name' => $first,
                    'last_name'  => $last,
                    'phone'      => null,
                    'role'       => 'Student',
                    'user_type'  => 'Student',
                    'branch_id'  => $branchId,
                    'company_id' => $companyId,
                ]);
                $this->attachRole($userId, $studentRoleId, $branchId);

                $birthYear = 2026 - (5 + (int) $grade);

                $studentId = $this->upsertGetId('students',
                    ['admission_number' => $admissionNumber],
                    [
                        'user_id'                 => $userId,
                        'branch_id'               => $branchId,
                        'school_id'               => $schoolId,
                        'academic_year_id'        => $ay->id,
                        'admission_date'          => $ay->start_date ?? '2026-06-01',
                        'roll_number'             => $grade . 'A' . $seq,
                        'grade'                   => $grade,
                        'section'                 => 'A',
                        'academic_year'           => $ay->name,
                        'date_of_birth'           => sprintf('%04d-%02d-%02d', $birthYear, ($s % 12) + 1, ($s % 27) + 1),
                        'gender'                  => $gender,
                        'nationality'             => 'Indian',
                        'current_address'         => $branch->address,
                        'city'                    => $branch->city,
                        'state'                   => $branch->state,
                        'country'                 => 'India',
                        'pincode'                 => $branch->pincode,
                        'father_name'             => 'Mr. ' . $last,
                        'father_phone'            => '+91 98765 ' . str_pad((string) $admissionSeq, 5, '0', STR_PAD_LEFT),
                        'mother_name'             => 'Mrs. ' . $last,
                        'emergency_contact_name'  => 'Mr. ' . $last,
                        'emergency_contact_phone' => '+91 98765 ' . str_pad((string) $admissionSeq, 5, '0', STR_PAD_LEFT),
                        'student_status'          => 'Active',
                        'admission_status'        => 'Admitted',
                    ],
                );

                // Update the user's user_type_id to point at the student record.
                DB::table('users')->where('id', $userId)->update(['user_type_id' => $studentId]);

                // Enrollment row for the current academic year.
                $this->upsertGetId('student_enrollments',
                    ['student_id' => $studentId, 'academic_year_id' => $ay->id],
                    [
                        'school_id'   => $schoolId,
                        'branch_id'   => $branchId,
                        'grade'       => $grade,
                        'section'     => 'A',
                        'roll_number' => $grade . 'A' . $seq,
                        'status'      => 'Active',
                    ],
                );
            }
        }

        $this->command->info("  ✓ {$school->name}: " . count($this->departments) . ' departments, ' . count($this->teachers) . ' teachers, ' . count($grades) . ' sections, ' . (count($grades) * 10) . ' students.');
    }

    /**
     * Insert or update a user keyed on email, returning its id.
     */
    private function upsertUser(string $email, array $values): int
    {
        $values['email_verified_at'] = now();
        $values['is_active'] = true;

        $existing = DB::table('users')->where('email', $email)->first();
        if ($existing) {
            DB::table('users')->where('id', $existing->id)->update(array_merge($values, ['updated_at' => now()]));
            return $existing->id;
        }

        return DB::table('users')->insertGetId(array_merge($values, [
            'email'      => $email,
            'password'   => Hash::make(self::PASSWORD),
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Generic upsert keyed on $unique; returns the row id.
     */
    private function upsertGetId(string $table, array $unique, array $values): int
    {
        $existing = DB::table($table)->where($unique)->first();
        if ($existing) {
            DB::table($table)->where('id', $existing->id)->update(array_merge($values, ['updated_at' => now()]));
            return $existing->id;
        }

        return DB::table($table)->insertGetId(array_merge($unique, $values, [
            'created_at' => now(),
            'updated_at' => now(),
        ]));
    }

    /**
     * Attach a role to a user via user_roles (idempotent).
     */
    private function attachRole(int $userId, ?int $roleId, int $branchId): void
    {
        if (! $roleId) {
            return;
        }

        $exists = DB::table('user_roles')
            ->where('user_id', $userId)
            ->where('role_id', $roleId)
            ->exists();

        if (! $exists) {
            DB::table('user_roles')->insert([
                'user_id'    => $userId,
                'role_id'    => $roleId,
                'is_primary' => true,
                'branch_id'  => $branchId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
