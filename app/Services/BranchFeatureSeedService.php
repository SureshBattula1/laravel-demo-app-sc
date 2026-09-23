<?php

namespace App\Services;

use App\Models\AccountCategory;
use App\Models\Branch;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\ExamTerm;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\FeeType;
use App\Models\Holiday;
use App\Models\Role;
use App\Models\School;
use App\Models\SectionSubject;
use App\Models\Subject;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Seeds the same demo feature set used by DemoOneSchoolSeeder + DemoSchoolOpsSeeder
 * onto a single new branch. Does not touch other branches.
 *
 * Password for generated users: Admin@123
 */
class BranchFeatureSeedService
{
    public const PASSWORD = 'Admin@123';

    public function seed(Branch $branch, ?User $actor = null): void
    {
        $branch->refresh();
        $school = School::query()->find($branch->school_id);
        if (! $school) {
            Log::warning('BranchFeatureSeedService: branch has no school', ['branch_id' => $branch->id]);

            return;
        }

        $companyId = (int) ($school->company_id ?? 0);
        $ay = $companyId > 0
            ? app(CompanyAcademicYearService::class)->ensureCurrentIndianYear($companyId)
            : null;

        if (! $ay) {
            Log::warning('BranchFeatureSeedService: no academic year', ['branch_id' => $branch->id]);

            return;
        }

        app(BranchGradeService::class)->ensureDefaults((int) $branch->id);
        app(BranchClassSeedService::class)->ensureDefaultsForBranch((int) $branch->id);

        $roles = [
            'teacher' => Role::where('slug', 'teacher')->first(),
            'staff' => Role::where('slug', 'staff')->first(),
            'accountant' => Role::where('slug', 'accountant')->first(),
            'student' => Role::where('slug', 'student')->first(),
        ];

        $deptIds = $this->seedDepartments($school, $branch);
        $teacherUserIds = $this->seedEmployees($school, $branch, $deptIds, $roles);
        $this->seedClassesAndStudents($school, $branch, $ay, $teacherUserIds, $roles['student']?->id);
        $this->seedSubjects($school, $branch, $ay, $teacherUserIds, $deptIds);
        $this->seedExams($school, $branch, $ay, $actor);
        $this->seedLeaves($school, $branch, $ay, $actor);
        $this->seedFees($school, $branch, $ay, $actor);
        $this->seedHolidays($school, $branch, $ay, $actor);

        Log::info('BranchFeatureSeedService: seeded branch feature data', [
            'branch_id' => $branch->id,
            'school_id' => $school->id,
        ]);
    }

    public function slugFor(Branch $branch): string
    {
        $slug = Str::slug((string) $branch->code);
        if ($slug === '') {
            $slug = 'br-'.$branch->id;
        }

        return Str::limit($slug, 24, '');
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

            $existing = DB::table('departments')
                ->where('branch_id', $branch->id)
                ->where('name', $name)
                ->first();
            if ($existing) {
                DB::table('departments')->where('id', $existing->id)->update($payload);
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
     * @return int[] teaching teacher user ids
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
                'email' => $this->email($branch, sprintf('teacher%02d', $i + 1)),
                'first' => $row[0],
                'last' => $row[1],
                'gender' => $row[2],
                'department_id' => $deptIds[$row[3]] ?? null,
                'designation' => $row[4],
                'category_type' => 'Teaching',
                'role' => 'Teacher',
                'user_type' => 'Teacher',
                'phone' => $this->phone($branch, 100, $i + 1),
            ]);
        }

        foreach ($staff as $i => $row) {
            $this->upsertEmployee($school, $branch, $roles['staff']?->id, [
                'seq' => $i + 1,
                'prefix' => 'S',
                'email' => $this->email($branch, sprintf('staff%02d', $i + 1)),
                'first' => $row[0],
                'last' => $row[1],
                'gender' => $row[2],
                'department_id' => $deptIds['Administration'] ?? null,
                'designation' => $row[3],
                'category_type' => 'Staff',
                'role' => 'Staff',
                'user_type' => 'Teacher',
                'phone' => $this->phone($branch, 200, $i + 1),
            ]);
        }

        foreach ($accountants as $i => $row) {
            $this->upsertEmployee($school, $branch, $roles['accountant']?->id, [
                'seq' => $i + 1,
                'prefix' => 'A',
                'email' => $this->email($branch, sprintf('accountant%02d', $i + 1)),
                'first' => $row[0],
                'last' => $row[1],
                'gender' => $row[2],
                'department_id' => $deptIds['Accounts'] ?? null,
                'designation' => $row[3],
                'category_type' => 'Account',
                'role' => 'Accountant',
                'user_type' => 'Teacher',
                'phone' => $this->phone($branch, 300, $i + 1),
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

        $employeeId = $this->employeeId($branch, $data['prefix'], $data['seq']);
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
        $ayName = $ay->name ?? '2026-2027';
        $ayId = (int) $ay->id;
        $admissionSeq = 0;
        $sectionIndex = 0;
        $slug = $this->slugFor($branch);

        foreach (['1', '2'] as $grade) {
            foreach (['A', 'B', 'C'] as $section) {
                $classTeacherId = $teacherUserIds[$sectionIndex] ?? null;

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
                        'code' => strtoupper($slug).'-G'.$grade.'-'.$section,
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
                    $email = $this->studentEmail($branch, $grade, $section, $s);
                    $admissionNumber = sprintf('%s/%s/G%s%s/%s', strtoupper($slug), $ayName, $grade, $section, $seq);
                    $roll = $grade.$section.$seq;
                    $birthYear = ((int) now()->format('Y')) - (5 + (int) $grade);

                    $user = User::updateOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $first,
                            'last_name' => $last,
                            'password' => Hash::make(self::PASSWORD),
                            'phone' => $this->phone($branch, 400, $admissionSeq),
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
                        'father_phone' => $this->phone($branch, 500, $admissionSeq),
                        'mother_name' => 'Mrs. '.$last,
                        'emergency_contact_name' => 'Mr. '.$last,
                        'emergency_contact_phone' => $this->phone($branch, 500, $admissionSeq),
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

    private function seedSubjects(School $school, Branch $branch, $ay, array $teacherUserIds, array $deptIds): void
    {
        $catalog = [
            ['code' => 'MATH', 'name' => 'Mathematics', 'type' => 'Core', 'credits' => 4, 'dept' => 'Mathematics', 'teacher' => ['1' => 3, '2' => 7]],
            ['code' => 'ENG', 'name' => 'English', 'type' => 'Language', 'credits' => 3, 'dept' => 'Languages', 'teacher' => ['1' => 4, '2' => 8]],
            ['code' => 'HIN', 'name' => 'Hindi', 'type' => 'Language', 'credits' => 3, 'dept' => 'Languages', 'teacher' => ['1' => 5, '2' => 5]],
            ['code' => 'SCI', 'name' => 'Science', 'type' => 'Core', 'credits' => 4, 'dept' => 'Science', 'teacher' => ['1' => 1, '2' => 2]],
            ['code' => 'PE', 'name' => 'Physical Education', 'type' => 'Activity', 'credits' => 1, 'dept' => 'Science', 'teacher' => ['1' => 6, '2' => 9]],
        ];
        $prefix = strtoupper($this->slugFor($branch));

        foreach (['1', '2'] as $grade) {
            foreach ($catalog as $item) {
                $teacherIdx = ($item['teacher'][$grade] ?? 1) - 1;
                $teacherId = $teacherUserIds[$teacherIdx] ?? ($teacherUserIds[0] ?? null);
                $subject = Subject::firstOrCreate(
                    ['branch_id' => $branch->id, 'code' => $prefix.'-'.$item['code'].'-G'.$grade],
                    [
                        'school_id' => $school->id,
                        'department_id' => $deptIds[$item['dept']] ?? null,
                        'teacher_id' => $teacherId,
                        'name' => $item['name'],
                        'description' => $item['name'].' for Grade '.$grade,
                        'grade_level' => $grade,
                        'type' => $item['type'],
                        'credits' => $item['credits'],
                        'is_active' => true,
                    ]
                );

                foreach (['A', 'B', 'C'] as $section) {
                    $sectionId = DB::table('sections')
                        ->where('branch_id', $branch->id)
                        ->where('grade_level', $grade)
                        ->where('name', $section)
                        ->value('id');
                    if (! $sectionId) {
                        continue;
                    }

                    SectionSubject::firstOrCreate(
                        [
                            'section_id' => $sectionId,
                            'subject_id' => $subject->id,
                            'academic_year' => $ay->name,
                        ],
                        [
                            'branch_id' => $branch->id,
                            'school_id' => $school->id,
                            'academic_year_id' => $ay->id,
                            'teacher_id' => $teacherId,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }

    private function seedExams(School $school, Branch $branch, $ay, ?User $actor): void
    {
        $enteredBy = $actor?->id ?? $this->fallbackActorId($branch);
        $year = substr((string) $ay->name, 0, 4);
        $prefix = strtoupper($this->slugFor($branch));

        $term = ExamTerm::firstOrCreate(
            ['branch_id' => $branch->id, 'code' => $prefix.'-T1-'.$year],
            [
                'name' => 'Term 1',
                'academic_year' => $ay->name,
                'school_id' => $school->id,
                'start_date' => $year.'-04-01',
                'end_date' => $year.'-09-30',
                'weightage' => 40,
                'description' => 'First term for '.$branch->name,
                'is_active' => true,
            ]
        );

        foreach (['1', '2'] as $grade) {
            $exam = Exam::firstOrCreate(
                ['branch_id' => $branch->id, 'exam_term_id' => $term->id, 'name' => 'Term 1 Examination - Grade '.$grade],
                [
                    'school_id' => $school->id,
                    'exam_type' => 'Midterm',
                    'academic_year' => $ay->name,
                    'academic_year_id' => $ay->id,
                    'start_date' => $year.'-09-01',
                    'end_date' => $year.'-09-10',
                    'total_marks' => 100,
                    'passing_marks' => 35,
                    'description' => 'Term 1 midterm for Grade '.$grade,
                    'created_by' => $enteredBy,
                    'is_active' => true,
                ]
            );
            if (Schema::hasColumn('exams', 'grade_level')) {
                DB::table('exams')->where('id', $exam->id)->update(['grade_level' => $grade]);
            }

            $subjects = DB::table('subjects')
                ->where('branch_id', $branch->id)
                ->where('grade_level', $grade)
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->get(['id', 'name', 'teacher_id']);

            $day = 1;
            foreach ($subjects as $subject) {
                foreach (['A', 'B', 'C'] as $section) {
                    $students = DB::table('students')
                        ->where('branch_id', $branch->id)
                        ->where('grade', $grade)
                        ->where('section', $section)
                        ->where('student_status', 'Active')
                        ->whereNull('deleted_at')
                        ->get(['user_id']);
                    if ($students->isEmpty()) {
                        continue;
                    }

                    $schedule = ExamSchedule::firstOrCreate(
                        [
                            'exam_id' => $exam->id,
                            'subject_id' => $subject->id,
                            'grade' => $grade,
                            'section' => $section,
                        ],
                        [
                            'branch_id' => $branch->id,
                            'exam_date' => $year.'-09-'.str_pad((string) min($day, 9), 2, '0', STR_PAD_LEFT),
                            'start_time' => '09:30:00',
                            'end_time' => '12:30:00',
                            'duration' => 180,
                            'total_marks' => 100,
                            'passing_marks' => 35,
                            'room_number' => 'R-'.$grade.$section,
                            'invigilator_id' => $subject->teacher_id,
                            'instructions' => 'Bring your own stationery.',
                        ]
                    );
                    DB::table('exam_schedules')->where('id', $schedule->id)->update([
                        'status' => 'Completed',
                        'is_active' => true,
                    ]);

                    foreach ($students as $i => $student) {
                        $isAbsent = ($i % 12 === 11);
                        $marks = $isAbsent ? 0 : (38 + (($i * 11 + (int) $subject->id * 5) % 57));
                        $existing = DB::table('exam_marks')
                            ->where('exam_schedule_id', $schedule->id)
                            ->where('student_id', $student->user_id)
                            ->first();
                        $payload = [
                            'subject_id' => $subject->id,
                            'marks_obtained' => $marks,
                            'total_marks' => 100,
                            'percentage' => $marks,
                            'grade' => $this->letterGrade($marks),
                            'is_absent' => $isAbsent,
                            'is_pass' => ! $isAbsent && $marks >= 35,
                            'remarks' => $isAbsent ? 'Absent - medical leave' : ($marks >= 75 ? 'Excellent work' : 'Good effort'),
                            'status' => 'Published',
                            'entered_by' => $enteredBy,
                            'approved_by' => $enteredBy,
                            'approved_at' => now(),
                            'updated_at' => now(),
                        ];
                        if ($existing) {
                            DB::table('exam_marks')->where('id', $existing->id)->update($payload);
                        } else {
                            DB::table('exam_marks')->insert(array_merge($payload, [
                                'exam_schedule_id' => $schedule->id,
                                'student_id' => $student->user_id,
                                'created_at' => now(),
                            ]));
                        }
                    }
                }
                $day++;
            }

            $studentsForGrade = DB::table('students')
                ->where('branch_id', $branch->id)
                ->where('grade', $grade)
                ->where('student_status', 'Active')
                ->whereNull('deleted_at')
                ->get(['user_id']);

            foreach ($studentsForGrade as $student) {
                $agg = DB::table('exam_marks')
                    ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
                    ->where('exam_schedules.exam_id', $exam->id)
                    ->where('exam_marks.student_id', $student->user_id)
                    ->selectRaw('SUM(exam_marks.marks_obtained) obtained, SUM(exam_marks.total_marks) total')
                    ->first();
                $obtained = (float) ($agg->obtained ?? 0);
                $total = (float) ($agg->total ?? 0);
                $pct = $total > 0 ? round($obtained / $total * 100, 2) : 0;
                ExamResult::updateOrCreate(
                    ['exam_id' => $exam->id, 'student_id' => $student->user_id],
                    [
                        'marks_obtained' => $obtained,
                        'grade' => $this->letterGrade($pct),
                        'percentage' => $pct,
                        'is_pass' => $pct >= 35,
                    ]
                );
            }
        }
    }

    private function seedLeaves(School $school, Branch $branch, $ay, ?User $actor): void
    {
        $approver = $actor?->id ?? $this->fallbackActorId($branch);
        $year = substr((string) $ay->name, 0, 4);
        $now = now();

        $studentLeaves = [
            [$this->studentEmail($branch, '1', 'A', 1), 'Sick Leave', 'Approved', $year.'-08-04', $year.'-08-05', 'Fever and cold'],
            [$this->studentEmail($branch, '1', 'A', 2), 'Casual Leave', 'Pending', $year.'-08-11', $year.'-08-11', 'Family function'],
            [$this->studentEmail($branch, '1', 'B', 1), 'Medical Leave', 'Rejected', $year.'-08-18', $year.'-08-20', 'Surgery recovery'],
            [$this->studentEmail($branch, '1', 'C', 5), 'Family Emergency', 'Approved', $year.'-09-02', $year.'-09-03', 'Grandparent hospitalization'],
            [$this->studentEmail($branch, '2', 'A', 1), 'Sick Leave', 'Approved', $year.'-08-25', $year.'-08-26', 'Viral fever'],
            [$this->studentEmail($branch, '2', 'B', 3), 'Casual Leave', 'Pending', $year.'-09-08', $year.'-09-08', 'Sibling wedding'],
            [$this->studentEmail($branch, '2', 'C', 10), 'Other', 'Cancelled', $year.'-07-14', $year.'-07-14', 'Travel cancelled'],
            [$this->studentEmail($branch, '2', 'A', 12), 'Medical Leave', 'Approved', $year.'-09-10', $year.'-09-12', 'Dental procedure'],
        ];

        foreach ($studentLeaves as $row) {
            $userId = DB::table('users')->where('email', $row[0])->value('id');
            if (! $userId) {
                continue;
            }
            $payload = [
                'branch_id' => $branch->id,
                'to_date' => $row[4],
                'total_days' => $this->days($row[3], $row[4]),
                'leave_type' => $row[1],
                'status' => $row[2],
                'reason' => $row[5],
                'approved_by' => $row[2] === 'Approved' ? $approver : null,
                'approved_at' => $row[2] === 'Approved' ? $now : null,
                'created_by' => $approver,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('student_leaves', 'school_id')) {
                $payload['school_id'] = $school->id;
            }
            if (Schema::hasColumn('student_leaves', 'academic_year_id')) {
                $payload['academic_year_id'] = $ay->id;
            }
            $existing = DB::table('student_leaves')->where('student_id', $userId)->where('from_date', $row[3])->first();
            if ($existing) {
                DB::table('student_leaves')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('student_leaves')->insert(array_merge($payload, [
                    'student_id' => $userId,
                    'from_date' => $row[3],
                    'created_at' => $now,
                ]));
            }
        }

        $teacherLeaves = [
            [$this->email($branch, 'teacher01'), 'Casual Leave', 'Approved', $year.'-08-06', $year.'-08-06', 'Personal work', $this->email($branch, 'teacher09')],
            [$this->email($branch, 'teacher03'), 'Sick Leave', 'Pending', $year.'-08-13', $year.'-08-14', 'Viral fever', null],
            [$this->email($branch, 'teacher04'), 'Medical Leave', 'Approved', $year.'-07-21', $year.'-07-22', 'Medical checkup', null],
            [$this->email($branch, 'teacher07'), 'Casual Leave', 'Rejected', $year.'-09-04', $year.'-09-04', 'Personal travel', null],
            [$this->email($branch, 'teacher08'), 'Unpaid Leave', 'Approved', $year.'-09-16', $year.'-09-17', 'Family function', $this->email($branch, 'teacher05')],
        ];

        foreach ($teacherLeaves as $row) {
            $userId = DB::table('users')->where('email', $row[0])->value('id');
            if (! $userId) {
                continue;
            }
            $payload = [
                'branch_id' => $branch->id,
                'to_date' => $row[4],
                'total_days' => $this->days($row[3], $row[4]),
                'leave_type' => $row[1],
                'status' => $row[2],
                'reason' => $row[5],
                'substitute_teacher_id' => $row[6] ? DB::table('users')->where('email', $row[6])->value('id') : null,
                'approved_by' => $row[2] === 'Approved' ? $approver : null,
                'approved_at' => $row[2] === 'Approved' ? $now : null,
                'created_by' => $approver,
                'updated_at' => $now,
            ];
            if (Schema::hasColumn('teacher_leaves', 'school_id')) {
                $payload['school_id'] = $school->id;
            }
            if (Schema::hasColumn('teacher_leaves', 'academic_year_id')) {
                $payload['academic_year_id'] = $ay->id;
            }
            $existing = DB::table('teacher_leaves')->where('teacher_id', $userId)->where('from_date', $row[3])->first();
            if ($existing) {
                DB::table('teacher_leaves')->where('id', $existing->id)->update($payload);
            } else {
                DB::table('teacher_leaves')->insert(array_merge($payload, [
                    'teacher_id' => $userId,
                    'from_date' => $row[3],
                    'created_at' => $now,
                ]));
            }
        }
    }

    private function seedFees(School $school, Branch $branch, $ay, ?User $actor): void
    {
        $creator = $actor?->id ?? $this->fallbackActorId($branch);
        $today = now()->toDateString();
        $dueDate = substr((string) $ay->name, 0, 4).'-07-15';
        $prefix = strtoupper($this->slugFor($branch));

        $types = [
            ['name' => 'Tuition Fee', 'code' => $prefix.'-TUI', 'mandatory' => true, 'amount' => ['1' => 42000, '2' => 43000]],
            ['name' => 'Transport Fee', 'code' => $prefix.'-TRA', 'mandatory' => false, 'amount' => ['1' => 12000, '2' => 12000]],
            ['name' => 'Exam Fee', 'code' => $prefix.'-EXM', 'mandatory' => true, 'amount' => ['1' => 2500, '2' => 2500]],
            ['name' => 'Library Fee', 'code' => $prefix.'-LIB', 'mandatory' => false, 'amount' => ['1' => 1500, '2' => 1500]],
        ];

        foreach ($types as $type) {
            FeeType::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $type['code']],
                [
                    'name' => $type['name'],
                    'description' => $type['name'].' for '.$branch->name,
                    'academic_year_id' => $ay->id,
                    'school_id' => $school->id,
                    'is_mandatory' => $type['mandatory'],
                    'is_refundable' => false,
                    'is_active' => true,
                ]
            );
        }

        $structures = [];
        foreach (['1', '2'] as $grade) {
            foreach ($types as $type) {
                $amount = $type['amount'][$grade];
                $structures[$grade][$type['name']] = FeeStructure::firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'grade' => $grade,
                        'fee_type' => $type['name'],
                        'academic_year' => $ay->name,
                    ],
                    [
                        'school_id' => $school->id,
                        'amount' => $amount,
                        'academic_year_id' => $ay->id,
                        'due_date' => $dueDate,
                        'description' => $type['name'].' for Grade '.$grade,
                        'tuition_fee' => $type['name'] === 'Tuition Fee' ? $amount : 0,
                        'exam_fee' => $type['name'] === 'Exam Fee' ? $amount : 0,
                        'library_fee' => $type['name'] === 'Library Fee' ? $amount : 0,
                        'transport_fee' => $type['name'] === 'Transport Fee' ? $amount : 0,
                        'total_amount' => $amount,
                        'is_active' => true,
                        'created_by' => $creator,
                    ]
                );
            }
        }

        $paymentPlan = [
            [$this->studentEmail($branch, '1', 'A', 1), '1', 'Tuition Fee', 1, 'Completed', 'Cash'],
            [$this->studentEmail($branch, '1', 'A', 2), '1', 'Tuition Fee', 0.4, 'Partial', 'Online'],
            [$this->studentEmail($branch, '1', 'A', 3), '1', 'Exam Fee', 1, 'Completed', 'Card'],
            [$this->studentEmail($branch, '1', 'B', 1), '1', 'Tuition Fee', 1, 'Completed', 'Online'],
            [$this->studentEmail($branch, '1', 'C', 1), '1', 'Library Fee', 1, 'Completed', 'Cash'],
            [$this->studentEmail($branch, '2', 'A', 1), '2', 'Tuition Fee', 1, 'Completed', 'Bank Transfer'],
            [$this->studentEmail($branch, '2', 'A', 2), '2', 'Tuition Fee', 0.5, 'Partial', 'Cheque'],
            [$this->studentEmail($branch, '2', 'B', 1), '2', 'Transport Fee', 1, 'Completed', 'Online'],
            [$this->studentEmail($branch, '2', 'C', 1), '2', 'Exam Fee', 1, 'Completed', 'Cash'],
            [$this->studentEmail($branch, '2', 'A', 5), '2', 'Library Fee', 1, 'Completed', 'Card'],
            [$this->studentEmail($branch, '1', 'B', 5), '1', 'Transport Fee', 1, 'Completed', 'Online'],
            [$this->studentEmail($branch, '2', 'C', 8), '2', 'Tuition Fee', 1, 'Completed', 'Cash'],
        ];

        foreach ($paymentPlan as $row) {
            $userId = DB::table('users')->where('email', $row[0])->value('id');
            $structure = $structures[$row[1]][$row[2]] ?? null;
            if (! $userId || ! $structure) {
                continue;
            }
            if (FeePayment::where('student_id', $userId)->where('fee_structure_id', $structure->id)->exists()) {
                continue;
            }
            $amount = round(((float) $structure->amount) * $row[3], 2);
            FeePayment::create([
                'fee_structure_id' => $structure->id,
                'student_id' => $userId,
                'branch_id' => $branch->id,
                'school_id' => $school->id,
                'amount_paid' => $amount,
                'total_amount' => $amount,
                'payment_date' => $today,
                'payment_method' => $row[5],
                'discount_amount' => 0,
                'late_fee' => 0,
                'payment_status' => $row[4],
                'academic_year' => $ay->name,
                'academic_year_id' => $ay->id,
                'remarks' => $row[2].' payment',
                'created_by' => $creator,
            ]);
        }

        $categories = [
            ['name' => 'Tuition Income', 'code' => 'GV-INC-TUI', 'type' => 'Income', 'sub' => 'Operating'],
            ['name' => 'Donation', 'code' => 'GV-INC-DON', 'type' => 'Income', 'sub' => 'Other'],
            ['name' => 'Staff Salaries', 'code' => 'GV-EXP-SAL', 'type' => 'Expense', 'sub' => 'Payroll'],
            ['name' => 'Utilities', 'code' => 'GV-EXP-UTL', 'type' => 'Expense', 'sub' => 'Operating'],
            ['name' => 'Maintenance', 'code' => 'GV-EXP-MNT', 'type' => 'Expense', 'sub' => 'Operating'],
        ];
        $catIds = [];
        foreach ($categories as $cat) {
            $record = AccountCategory::firstOrCreate(
                ['school_id' => $school->id, 'code' => $cat['code']],
                [
                    'branch_id' => $branch->id,
                    'academic_year_id' => $ay->id,
                    'name' => $cat['name'],
                    'type' => $cat['type'],
                    'sub_type' => $cat['sub'],
                    'description' => $cat['name'],
                    'is_active' => true,
                ]
            );
            $catIds[$cat['code']] = $record;
        }

        $txPlan = [
            ['code' => 'GV-INC-TUI', 'amount' => 250000, 'status' => 'Approved', 'method' => 'Bank Transfer', 'party' => 'Fee Collection', 'suffix' => 'INC-001'],
            ['code' => 'GV-INC-DON', 'amount' => 50000, 'status' => 'Approved', 'method' => 'UPI', 'party' => 'Alumni Trust', 'suffix' => 'INC-002'],
            ['code' => 'GV-EXP-SAL', 'amount' => 180000, 'status' => 'Approved', 'method' => 'Bank Transfer', 'party' => 'Payroll', 'suffix' => 'EXP-001'],
            ['code' => 'GV-EXP-UTL', 'amount' => 24000, 'status' => 'Pending', 'method' => 'Check', 'party' => 'Power Board', 'suffix' => 'EXP-002'],
        ];
        foreach ($txPlan as $tx) {
            $category = $catIds[$tx['code']];
            Transaction::firstOrCreate(
                ['transaction_number' => $prefix.'-'.$tx['suffix']],
                [
                    'branch_id' => $branch->id,
                    'school_id' => $school->id,
                    'category_id' => $category->id,
                    'transaction_date' => $today,
                    'type' => $category->type,
                    'amount' => $tx['amount'],
                    'party_name' => $tx['party'],
                    'party_type' => $category->type === 'Income' ? 'Customer' : 'Vendor',
                    'payment_method' => $tx['method'],
                    'description' => $tx['party'].' - '.$category->name,
                    'status' => $tx['status'],
                    'created_by' => $creator,
                    'approved_by' => $tx['status'] === 'Approved' ? $creator : null,
                    'approved_at' => $tx['status'] === 'Approved' ? now() : null,
                    'financial_year' => $ay->name,
                    'month' => now()->format('F'),
                ]
            );
        }
    }

    private function seedHolidays(School $school, Branch $branch, $ay, ?User $actor): void
    {
        $startYear = (int) substr((string) $ay->name, 0, 4);
        $endYear = $startYear + 1;
        $createdBy = $actor?->id ?? $this->fallbackActorId($branch);
        $holidays = [
            ['title' => 'Independence Day', 'start' => $startYear.'-08-15', 'end' => $startYear.'-08-15', 'type' => 'National', 'color' => '#2e7d32'],
            ['title' => 'Teachers Day', 'start' => $startYear.'-09-05', 'end' => $startYear.'-09-05', 'type' => 'School', 'color' => '#1565c0'],
            ['title' => 'Gandhi Jayanti', 'start' => $startYear.'-10-02', 'end' => $startYear.'-10-02', 'type' => 'National', 'color' => '#2e7d32'],
            ['title' => 'Diwali Break', 'start' => $startYear.'-10-20', 'end' => $startYear.'-10-25', 'type' => 'School', 'color' => '#ef6c00'],
            ['title' => 'Annual Day', 'start' => $startYear.'-11-15', 'end' => $startYear.'-11-15', 'type' => 'School', 'color' => '#6a1b9a'],
            ['title' => 'Christmas', 'start' => $startYear.'-12-25', 'end' => $startYear.'-12-25', 'type' => 'National', 'color' => '#c62828'],
            ['title' => 'Republic Day', 'start' => $endYear.'-01-26', 'end' => $endYear.'-01-26', 'type' => 'National', 'color' => '#2e7d32'],
            ['title' => 'Holi', 'start' => $endYear.'-03-03', 'end' => $endYear.'-03-04', 'type' => 'School', 'color' => '#ef6c00'],
        ];

        foreach ($holidays as $row) {
            Holiday::firstOrCreate(
                [
                    'branch_id' => $branch->id,
                    'title' => $row['title'],
                    'start_date' => $row['start'],
                ],
                [
                    'school_id' => $school->id,
                    'name' => $row['title'],
                    'description' => $row['title'].' holiday for '.$branch->name,
                    'end_date' => $row['end'],
                    'date' => $row['start'],
                    'type' => $row['type'],
                    'color' => $row['color'],
                    'is_recurring' => $row['type'] === 'National',
                    'academic_year' => $ay->name,
                    'academic_year_id' => $ay->id,
                    'is_active' => true,
                    'created_by' => $createdBy,
                ]
            );
        }
    }

    private function email(Branch $branch, string $local): string
    {
        return $local.'.'.$this->slugFor($branch).'@greenvalley.demo';
    }

    private function studentEmail(Branch $branch, string $grade, string $section, int $seq): string
    {
        return sprintf(
            'student.g%s%s.%02d.%s@greenvalley.demo',
            $grade,
            strtolower($section),
            $seq,
            $this->slugFor($branch)
        );
    }

    private function employeeId(Branch $branch, string $prefix, int $seq): string
    {
        return sprintf('%s-%s-%02d', strtoupper($this->slugFor($branch)), $prefix, $seq);
    }

    private function phone(Branch $branch, int $series, int $seq): string
    {
        return sprintf('+9198%02d%03d%04d', $branch->id % 100, $series % 1000, $seq % 10000);
    }

    private function fallbackActorId(Branch $branch): ?int
    {
        $onBranch = User::query()
            ->where('branch_id', $branch->id)
            ->whereIn('role', ['BranchAdmin', 'SuperAdmin'])
            ->value('id');
        if ($onBranch) {
            return (int) $onBranch;
        }

        $schoolBranchIds = Branch::query()
            ->where('school_id', $branch->school_id)
            ->pluck('id')
            ->all();

        $onSchool = User::query()
            ->whereIn('role', ['SuperAdmin', 'BranchAdmin'])
            ->where(function ($q) use ($schoolBranchIds, $branch) {
                if (! empty($schoolBranchIds)) {
                    $q->whereIn('branch_id', $schoolBranchIds);
                }
                $companyId = School::query()->where('id', $branch->school_id)->value('company_id');
                if ($companyId) {
                    $q->orWhere('company_id', $companyId);
                }
            })
            ->value('id');

        return $onSchool ? (int) $onSchool : User::query()->where('role', 'SuperAdmin')->value('id');
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

    private function days(string $from, string $to): int
    {
        return max(1, Carbon::parse($from)->startOfDay()->diffInDays(Carbon::parse($to)->startOfDay()) + 1);
    }

    private function letterGrade(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'A+',
            $pct >= 80 => 'A',
            $pct >= 70 => 'B+',
            $pct >= 60 => 'B',
            $pct >= 50 => 'C',
            $pct >= 40 => 'D',
            default => 'F',
        };
    }
}
