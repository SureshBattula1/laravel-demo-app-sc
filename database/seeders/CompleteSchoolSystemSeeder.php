<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Subject;
use App\Models\Section;
use App\Models\ClassModel;
use App\Models\Teacher;
use App\Models\Student;
use Carbon\Carbon;
use Faker\Factory as Faker;

class CompleteSchoolSystemSeeder extends Seeder
{
    private $faker;
    private $academicYear = '2024-2025';
    private $grades = ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12'];
    private $departments = [];
    private $subjects = [];
    private $branches = [];

    public function run(): void
    {
        $this->faker = Faker::create('en_IN');
        
        $this->command->info('🌱 Starting Complete School System Seeder...');
        $this->command->info('Creating 3 schools, 5 departments, 5 subjects, 10 teachers per school, sections, and students...');
        $this->command->info('Note: Existing branches will be reused if found.');

        DB::beginTransaction();

        try {
            // 1. Create or Get 3 Branches (Schools)
            $this->command->info('📚 Creating or getting 3 branches (schools)...');
            $this->branches = $this->createBranches();

            // 2. Create 5 Departments (shared across all branches)
            $this->command->info('🏢 Creating 5 departments...');
            $this->departments = $this->createDepartments();

            // 3. Create 5 Subjects per branch (assigned to departments)
            $this->command->info('📖 Creating 5 subjects per branch...');
            $this->subjects = $this->createSubjects();

            // 4. Create Sections (at least 2 per grade per branch)
            $this->command->info('📋 Creating sections (2 per grade per branch)...');
            $sections = $this->createSections();

            // 5. Create Classes (grade-section combinations)
            $this->command->info('🏫 Creating classes (grade-section combinations)...');
            $classes = $this->createClasses($sections);

            // 6. Create 10 Teachers per branch (30 total)
            $this->command->info('👨‍🏫 Creating 10 teachers per branch (30 total)...');
            $teachers = $this->createTeachers();

            // 7. Create Students (at least 20 per section)
            $this->command->info('👨‍🎓 Creating students (20+ per section)...');
            $students = $this->createStudents($sections);

            // 8. Assign Subjects to Sections
            $this->command->info('🔗 Assigning subjects to sections...');
            $this->assignSubjectsToSections($sections);

            // 9. Assign Teachers to Subjects/Sections
            $this->command->info('👨‍🏫 Assigning teachers to subjects and sections...');
            $this->assignTeachersToSubjects($teachers, $sections);

            // 10. Update section strengths
            $this->command->info('📊 Updating section strengths...');
            $this->updateSectionStrengths();

            DB::commit();

            $this->command->info('✅ Seeding completed successfully!');
            $this->displaySummary();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    private function createBranches(): array
    {
        $branches = [];
        $cities = ['Mumbai', 'Delhi', 'Bangalore'];
        $states = ['Maharashtra', 'Delhi', 'Karnataka'];
        $pincodes = ['400001', '110001', '560001'];

        for ($i = 1; $i <= 3; $i++) {
            $branch = Branch::firstOrCreate(
                ['code' => "SCHOOL{$i}"],
                [
                'name' => "Excel School {$i}",
                'branch_type' => 'School',
                'address' => "{$this->faker->buildingNumber()} {$this->faker->streetName()}, {$cities[$i-1]}",
                'city' => $cities[$i-1],
                'state' => $states[$i-1],
                'country' => 'India',
                'pincode' => $pincodes[$i-1],
                'phone' => $this->faker->numerify('##########'),
                'email' => "school{$i}@excel.edu",
                'website' => "https://school{$i}.excel.edu",
                'principal_name' => $this->faker->name(),
                'principal_contact' => $this->faker->numerify('##########'),
                'principal_email' => "principal{$i}@excel.edu",
                'established_date' => $this->faker->dateTimeBetween('-20 years', '-5 years'),
                'opening_date' => $this->faker->dateTimeBetween('-5 years', '-1 year'),
                'board' => $this->faker->randomElement(['CBSE', 'ICSE', 'State Board']),
                'affiliation_number' => "AFF{$i}" . $this->faker->numerify('####'),
                'current_academic_year' => $this->academicYear,
                'academic_year_start' => '04-01',
                'academic_year_end' => '03-31',
                'grades_offered' => $this->grades,
                'total_capacity' => 1000,
                'current_enrollment' => 0,
                'is_main_branch' => $i === 1,
                'is_active' => true,
                'status' => 'Active',
                'has_library' => true,
                'has_lab' => true,
                'has_sports' => true,
                'has_canteen' => true,
                ]
            );

            $branches[] = $branch;
            if ($branch->wasRecentlyCreated) {
                $this->command->info("   ✓ Created branch: {$branch->name} (ID: {$branch->id})");
            } else {
                $this->command->info("   ⊙ Branch already exists: {$branch->name} (ID: {$branch->id})");
            }
        }

        return $branches;
    }

    private function createDepartments(): array
    {
        $departmentNames = [
            'Mathematics',
            'Science',
            'Languages',
            'Social Studies',
            'Physical Education'
        ];

        $departments = [];
        foreach ($this->branches as $branch) {
            foreach ($departmentNames as $index => $name) {
                $dept = Department::firstOrCreate(
                    [
                        'name' => $name,
                        'branch_id' => $branch->id,
                    ],
                    [
                        'head' => $this->faker->name(),
                        'description' => "Department of {$name} at {$branch->name}",
                        'is_active' => true,
                        'established_date' => $this->faker->dateTimeBetween('-10 years', '-1 year'),
                    ]
                );
                $departments[$branch->id][] = $dept;
            }
        }

        return $departments;
    }

    private function createSubjects(): array
    {
        $subjectData = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'type' => 'Core', 'department_index' => 0],
            ['name' => 'English', 'code' => 'ENG', 'type' => 'Core', 'department_index' => 2],
            ['name' => 'Science', 'code' => 'SCI', 'type' => 'Core', 'department_index' => 1],
            ['name' => 'Social Studies', 'code' => 'SST', 'type' => 'Core', 'department_index' => 3],
            ['name' => 'Physical Education', 'code' => 'PE', 'type' => 'Activity', 'department_index' => 4],
        ];

        $subjects = [];
        foreach ($this->branches as $branch) {
            $branchSubjects = [];
            foreach ($subjectData as $subjData) {
                $department = $this->departments[$branch->id][$subjData['department_index']];
                
                // Create subject for each grade level
                foreach ($this->grades as $grade) {
                    $subjectCode = "{$branch->code}-{$subjData['code']}-{$grade}";
                    $subject = Subject::firstOrCreate(
                        [
                            'code' => $subjectCode,
                        ],
                        [
                            'name' => $subjData['name'],
                            'branch_id' => $branch->id,
                            'department_id' => $department->id,
                            'grade_level' => $grade,
                            'type' => $subjData['type'],
                            'description' => "{$subjData['name']} for Grade {$grade}",
                            'credits' => $subjData['type'] === 'Core' ? 4 : 2,
                            'is_active' => true,
                        ]
                    );
                    $branchSubjects[] = $subject;
                }
            }
            $subjects[$branch->id] = $branchSubjects;
        }

        return $subjects;
    }

    private function createSections(): array
    {
        $sections = [];
        $sectionNames = ['A', 'B']; // At least 2 sections per grade

        foreach ($this->branches as $branch) {
            $branchSections = [];
            foreach ($this->grades as $grade) {
                foreach ($sectionNames as $sectionName) {
                    $sectionCode = "{$branch->code}-G{$grade}-{$sectionName}";
                    $section = Section::firstOrCreate(
                        [
                            'code' => $sectionCode,
                        ],
                        [
                            'branch_id' => $branch->id,
                            'name' => $sectionName,
                            'grade_level' => $grade,
                            'capacity' => 40,
                            'current_strength' => 0,
                            'room_number' => "R-{$grade}-{$sectionName}",
                            'description' => "Grade {$grade} Section {$sectionName}",
                            'is_active' => true,
                        ]
                    );
                    $branchSections[] = $section;
                }
            }
            $sections[$branch->id] = $branchSections;
        }

        return $sections;
    }

    private function createClasses(array $sections): array
    {
        $classes = [];

        foreach ($sections as $branchId => $branchSections) {
            foreach ($branchSections as $section) {
                $class = ClassModel::firstOrCreate(
                    [
                        'branch_id' => $branchId,
                        'grade' => $section->grade_level,
                        'section' => $section->name,
                        'academic_year' => $this->academicYear,
                    ],
                    [
                        'class_name' => "Grade {$section->grade_level}-{$section->name}",
                        'capacity' => $section->capacity,
                        'current_strength' => 0,
                        'room_number' => $section->room_number,
                        'is_active' => true,
                    ]
                );
                $classes[] = $class;
            }
        }

        return $classes;
    }

    private function createTeachers(): array
    {
        $teachers = [];
        $designations = ['Teacher', 'Senior Teacher', 'Head Teacher', 'Vice Principal'];
        $employeeTypes = ['Permanent', 'Contract'];
        $qualifications = ['B.Ed', 'M.Ed', 'M.Sc', 'M.A', 'Ph.D'];
        $specializations = ['Mathematics', 'Science', 'English', 'Social Studies', 'Physical Education'];

        foreach ($this->branches as $branch) {
            $branchTeachers = [];
            for ($i = 1; $i <= 10; $i++) {
                $firstName = $this->faker->firstName();
                $lastName = $this->faker->lastName();
                $email = "teacher{$branch->id}.{$i}@excel.edu";

                // Create or get User
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => $this->faker->numerify('##########'),
                        'password' => Hash::make('Welcome@123'),
                        'role' => 'Teacher',
                        'user_type' => 'Teacher',
                        'branch_id' => $branch->id,
                        'is_active' => true,
                    ]
                );

                // Create or get Teacher record
                $employeeId = "TCH-{$branch->code}-{$i}";
                $teacher = Teacher::firstOrCreate(
                    ['employee_id' => $employeeId],
                    [
                    'user_id' => $user->id,
                    'branch_id' => $branch->id,
                    'department_id' => $this->departments[$branch->id][$this->faker->numberBetween(0, 4)]->id,
                    'category_type' => 'Teaching',
                    'designation' => $this->faker->randomElement($designations),
                    'employee_type' => $this->faker->randomElement($employeeTypes),
                    'joining_date' => $this->faker->dateTimeBetween('-5 years', '-1 month'),
                    'qualification' => $this->faker->randomElement($qualifications),
                    'experience_years' => $this->faker->randomFloat(1, 1, 15),
                    'specialization' => $this->faker->randomElement($specializations),
                    'date_of_birth' => $this->faker->dateTimeBetween('-50 years', '-25 years'),
                    'gender' => $this->faker->randomElement(['Male', 'Female']),
                    'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
                    'current_address' => $this->faker->address(),
                    'city' => $branch->city,
                    'state' => $branch->state,
                    'pincode' => $branch->pincode,
                    'emergency_contact_name' => $this->faker->name(),
                    'emergency_contact_phone' => $this->faker->numerify('##########'),
                    'emergency_contact_relation' => $this->faker->randomElement(['Spouse', 'Father', 'Mother', 'Sibling']),
                    'basic_salary' => $this->faker->numberBetween(30000, 80000),
                    'bank_name' => $this->faker->randomElement(['SBI', 'HDFC', 'ICICI', 'Axis']),
                    'bank_account_number' => $this->faker->numerify('################'),
                    'bank_ifsc_code' => $this->faker->bothify('????#######'),
                    'pan_number' => $this->faker->bothify('?????####?'),
                    'aadhar_number' => $this->faker->numerify('############'),
                    'teacher_status' => 'Active',
                ]);

                $branchTeachers[] = $teacher;
            }
            $teachers[$branch->id] = $branchTeachers;
        }

        return $teachers;
    }

    private function createStudents(array $sections): array
    {
        $students = [];
        $studentsPerSection = 20; // At least 20 students per section

        foreach ($sections as $branchId => $branchSections) {
            $branchStudents = [];
            $rollNumber = 1;

            foreach ($branchSections as $section) {
                for ($i = 1; $i <= $studentsPerSection; $i++) {
                    $firstName = $this->faker->firstName();
                    $lastName = $this->faker->lastName();
                    $email = "student{$branchId}.{$section->id}.{$i}@excel.edu";

                    // Get branch for student data
                    $branch = Branch::find($branchId);
                    
                    // Create Student record first to get admission number
                    $admissionNumber = "STU-{$branch->code}-" . str_pad($rollNumber, 4, '0', STR_PAD_LEFT);
                    
                    // Create or get User
                    $user = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'phone' => $this->faker->numerify('##########'),
                            'password' => Hash::make('Welcome@123'),
                            'role' => 'Student',
                            'user_type' => 'Student',
                            'branch_id' => $branchId,
                            'is_active' => true,
                        ]
                    );

                    // Create or get Student record
                    $student = Student::firstOrCreate(
                        ['admission_number' => $admissionNumber],
                        [
                        'user_id' => $user->id,
                        'branch_id' => $branchId,
                        'admission_number' => $admissionNumber,
                        'admission_date' => $this->faker->dateTimeBetween('-1 year', '-1 month'),
                        'roll_number' => (string)$rollNumber,
                        'registration_number' => "REG-{$branch->code}-" . str_pad($rollNumber, 4, '0', STR_PAD_LEFT),
                        'grade' => $section->grade_level,
                        'section' => $section->name,
                        'academic_year' => $this->academicYear,
                        'date_of_birth' => $this->calculateDateOfBirth($section->grade_level),
                        'gender' => $this->faker->randomElement(['Male', 'Female']),
                        'blood_group' => $this->faker->randomElement(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-']),
                        'religion' => $this->faker->randomElement(['Hindu', 'Muslim', 'Christian', 'Sikh', 'Buddhist']),
                        'nationality' => 'Indian',
                        'mother_tongue' => $this->faker->randomElement(['Hindi', 'English', 'Marathi', 'Tamil', 'Telugu', 'Bengali']),
                        'category' => $this->faker->randomElement(['General', 'OBC', 'SC', 'ST']),
                        'current_address' => $this->faker->address(),
                        'permanent_address' => $this->faker->address(),
                        'city' => $branch->city,
                        'state' => $branch->state,
                        'country' => 'India',
                        'pincode' => $branch->pincode,
                        'father_name' => $this->faker->name('male'),
                        'father_phone' => $this->faker->numerify('##########'),
                        'father_email' => $this->faker->email(),
                        'father_occupation' => $this->faker->jobTitle(),
                        'father_annual_income' => $this->faker->numberBetween(200000, 1500000),
                        'mother_name' => $this->faker->name('female'),
                        'mother_phone' => $this->faker->numerify('##########'),
                        'mother_email' => $this->faker->email(),
                        'mother_occupation' => $this->faker->randomElement(['Housewife', 'Teacher', 'Doctor', 'Engineer', 'Accountant']),
                        'mother_annual_income' => $this->faker->numberBetween(100000, 800000),
                        'emergency_contact_name' => $this->faker->name(),
                        'emergency_contact_phone' => $this->faker->numerify('##########'),
                        'emergency_contact_relation' => $this->faker->randomElement(['Father', 'Mother', 'Uncle', 'Aunt']),
                        'student_status' => 'Active',
                    ]);

                    $branchStudents[] = $student;
                    $rollNumber++;
                }
            }
            $students[$branchId] = $branchStudents;
        }

        return $students;
    }

    private function calculateDateOfBirth(string $grade): Carbon
    {
        // Calculate age based on grade (assuming grade 1 = age 6, grade 12 = age 17)
        $age = 6 + (int)$grade - 1;
        return Carbon::now()->subYears($age)->subMonths(rand(0, 11))->subDays(rand(0, 30));
    }

    private function assignSubjectsToSections(array $sections): void
    {
        foreach ($sections as $branchId => $branchSections) {
            $branchSubjects = $this->subjects[$branchId];
            
            foreach ($branchSections as $section) {
                // Get subjects for this grade level
                $gradeSubjects = array_filter($branchSubjects, function($subject) use ($section) {
                    return $subject->grade_level === $section->grade_level;
                });

                foreach ($gradeSubjects as $subject) {
                    DB::table('section_subjects')->updateOrInsert(
                        [
                            'section_id' => $section->id,
                            'subject_id' => $subject->id,
                            'academic_year' => $this->academicYear,
                        ],
                        [
                            'branch_id' => $branchId,
                            'weekly_periods' => $subject->type === 'Core' ? 5 : 2,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }
            }
        }
    }

    private function assignTeachersToSubjects(array $teachers, array $sections): void
    {
        foreach ($teachers as $branchId => $branchTeachers) {
            $teacherIndex = 0;
            
            // Assign class teachers to sections
            foreach ($sections[$branchId] as $section) {
                if ($teacherIndex < count($branchTeachers)) {
                    $teacher = $branchTeachers[$teacherIndex];
                    $section->update(['class_teacher_id' => $teacher->user_id]);
                    
                    // Update class teacher
                    $class = ClassModel::where('branch_id', $branchId)
                        ->where('grade', $section->grade_level)
                        ->where('section', $section->name)
                        ->first();
                    if ($class) {
                        $class->update(['class_teacher_id' => $teacher->user_id]);
                    }
                    
                    $teacher->update([
                        'is_class_teacher' => true,
                        'class_teacher_of_grade' => $section->grade_level,
                        'class_teacher_of_section' => $section->name,
                    ]);
                    
                    $teacherIndex++;
                }
            }

            // Assign teachers to subjects in section_subjects
            $sectionSubjects = DB::table('section_subjects')
                ->where('branch_id', $branchId)
                ->whereNull('teacher_id')
                ->get();

            $teacherIndex = 0;
            foreach ($sectionSubjects as $sectionSubject) {
                if ($teacherIndex >= count($branchTeachers)) {
                    $teacherIndex = 0;
                }
                $teacher = $branchTeachers[$teacherIndex];
                
                DB::table('section_subjects')
                    ->where('id', $sectionSubject->id)
                    ->update(['teacher_id' => $teacher->user_id]);
                
                // Also update subject teacher_id if not set
                DB::table('subjects')
                    ->where('id', $sectionSubject->subject_id)
                    ->whereNull('teacher_id')
                    ->update(['teacher_id' => $teacher->user_id]);
                
                $teacherIndex++;
            }
        }
    }

    private function updateSectionStrengths(): void
    {
        foreach ($this->branches as $branch) {
            $sections = Section::where('branch_id', $branch->id)->get();
            
            foreach ($sections as $section) {
                $strength = Student::where('branch_id', $branch->id)
                    ->where('grade', $section->grade_level)
                    ->where('section', $section->name)
                    ->where('student_status', 'Active')
                    ->count();
                
                $section->update(['current_strength' => $strength]);
                
                // Update class strength
                $class = ClassModel::where('branch_id', $branch->id)
                    ->where('grade', $section->grade_level)
                    ->where('section', $section->name)
                    ->first();
                if ($class) {
                    $class->update(['current_strength' => $strength]);
                }
            }
        }
    }

    private function displaySummary(): void
    {
        $this->command->info('');
        $this->command->info('📊 Seeding Summary:');
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        
        foreach ($this->branches as $branch) {
            $this->command->info("🏫 {$branch->name} (ID: {$branch->id}):");
            
            $teachersCount = Teacher::where('branch_id', $branch->id)->count();
            $studentsCount = Student::where('branch_id', $branch->id)->count();
            $sectionsCount = Section::where('branch_id', $branch->id)->count();
            $subjectsCount = Subject::where('branch_id', $branch->id)->count();
            $departmentsCount = Department::where('branch_id', $branch->id)->count();
            
            $this->command->info("   • Departments: {$departmentsCount}");
            $this->command->info("   • Subjects: {$subjectsCount}");
            $this->command->info("   • Sections: {$sectionsCount}");
            $this->command->info("   • Teachers: {$teachersCount}");
            $this->command->info("   • Students: {$studentsCount}");
            $this->command->info('');
        }
        
        $this->command->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->command->info('✅ Total Branches: ' . Branch::count());
        $this->command->info('✅ Total Teachers: ' . Teacher::count());
        $this->command->info('✅ Total Students: ' . Student::count());
        $this->command->info('✅ Total Sections: ' . Section::count());
        $this->command->info('✅ Total Subjects: ' . Subject::count());
        $this->command->info('✅ Total Departments: ' . Department::count());
    }
}

