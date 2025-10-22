<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Teacher;
use App\Models\Section;
use App\Models\ClassModel;
use Carbon\Carbon;

class DemoDataSeeder extends Seeder
{
    private $currentAcademicYear = '2024-2025';
    private $branches = [];
    private $departments = [];
    private $teachers = [];
    private $sections = [];
    private $classes = [];
    
    // Realistic data arrays
    private $firstNames = [
        'Male' => ['James', 'John', 'Robert', 'Michael', 'William', 'David', 'Richard', 'Joseph', 'Thomas', 'Charles', 
                   'Daniel', 'Matthew', 'Anthony', 'Mark', 'Donald', 'Steven', 'Paul', 'Andrew', 'Joshua', 'Kenneth',
                   'Kevin', 'Brian', 'George', 'Edward', 'Ronald', 'Timothy', 'Jason', 'Jeffrey', 'Ryan', 'Jacob',
                   'Gary', 'Nicholas', 'Eric', 'Jonathan', 'Stephen', 'Larry', 'Justin', 'Scott', 'Brandon', 'Benjamin',
                   'Samuel', 'Frank', 'Gregory', 'Raymond', 'Alexander', 'Patrick', 'Jack', 'Dennis', 'Jerry', 'Tyler'],
        'Female' => ['Mary', 'Patricia', 'Jennifer', 'Linda', 'Barbara', 'Elizabeth', 'Susan', 'Jessica', 'Sarah', 'Karen',
                     'Nancy', 'Lisa', 'Betty', 'Margaret', 'Sandra', 'Ashley', 'Kimberly', 'Emily', 'Donna', 'Michelle',
                     'Dorothy', 'Carol', 'Amanda', 'Melissa', 'Deborah', 'Stephanie', 'Rebecca', 'Sharon', 'Laura', 'Cynthia',
                     'Kathleen', 'Amy', 'Angela', 'Shirley', 'Anna', 'Brenda', 'Pamela', 'Emma', 'Nicole', 'Helen',
                     'Samantha', 'Katherine', 'Christine', 'Debra', 'Rachel', 'Carolyn', 'Janet', 'Catherine', 'Maria', 'Heather']
    ];
    
    private $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez',
                         'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas', 'Taylor', 'Moore', 'Jackson', 'Martin',
                         'Lee', 'Perez', 'Thompson', 'White', 'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson',
                         'Walker', 'Young', 'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
                         'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell', 'Carter', 'Roberts'];
    
    private $cities = [
        ['name' => 'New York', 'state' => 'New York', 'country' => 'USA'],
        ['name' => 'Los Angeles', 'state' => 'California', 'country' => 'USA'],
        ['name' => 'Chicago', 'state' => 'Illinois', 'country' => 'USA'],
        ['name' => 'Houston', 'state' => 'Texas', 'country' => 'USA'],
        ['name' => 'Phoenix', 'state' => 'Arizona', 'country' => 'USA'],
    ];
    
    private $streets = ['Main St', 'Oak Ave', 'Maple Dr', 'Park Blvd', 'Washington St', 'Lake View Rd', 
                        'Hill St', 'Cedar Ln', 'Elm St', 'Pine Ave'];
    
    private $subjects = [
        '1-5' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art', 'Physical Education', 'Music'],
        '6-8' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer Science', 'Art', 'Physical Education', 'Music'],
        '9-10' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Computer Science', 'Physical Education'],
        '11-12' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Economics', 'Computer Science', 'Business Studies']
    ];
    
    private $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    private $religions = ['Christianity', 'Islam', 'Hinduism', 'Buddhism', 'Judaism', 'Other'];
    private $categories = ['General', 'SC', 'ST', 'OBC'];

    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🚀 Starting Demo Data Seeding...');
            
            // Step 1: Create Branches
            $this->command->info('📍 Creating branches...');
            $this->createBranches();
            
            // Step 2: Create Departments
            $this->command->info('🏢 Creating departments...');
            $this->createDepartments();
            
            // Step 3: Ensure Grades exist
            $this->command->info('📚 Ensuring grades exist...');
            $this->ensureGrades();
            
            // Step 4: Create Sections
            $this->command->info('📋 Creating sections...');
            $this->createSections();
            
            // Step 5: Create Classes
            $this->command->info('🏫 Creating classes...');
            $this->createClasses();
            
            // Step 6: Create Teachers
            $this->command->info('👨‍🏫 Creating teachers (20 per branch)...');
            $this->createTeachers();
            
            // Step 7: Assign Class Teachers
            $this->command->info('👥 Assigning class teachers...');
            $this->assignClassTeachers();
            
            // Step 8: Create Students
            $this->command->info('👨‍🎓 Creating students (1000 per branch)...');
            $this->createStudents();
            
            // Step 9: Update Statistics
            $this->command->info('📊 Updating statistics...');
            $this->updateStatistics();
            
            DB::commit();
            
            $this->command->info('✅ Demo Data Seeding Completed Successfully!');
            $this->printSummary();
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    private function createBranches(): void
    {
        foreach ($this->cities as $index => $city) {
            $branch = Branch::firstOrCreate(
                ['code' => 'BR' . str_pad($index + 1, 3, '0', STR_PAD_LEFT)],
                [
                    'name' => 'Excellence Academy - ' . $city['name'],
                    'address' => rand(100, 9999) . ' ' . $this->streets[array_rand($this->streets)],
                    'city' => $city['name'],
                    'state' => $city['state'],
                    'country' => $city['country'],
                    'pincode' => str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT),
                    'phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                    'email' => strtolower(str_replace(' ', '', $city['name'])) . '@excellenceacademy.com',
                    'principal_name' => 'Dr. ' . $this->firstNames[rand(0, 1) ? 'Male' : 'Female']
                                        [array_rand($this->firstNames['Male'])] . ' ' 
                                        . $this->lastNames[array_rand($this->lastNames)],
                    'principal_contact' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                    'principal_email' => 'principal.' . strtolower(str_replace(' ', '', $city['name'])) . '@excellenceacademy.com',
                    'established_date' => Carbon::now()->subYears(rand(5, 20))->format('Y-m-d'),
                    'affiliation_number' => 'AFF-' . date('Y') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                    'is_main_branch' => $index === 0,
                    'is_active' => true,
                    'total_capacity' => 1200,
                    'current_enrollment' => 0,
                    'has_library' => true,
                    'has_lab' => true,
                    'has_sports' => true,
                    'has_canteen' => true,
                    'has_transport' => true,
                    'settings' => [
                        'academicYearStart' => 'August',
                        'academicYearEnd' => 'July',
                        'workingDays' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
                        'schoolTimings' => ['startTime' => '08:00', 'endTime' => '15:00'],
                        'currency' => 'USD',
                        'language' => 'English',
                        'timezone' => 'America/New_York'
                    ]
                ]
            );
            
            $this->branches[] = $branch;
            $this->command->info("  ✓ Created branch: {$branch->name}");
        }
    }
    
    private function createDepartments(): void
    {
        $departmentNames = [
            'Primary Education (Grades 1-5)',
            'Middle School (Grades 6-8)',
            'High School (Grades 9-10)',
            'Senior Secondary (Grades 11-12)',
            'Science Department',
            'Mathematics Department',
            'Languages Department',
            'Social Sciences Department',
            'Arts & Sports Department'
        ];
        
        foreach ($this->branches as $branch) {
            foreach ($departmentNames as $deptName) {
                $dept = Department::firstOrCreate(
                    ['name' => $deptName, 'branch_id' => $branch->id],
                    [
                        'head' => 'TBD',  // Will be assigned later
                        'description' => 'Department of ' . $deptName,
                        'established_date' => $branch->established_date,
                        'is_active' => true
                    ]
                );
                
                $this->departments[$branch->id][] = $dept;
            }
        }
        
        $this->command->info("  ✓ Created " . count($departmentNames) . " departments for each branch");
    }
    
    private function ensureGrades(): void
    {
        for ($i = 1; $i <= 12; $i++) {
            DB::table('grades')->insertOrIgnore([
                'value' => (string)$i,
                'label' => 'Grade ' . $i,
                'description' => 'Grade ' . $i . ' curriculum',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        $this->command->info("  ✓ Ensured grades 1-12 exist");
    }
    
    private function createSections(): void
    {
        $sectionNames = ['A', 'B', 'C', 'D'];
        
        foreach ($this->branches as $branch) {
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach ($sectionNames as $sectionName) {
                    $section = Section::firstOrCreate(
                        [
                            'branch_id' => $branch->id,
                            'name' => $sectionName,
                            'grade_level' => (string)$grade
                        ],
                        [
                            'code' => $branch->code . '-G' . $grade . '-' . $sectionName,
                            'capacity' => rand(30, 45),
                            'current_strength' => 0,
                            'room_number' => $grade . str_pad(array_search($sectionName, $sectionNames) + 1, 2, '0', STR_PAD_LEFT),
                            'description' => 'Grade ' . $grade . ' Section ' . $sectionName,
                            'is_active' => true
                        ]
                    );
                    
                    $this->sections[$branch->id][$grade][] = $section;
                }
            }
        }
        
        $this->command->info("  ✓ Created sections for all grades and branches");
    }
    
    private function createClasses(): void
    {
        foreach ($this->branches as $branch) {
            for ($grade = 1; $grade <= 12; $grade++) {
                if (isset($this->sections[$branch->id][$grade])) {
                    foreach ($this->sections[$branch->id][$grade] as $section) {
                        $class = ClassModel::firstOrCreate(
                            [
                                'branch_id' => $branch->id,
                                'grade' => (string)$grade,
                                'section' => $section->name,
                                'academic_year' => $this->currentAcademicYear
                            ],
                            [
                                'class_name' => 'Grade ' . $grade . '-' . $section->name,
                                'capacity' => $section->capacity,
                                'current_strength' => 0,
                                'room_number' => $section->room_number,
                                'description' => 'Grade ' . $grade . ' Section ' . $section->name . ' - Academic Year ' . $this->currentAcademicYear,
                                'is_active' => true
                            ]
                        );
                        
                        $this->classes[$branch->id][$grade][] = $class;
                    }
                }
            }
        }
        
        $this->command->info("  ✓ Created classes for all grades and sections");
    }
    
    private function createTeachers(): void
    {
        $designations = ['Senior Teacher', 'Teacher', 'Junior Teacher', 'PGT', 'TGT', 'PRT'];
        $qualifications = ['B.Ed', 'M.Ed', 'B.A. B.Ed', 'M.A. B.Ed', 'M.Sc. B.Ed', 'Ph.D.'];
        
        foreach ($this->branches as $branch) {
            for ($i = 1; $i <= 20; $i++) {
                $gender = rand(0, 1) ? 'Male' : 'Female';
                $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
                $lastName = $this->lastNames[array_rand($this->lastNames)];
                $email = strtolower($firstName . '.' . $lastName . $i . '@excellenceacademy.com');
                
                // Create User
                $user = User::firstOrCreate(
                    ['email' => $email],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                        'password' => Hash::make('Teacher@123'),
                        'role' => 'Teacher',
                        'user_type' => 'Teacher',
                        'branch_id' => $branch->id,
                        'is_active' => true,
                        'email_verified_at' => now()
                    ]
                );
                
                // Create Teacher Record - BARE MINIMUM (based on actual schema)
                $teacher = Teacher::firstOrCreate(
                    ['user_id' => $user->id],
                    [
                        'branch_id' => $branch->id,
                        'employee_id' => 'EMP-' . $branch->code . '-' . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'designation' => $designations[array_rand($designations)],
                        'employee_type' => rand(0, 1) ? 'Permanent' : 'Contract',
                        'joining_date' => Carbon::now()->subMonths(rand(1, 60))->format('Y-m-d'),
                        'date_of_birth' => Carbon::now()->subYears(rand(25, 55))->format('Y-m-d'),
                        'gender' => $gender,
                        'basic_salary' => rand(30000, 80000),
                        'subjects' => $this->getRandomSubjects(),
                        'classes_assigned' => [],
                        'is_class_teacher' => false,
                        'teacher_status' => 'Active',
                    ]
                );
                
                $this->teachers[$branch->id][] = $teacher;
            }
        }
        
        $this->command->info("  ✓ Created 20 teachers for each branch");
    }
    
    private function assignClassTeachers(): void
    {
        foreach ($this->branches as $branch) {
            if (!isset($this->classes[$branch->id]) || !isset($this->teachers[$branch->id])) {
                continue;
            }
            
            $availableTeachers = $this->teachers[$branch->id];
            $teacherIndex = 0;
            
            for ($grade = 1; $grade <= 12; $grade++) {
                if (isset($this->classes[$branch->id][$grade])) {
                    foreach ($this->classes[$branch->id][$grade] as $class) {
                        if ($teacherIndex < count($availableTeachers)) {
                            $teacher = $availableTeachers[$teacherIndex];
                            
                            // Update class with teacher
                            $class->update(['class_teacher_id' => $teacher->user_id]);
                            
                            // Update section with teacher
                            if (isset($this->sections[$branch->id][$grade])) {
                                foreach ($this->sections[$branch->id][$grade] as $section) {
                                    if ($section->name === $class->section) {
                                        $section->update(['class_teacher_id' => $teacher->user_id]);
                                        break;
                                    }
                                }
                            }
                            
                            // Update teacher
                            $teacher->update([
                                'is_class_teacher' => true,
                                'classes_assigned' => [$class->id]
                            ]);
                            
                            $teacherIndex++;
                        }
                    }
                }
            }
        }
        
        $this->command->info("  ✓ Assigned class teachers to all classes");
    }
    
    private function createStudents(): void
    {
        foreach ($this->branches as $branch) {
            $this->command->info("  Creating students for {$branch->name}...");
            
            $studentsPerGrade = intval(1000 / 12); // ~83 students per grade
            $studentCount = 0;
            
            for ($grade = 1; $grade <= 12; $grade++) {
                if (!isset($this->sections[$branch->id][$grade])) {
                    continue;
                }
                
                $sections = $this->sections[$branch->id][$grade];
                $studentsInGrade = 0;
                
                while ($studentsInGrade < $studentsPerGrade && $studentCount < 1000) {
                    // Distribute students evenly across sections
                    $section = $sections[$studentsInGrade % count($sections)];
                    
                    $gender = rand(0, 1) ? 'Male' : 'Female';
                    $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
                    $lastName = $this->lastNames[array_rand($this->lastNames)];
                    $admissionNumber = 'ADM-' . $branch->code . '-' . date('Y') . '-' . str_pad($studentCount + 1, 5, '0', STR_PAD_LEFT);
                    $email = strtolower($firstName . '.' . $lastName . '.' . ($studentCount + 1) . '@student.excellenceacademy.com');
                    
                    // Create User
                    $user = User::firstOrCreate(
                        ['email' => $email],
                        [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                            'password' => Hash::make('Student@123'),
                            'role' => 'Student',
                            'user_type' => 'Student',
                            'branch_id' => $branch->id,
                            'is_active' => true,
                            'email_verified_at' => now()
                        ]
                    );
                    
                    // Create Student Record
                    $fatherFirstName = $this->firstNames['Male'][array_rand($this->firstNames['Male'])];
                    $motherFirstName = $this->firstNames['Female'][array_rand($this->firstNames['Female'])];
                    
                    DB::table('students')->insertOrIgnore([
                        'user_id' => $user->id,
                        'branch_id' => $branch->id,
                        'admission_number' => $admissionNumber,
                        'admission_date' => Carbon::now()->subMonths(rand(1, 24))->format('Y-m-d'),
                        'roll_number' => str_pad($studentsInGrade + 1, 3, '0', STR_PAD_LEFT),
                        'grade' => (string)$grade,
                        'section' => $section->name,
                        'academic_year' => $this->currentAcademicYear,
                        'date_of_birth' => Carbon::now()->subYears($grade + 5)->subMonths(rand(0, 11))->format('Y-m-d'),
                        'gender' => $gender,
                        'blood_group' => $this->bloodGroups[array_rand($this->bloodGroups)],
                        'religion' => $this->religions[array_rand($this->religions)],
                        'category' => $this->categories[array_rand($this->categories)],
                        'nationality' => 'American',
                        'mother_tongue' => 'English',
                        'current_address' => rand(100, 9999) . ' ' . $this->streets[array_rand($this->streets)],
                        'permanent_address' => rand(100, 9999) . ' ' . $this->streets[array_rand($this->streets)],
                        'city' => $branch->city,
                        'state' => $branch->state,
                        'country' => $branch->country,
                        'pincode' => str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT),
                        'father_name' => $fatherFirstName . ' ' . $lastName,
                        'father_phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                        'father_email' => strtolower($fatherFirstName . '.' . $lastName . '@email.com'),
                        'father_occupation' => ['Engineer', 'Doctor', 'Teacher', 'Businessman', 'Government Employee'][array_rand(['Engineer', 'Doctor', 'Teacher', 'Businessman', 'Government Employee'])],
                        'father_annual_income' => rand(30000, 150000),
                        'mother_name' => $motherFirstName . ' ' . $lastName,
                        'mother_phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                        'mother_email' => strtolower($motherFirstName . '.' . $lastName . '@email.com'),
                        'mother_occupation' => ['Engineer', 'Doctor', 'Teacher', 'Homemaker', 'Businesswoman'][array_rand(['Engineer', 'Doctor', 'Teacher', 'Homemaker', 'Businesswoman'])],
                        'mother_annual_income' => rand(0, 120000),
                        'emergency_contact_name' => $fatherFirstName . ' ' . $lastName,
                        'emergency_contact_phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                        'student_status' => 'Active',
                        'admission_status' => 'Admitted',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $studentsInGrade++;
                    $studentCount++;
                }
                
                $this->command->info("    ✓ Grade {$grade}: {$studentsInGrade} students");
            }
            
            $this->command->info("  ✓ Total students created for {$branch->name}: {$studentCount}");
        }
    }
    
    private function updateStatistics(): void
    {
        foreach ($this->branches as $branch) {
            // Update section strengths
            if (isset($this->sections[$branch->id])) {
                foreach ($this->sections[$branch->id] as $grade => $sections) {
                    foreach ($sections as $section) {
                        $count = DB::table('students')
                            ->where('branch_id', $branch->id)
                            ->where('grade', (string)$grade)
                            ->where('section', $section->name)
                            ->where('student_status', 'Active')
                            ->count();
                        
                        $section->update(['current_strength' => $count]);
                    }
                }
            }
            
            // Update class strengths
            if (isset($this->classes[$branch->id])) {
                foreach ($this->classes[$branch->id] as $grade => $classes) {
                    foreach ($classes as $class) {
                        $count = DB::table('students')
                            ->where('branch_id', $branch->id)
                            ->where('grade', (string)$grade)
                            ->where('section', $class->section)
                            ->where('student_status', 'Active')
                            ->count();
                        
                        $class->update(['current_strength' => $count]);
                    }
                }
            }
            
            // Update branch enrollment
            $totalStudents = DB::table('students')
                ->where('branch_id', $branch->id)
                ->where('student_status', 'Active')
                ->count();
            
            $branch->update(['current_enrollment' => $totalStudents]);
        }
        
        $this->command->info("  ✓ Updated all statistics");
    }
    
    private function getRandomSubjects(): array
    {
        $allSubjects = array_merge(
            $this->subjects['1-5'],
            $this->subjects['6-8'],
            $this->subjects['9-10'],
            $this->subjects['11-12']
        );
        
        $uniqueSubjects = array_unique($allSubjects);
        shuffle($uniqueSubjects);
        
        return array_slice($uniqueSubjects, 0, rand(2, 4));
    }
    
    private function printSummary(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('📊 DEMO DATA SEEDING SUMMARY');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');
        
        $totalTeachers = 0;
        $totalStudents = 0;
        
        foreach ($this->branches as $branch) {
            $students = DB::table('students')->where('branch_id', $branch->id)->count();
            $teachers = Teacher::where('branch_id', $branch->id)->count();
            
            $this->command->info("🏢 {$branch->name}");
            $this->command->info("   📍 Location: {$branch->city}, {$branch->state}");
            $this->command->info("   👨‍🏫 Teachers: {$teachers}");
            $this->command->info("   👨‍🎓 Students: {$students}");
            $this->command->info('');
            
            $totalTeachers += $teachers;
            $totalStudents += $students;
        }
        
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info("📊 TOTALS:");
        $this->command->info("   🏢 Branches: " . count($this->branches));
        $this->command->info("   👨‍🏫 Teachers: {$totalTeachers}");
        $this->command->info("   👨‍🎓 Students: {$totalStudents}");
        $this->command->info("   📚 Grades: 12 (Grade 1 - Grade 12)");
        $this->command->info("   📋 Sections per Grade: 4 (A, B, C, D)");
        $this->command->info("   🏫 Total Classes: " . ClassModel::count());
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->info('🔑 LOGIN CREDENTIALS:');
        $this->command->info('   Teachers: [email]@excellenceacademy.com / Teacher@123');
        $this->command->info('   Students: [email]@student.excellenceacademy.com / Student@123');
        $this->command->info('');
        $this->command->info('✅ Application is ready for demo!');
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}

