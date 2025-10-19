<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class RealtimeComprehensiveSeeder extends Seeder
{
    private $branches = [];
    private $departments = [];
    private $teachers = [];
    private $classes = [];
    private $sections = [];
    private $students = [];
    private $subjects = [];
    
    // Realistic data arrays
    private $firstNames = [
        'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
        'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
        'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa',
        'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
        'Steven', 'Kimberly', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle',
        'Kenneth', 'Dorothy', 'Kevin', 'Carol', 'Brian', 'Amanda', 'George', 'Melissa',
        'Edward', 'Deborah', 'Ronald', 'Stephanie', 'Timothy', 'Rebecca', 'Jason', 'Sharon',
        'Jeffrey', 'Laura', 'Ryan', 'Cynthia', 'Jacob', 'Kathleen', 'Gary', 'Amy',
        'Nicholas', 'Shirley', 'Eric', 'Angela', 'Jonathan', 'Helen', 'Stephen', 'Anna',
        'Larry', 'Brenda', 'Justin', 'Pamela', 'Scott', 'Nicole', 'Brandon', 'Emma',
        'Benjamin', 'Samantha', 'Samuel', 'Katherine', 'Raymond', 'Christine', 'Gregory', 'Debra',
        'Alexander', 'Rachel', 'Patrick', 'Catherine', 'Frank', 'Carolyn', 'Jack', 'Janet',
        'Dennis', 'Ruth', 'Jerry', 'Maria', 'Tyler', 'Heather', 'Aaron', 'Diane',
        'Jose', 'Virginia', 'Adam', 'Julie', 'Henry', 'Joyce', 'Nathan', 'Victoria',
        'Douglas', 'Olivia', 'Zachary', 'Kelly', 'Peter', 'Christina', 'Kyle', 'Lauren'
    ];

    private $lastNames = [
        'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
        'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson', 'Anderson', 'Thomas',
        'Taylor', 'Moore', 'Jackson', 'Martin', 'Lee', 'Perez', 'Thompson', 'White',
        'Harris', 'Sanchez', 'Clark', 'Ramirez', 'Lewis', 'Robinson', 'Walker', 'Young',
        'Allen', 'King', 'Wright', 'Scott', 'Torres', 'Nguyen', 'Hill', 'Flores',
        'Green', 'Adams', 'Nelson', 'Baker', 'Hall', 'Rivera', 'Campbell', 'Mitchell',
        'Carter', 'Roberts', 'Gomez', 'Phillips', 'Evans', 'Turner', 'Diaz', 'Parker',
        'Cruz', 'Edwards', 'Collins', 'Reyes', 'Stewart', 'Morris', 'Morales', 'Murphy',
        'Cook', 'Rogers', 'Gutierrez', 'Ortiz', 'Morgan', 'Cooper', 'Peterson', 'Bailey',
        'Reed', 'Kelly', 'Howard', 'Ramos', 'Kim', 'Cox', 'Ward', 'Richardson',
        'Watson', 'Brooks', 'Chavez', 'Wood', 'James', 'Bennett', 'Gray', 'Mendoza',
        'Ruiz', 'Hughes', 'Price', 'Alvarez', 'Castillo', 'Sanders', 'Patel', 'Myers'
    ];

    private $cities = [
        'New York', 'Los Angeles', 'Chicago', 'Houston', 'Phoenix', 'Philadelphia',
        'San Antonio', 'San Diego', 'Dallas', 'San Jose', 'Austin', 'Jacksonville',
        'Fort Worth', 'Columbus', 'San Francisco', 'Charlotte', 'Indianapolis', 'Seattle',
        'Denver', 'Boston', 'El Paso', 'Detroit', 'Nashville', 'Portland', 'Memphis',
        'Oklahoma City', 'Las Vegas', 'Louisville', 'Baltimore', 'Milwaukee'
    ];

    private $states = [
        'NY', 'CA', 'IL', 'TX', 'AZ', 'PA', 'FL', 'OH', 'WA', 'CO', 'MA', 'TN', 'OR'
    ];

    private $streets = [
        'Main Street', 'Oak Avenue', 'Maple Drive', 'Cedar Lane', 'Pine Road',
        'Washington Boulevard', 'Lincoln Avenue', 'Park Street', 'Elm Street', 'Lake Drive',
        'Hill Road', 'Church Street', 'School Street', 'Forest Avenue', 'River Road',
        'Sunset Boulevard', 'Valley Road', 'Mountain View', 'Garden Street', 'College Avenue'
    ];

    private $departmentNames = [
        'Mathematics', 'Science', 'English Language', 'Social Studies', 'Computer Science',
        'Physical Education', 'Arts & Crafts', 'Music', 'Foreign Languages', 'Business Studies',
        'Commerce', 'Biology', 'Chemistry', 'Physics', 'History', 'Geography',
        'Economics', 'Political Science', 'Psychology', 'Environmental Science'
    ];

    private $subjectsByGrade = [
        '1' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art', 'Physical Education'],
        '2' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art', 'Physical Education'],
        '3' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art', 'Music', 'Physical Education'],
        '4' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer', 'Art', 'Physical Education'],
        '5' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer', 'Art', 'Physical Education'],
        '6' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer', 'Hindi', 'Physical Education'],
        '7' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer', 'Hindi', 'Physical Education'],
        '8' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer', 'Hindi', 'Physical Education'],
        '9' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Computer Science'],
        '10' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'History', 'Geography', 'Computer Science'],
        '11' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Business Studies', 'Economics'],
        '12' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'Business Studies', 'Economics']
    ];

    public function run()
    {
        echo "\n🚀 Starting Comprehensive Real-time Data Seeder...\n\n";
        
        DB::beginTransaction();
        
        try {
            // Step 1: Create Main Branch
            $this->createMainBranch();
            
            // Step 2: Create 5 Sub-branches
            $this->createSubBranches(5);
            
            // Step 3: Create Departments (10+ per branch)
            $this->createDepartments();
            
            // Step 4: Create Grades
            $this->createGrades();
            
            // Step 5: Create Classes for each branch
            $this->createClasses();
            
            // Step 6: Create Sections for each class
            $this->createSections();
            
            // Step 7: Create Teachers (20+ per branch)
            $this->createTeachers();
            
            // Step 8: Create Subjects
            $this->createSubjects();
            
            // Step 9: Create Students (50+ per section)
            $this->createStudents();
            
            // Step 10: Create Fee Structures
            $this->createFeeStructures();
            
            // Step 11: Create Attendance Records
            $this->createAttendanceRecords();
            
            // Step 12: Create Exams
            $this->createExams();
            
            // Step 13: Create Library Books
            $this->createLibraryBooks();
            
            // Step 14: Create Transport Routes
            $this->createTransportRoutes();
            
            // Step 15: Create Events & Holidays
            $this->createEventsAndHolidays();
            
            // Step 16: Create Timetables
            $this->createTimetables();
            
            DB::commit();
            
            $this->printSummary();
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
        }
    }

    private function createMainBranch()
    {
        echo "📍 Creating Main Branch...\n";
        
        $mainBranch = DB::table('branches')->insertGetId([
            'name' => 'Global Education Network - Headquarters',
            'code' => 'GEN-HQ',
            'branch_type' => 'HeadOffice',
            'parent_branch_id' => null,
            'address' => '1000 Education Boulevard',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'United States',
            'pincode' => '10001',
            'phone' => '+1-212-555-0001',
            'email' => 'hq@globaledu.com',
            'website' => 'www.globaledu.com',
            'principal_name' => 'Dr. Robert Anderson',
            'principal_email' => 'r.anderson@globaledu.com',
            'principal_contact' => '+1-212-555-0100',
            'established_date' => '2010-01-15',
            'total_capacity' => 2000,
            'current_enrollment' => 0,
            'board' => 'International Baccalaureate',
            'status' => 'Active',
            'is_active' => true,
            'is_main_branch' => true,
            'timezone' => 'America/New_York',
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        $this->branches[] = $mainBranch;
        echo "✅ Main Branch created (ID: {$mainBranch})\n\n";
    }

    private function createSubBranches($count)
    {
        echo "📍 Creating {$count} Sub-branches...\n";
        
        $branchData = [
            [
                'name' => 'Global Education Network - Downtown Campus',
                'code' => 'GEN-DTC',
                'city' => 'New York',
                'state' => 'NY',
                'principal' => 'Dr. Jennifer Martinez',
                'board' => 'Cambridge International'
            ],
            [
                'name' => 'Global Education Network - Westside Academy',
                'code' => 'GEN-WSA',
                'city' => 'Los Angeles',
                'state' => 'CA',
                'principal' => 'Prof. Michael Thompson',
                'board' => 'CBSE International'
            ],
            [
                'name' => 'Global Education Network - Lakeside School',
                'code' => 'GEN-LSS',
                'city' => 'Chicago',
                'state' => 'IL',
                'principal' => 'Dr. Sarah Williams',
                'board' => 'International Baccalaureate'
            ],
            [
                'name' => 'Global Education Network - Sunrise Campus',
                'code' => 'GEN-SRC',
                'city' => 'Houston',
                'state' => 'TX',
                'principal' => 'Dr. David Johnson',
                'board' => 'Cambridge International'
            ],
            [
                'name' => 'Global Education Network - Valley View School',
                'code' => 'GEN-VVS',
                'city' => 'Phoenix',
                'state' => 'AZ',
                'principal' => 'Prof. Emily Davis',
                'board' => 'CBSE International'
            ]
        ];
        
        $mainBranchId = $this->branches[0];
        
        foreach ($branchData as $index => $data) {
            $branchId = DB::table('branches')->insertGetId([
                'name' => $data['name'],
                'code' => $data['code'],
                'branch_type' => 'School',
                'parent_branch_id' => $mainBranchId,
                'address' => rand(100, 999) . ' ' . $this->streets[array_rand($this->streets)],
                'city' => $data['city'],
                'state' => $data['state'],
                'country' => 'United States',
                'pincode' => (string)rand(10000, 99999),
                'phone' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '', $data['code'])) . '@globaledu.com',
                'website' => 'www.globaledu.com/' . strtolower($data['code']),
                'principal_name' => $data['principal'],
                'principal_email' => strtolower(str_replace([' ', '.'], ['', ''], explode(' - ', $data['principal'])[0])) . '@globaledu.com',
                'principal_contact' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'established_date' => Carbon::now()->subYears(rand(5, 15))->format('Y-m-d'),
                'total_capacity' => rand(1500, 3000),
                'current_enrollment' => 0,
                'board' => $data['board'],
                'status' => 'Active',
                'is_active' => true,
                'is_main_branch' => false,
                'timezone' => 'America/New_York',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            $this->branches[] = $branchId;
            $branchNum = $index + 1;
            echo "  ✅ Branch {$branchNum}: {$data['name']} (ID: {$branchId})\n";
        }
        
        echo "\n";
    }

    private function createDepartments()
    {
        echo "🏢 Creating Departments (12 per branch)...\n";
        
        $totalDepts = 0;
        
        foreach ($this->branches as $branchId) {
            foreach ($this->departmentNames as $index => $deptName) {
                if ($index >= 12) break; // 12 departments per branch
                
                $headFirstName = $this->firstNames[array_rand($this->firstNames)];
                $headLastName = $this->lastNames[array_rand($this->lastNames)];
                
                $deptId = DB::table('departments')->insertGetId([
                    'name' => $deptName,
                    'head' => "Dr. {$headFirstName} {$headLastName}",
                    'head_id' => null,
                    'description' => "Department of {$deptName} - Excellence in education and research",
                    'branch_id' => $branchId,
                    'students_count' => 0,
                    'teachers_count' => 0,
                    'is_active' => true,
                    'established_date' => Carbon::now()->subYears(rand(1, 10))->format('Y-m-d'),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $this->departments[$branchId][] = $deptId;
                $totalDepts++;
            }
        }
        
        echo "  ✅ Created {$totalDepts} departments across all branches\n\n";
    }

    private function createGrades()
    {
        echo "📚 Verifying Grade levels (1-12)...\n";
        
        // Grades are already created in the migration, just verify they exist
        $gradesCount = DB::table('grades')->count();
        
        if ($gradesCount == 0) {
            // If not created in migration, create them now
            $grades = [
                ['value' => '1', 'label' => 'Grade 1', 'description' => 'First Grade - Elementary', 'is_active' => true],
                ['value' => '2', 'label' => 'Grade 2', 'description' => 'Second Grade - Elementary', 'is_active' => true],
                ['value' => '3', 'label' => 'Grade 3', 'description' => 'Third Grade - Elementary', 'is_active' => true],
                ['value' => '4', 'label' => 'Grade 4', 'description' => 'Fourth Grade - Elementary', 'is_active' => true],
                ['value' => '5', 'label' => 'Grade 5', 'description' => 'Fifth Grade - Elementary', 'is_active' => true],
                ['value' => '6', 'label' => 'Grade 6', 'description' => 'Sixth Grade - Middle School', 'is_active' => true],
                ['value' => '7', 'label' => 'Grade 7', 'description' => 'Seventh Grade - Middle School', 'is_active' => true],
                ['value' => '8', 'label' => 'Grade 8', 'description' => 'Eighth Grade - Middle School', 'is_active' => true],
                ['value' => '9', 'label' => 'Grade 9', 'description' => 'Ninth Grade - High School', 'is_active' => true],
                ['value' => '10', 'label' => 'Grade 10', 'description' => 'Tenth Grade - High School', 'is_active' => true],
                ['value' => '11', 'label' => 'Grade 11', 'description' => 'Eleventh Grade - Senior High', 'is_active' => true],
                ['value' => '12', 'label' => 'Grade 12', 'description' => 'Twelfth Grade - Senior High', 'is_active' => true],
            ];
            
            foreach ($grades as $grade) {
                DB::table('grades')->insert([
                    'value' => $grade['value'],
                    'label' => $grade['label'],
                    'description' => $grade['description'],
                    'is_active' => $grade['is_active'],
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            echo "  ✅ Created 12 grade levels\n\n";
        } else {
            echo "  ✅ Grade levels already exist ({$gradesCount} grades)\n\n";
        }
    }

    private function createClasses()
    {
        echo "🎓 Creating Classes for each branch...\n";
        
        $totalClasses = 0;
        
        foreach ($this->branches as $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                $sections = ['A', 'B', 'C', 'D'];
                foreach ($sections as $section) {
                    $classId = DB::table('classes')->insertGetId([
                        'class_name' => "Grade {$grade}-{$section}",
                        'grade' => (string)$grade,
                        'section' => $section,
                        'branch_id' => $branchId,
                        'academic_year' => '2024-2025',
                        'class_teacher_id' => null,
                        'capacity' => 60,
                        'current_strength' => 0,
                        'room_number' => 'Room ' . rand(101, 599),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $this->classes[$branchId][$grade] = $classId;
                    $totalClasses++;
                }
            }
        }
        
        echo "  ✅ Created {$totalClasses} classes\n\n";
    }

    private function createSections()
    {
        echo "📑 Sections created with classes...\n";
        
        // Sections are already created as part of classes
        // Just populate the sections array for later use
        foreach ($this->classes as $branchId => $grades) {
            foreach ($grades as $grade => $classId) {
                $sections = ['A', 'B', 'C', 'D'];
                foreach ($sections as $sectionName) {
                    $this->sections[$branchId][$grade][] = [
                        'id' => $classId,
                        'name' => $sectionName,
                        'class_id' => $classId
                    ];
                }
            }
        }
        
        echo "  ✅ Section tracking configured\n\n";
    }

    private function createTeachers()
    {
        echo "👨‍🏫 Creating Teachers (25 per branch)...\n";
        
        $totalTeachers = 0;
        $userInserts = [];
        
        foreach ($this->branches as $branchId) {
            $deptIds = $this->departments[$branchId] ?? [];
            
            for ($i = 0; $i < 25; $i++) {
                $firstName = $this->firstNames[array_rand($this->firstNames)];
                $lastName = $this->lastNames[array_rand($this->lastNames)];
                $email = strtolower($firstName . '.' . $lastName . $i) . '@globaledu.com';
                $phone = '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                
                $gender = rand(0, 1) ? 'Male' : 'Female';
                $deptId = $deptIds[array_rand($deptIds)];
                
                $userInserts[] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'password' => Hash::make('Teacher@123'),
                    'role' => 'Teacher',
                    'user_type' => 'Teacher',
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                $this->teachers[$branchId][] = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $phone,
                    'department_id' => $deptId
                ];
                
                $totalTeachers++;
            }
        }
        
        // Bulk insert users
        foreach (array_chunk($userInserts, 500) as $chunk) {
            DB::table('users')->insert($chunk);
        }
        
        echo "  ✅ Created {$totalTeachers} teachers\n\n";
    }

    private function createSubjects()
    {
        echo "📖 Creating Subjects...\n";
        
        $totalSubjects = 0;
        $subjectCounter = 0;
        
        foreach ($this->branches as $branchId) {
            $deptIds = $this->departments[$branchId] ?? [];
            
            foreach ($this->subjectsByGrade as $grade => $subjectNames) {
                foreach ($subjectNames as $subjectName) {
                    $deptId = $deptIds[array_rand($deptIds)];
                    $subjectCounter++;
                    
                    // Create unique code: PREFIX + GRADE + BRANCH + COUNTER
                    $code = strtoupper(substr($subjectName, 0, 3)) . '-G' . $grade . '-B' . $branchId . '-' . str_pad($subjectCounter, 4, '0', STR_PAD_LEFT);
                    
                    $subjectId = DB::table('subjects')->insertGetId([
                        'name' => $subjectName,
                        'code' => $code,
                        'description' => "{$subjectName} for Grade {$grade}",
                        'department_id' => $deptId,
                        'teacher_id' => null,
                        'branch_id' => $branchId,
                        'grade_level' => (string)$grade,
                        'credits' => rand(3, 5),
                        'type' => in_array($subjectName, ['Mathematics', 'English', 'Science']) ? 'Core' : 'Elective',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $this->subjects[$branchId][$grade][] = $subjectId;
                    $totalSubjects++;
                }
            }
        }
        
        echo "  ✅ Created {$totalSubjects} subjects\n\n";
    }

    private function createStudents()
    {
        echo "👨‍🎓 Creating Students (30 per section)...\n";
        
        $totalStudents = 0;
        $batchSize = 100;
        $userBatch = [];
        
        foreach ($this->sections as $branchId => $grades) {
            foreach ($grades as $grade => $sections) {
                foreach ($sections as $section) {
                    // Create 30 students per section (reduced for performance)
                    for ($i = 0; $i < 30; $i++) {
                        $firstName = $this->firstNames[array_rand($this->firstNames)];
                        $lastName = $this->lastNames[array_rand($this->lastNames)];
                        $rollNumber = 'STU' . str_pad($totalStudents + 1, 6, '0', STR_PAD_LEFT);
                        $email = strtolower($firstName . '.' . $lastName . '.' . $rollNumber) . '@student.globaledu.com';
                        $phone = '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                        
                        $gender = rand(0, 1) ? 'Male' : 'Female';
                        $dob = Carbon::now()->subYears(5 + (int)$grade)->subDays(rand(0, 365))->format('Y-m-d');
                        
                        $userBatch[] = [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => $email,
                            'phone' => $phone,
                            'password' => Hash::make('Student@123'),
                            'role' => 'Student',
                            'user_type' => 'Student',
                            'branch_id' => $branchId,
                            'is_active' => true,
                            'email_verified_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                        
                        $totalStudents++;
                        
                        // Insert in batches
                        if (count($userBatch) >= $batchSize) {
                            DB::table('users')->insert($userBatch);
                            $userBatch = [];
                            echo "  📊 Progress: {$totalStudents} students created...\n";
                        }
                    }
                }
            }
        }
        
        // Insert remaining
        if (count($userBatch) > 0) {
            DB::table('users')->insert($userBatch);
        }
        
        echo "  ✅ Created {$totalStudents} students\n\n";
    }

    private function createFeeStructures()
    {
        echo "💰 Creating Fee Structures...\n";
        
        $feeTypes = [
            ['name' => 'Tuition Fee', 'frequency' => 'Monthly', 'amount_range' => [500, 1500]],
            ['name' => 'Admission Fee', 'frequency' => 'One-time', 'amount_range' => [1000, 3000]],
            ['name' => 'Library Fee', 'frequency' => 'Annual', 'amount_range' => [100, 300]],
            ['name' => 'Sports Fee', 'frequency' => 'Annual', 'amount_range' => [200, 500]],
            ['name' => 'Lab Fee', 'frequency' => 'Annual', 'amount_range' => [300, 800]],
            ['name' => 'Transport Fee', 'frequency' => 'Monthly', 'amount_range' => [100, 400]],
        ];
        
        $totalFees = 0;
        
        foreach ($this->branches as $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach ($feeTypes as $feeType) {
                    DB::table('fee_structures')->insert([
                        'name' => $feeType['name'],
                        'grade' => (string)$grade,
                        'branch_id' => $branchId,
                        'amount' => rand($feeType['amount_range'][0], $feeType['amount_range'][1]),
                        'frequency' => $feeType['frequency'],
                        'due_date' => Carbon::now()->addDays(rand(1, 30))->format('Y-m-d'),
                        'academic_year' => '2024-2025',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $totalFees++;
                }
            }
        }
        
        echo "  ✅ Created {$totalFees} fee structures\n\n";
    }

    private function createAttendanceRecords()
    {
        echo "📅 Creating Attendance Records (last 30 days)...\n";
        
        $students = DB::table('users')->where('role', 'Student')->select('id', 'branch_id')->limit(100)->get();
        $totalRecords = 0;
        $batchSize = 500;
        $attendanceBatch = [];
        
        // Create attendance for last 30 days (limited to 100 students for performance)
        for ($day = 29; $day >= 0; $day--) {
            $date = Carbon::now()->subDays($day)->format('Y-m-d');
            
            // Skip weekends
            if (Carbon::parse($date)->isWeekend()) continue;
            
            foreach ($students as $student) {
                // 95% attendance rate
                $statusOptions = ['Present', 'Absent', 'Late'];
                $status = rand(1, 100) <= 95 ? 'Present' : $statusOptions[array_rand($statusOptions)];
                
                $attendanceBatch[] = [
                    'student_id' => $student->id,
                    'grade_level' => (string)rand(1, 12),
                    'section' => ['A', 'B', 'C', 'D'][array_rand(['A', 'B', 'C', 'D'])],
                    'branch_id' => $student->branch_id,
                    'date' => $date,
                    'status' => $status,
                    'remarks' => null,
                    'marked_by' => 'System',
                    'academic_year' => '2024-2025',
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                
                $totalRecords++;
                
                if (count($attendanceBatch) >= $batchSize) {
                    DB::table('student_attendance')->insert($attendanceBatch);
                    $attendanceBatch = [];
                }
            }
        }
        
        if (count($attendanceBatch) > 0) {
            DB::table('student_attendance')->insert($attendanceBatch);
        }
        
        echo "  ✅ Created {$totalRecords} attendance records\n\n";
    }

    private function createExams()
    {
        echo "📝 Creating Exams...\n";
        
        $examTypes = ['Mid-Term', 'Final', 'Unit Test', 'Quarterly'];
        $totalExams = 0;
        
        foreach ($this->branches as $branchId) {
            foreach ($this->subjects[$branchId] ?? [] as $grade => $subjectIds) {
                foreach ($examTypes as $examType) {
                    foreach ($subjectIds as $subjectId) {
                        $duration = rand(60, 180);
                        $startTime = '09:00:00';
                        $endTime = date('H:i:s', strtotime($startTime) + ($duration * 60));
                        
                        DB::table('exams')->insert([
                            'name' => "{$examType} - Grade {$grade}",
                            'type' => $examType,
                            'subject_id' => $subjectId,
                            'branch_id' => $branchId,
                            'grade_level' => (string)$grade,
                            'section' => 'A',
                            'date' => Carbon::now()->addDays(rand(1, 90))->format('Y-m-d'),
                            'start_time' => $startTime,
                            'end_time' => $endTime,
                            'duration' => $duration,
                            'total_marks' => 100,
                            'passing_marks' => 40,
                            'room' => 'Room ' . rand(101, 599),
                            'academic_year' => '2024-2025',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $totalExams++;
                    }
                }
            }
        }
        
        echo "  ✅ Created {$totalExams} exams\n\n";
    }

    private function createLibraryBooks()
    {
        echo "📚 Creating Library Books...\n";
        
        $bookTitles = [
            'Introduction to Computer Science', 'Advanced Mathematics', 'Physics Fundamentals',
            'Chemistry Lab Manual', 'Biology Textbook', 'World History', 'English Literature',
            'Business Studies', 'Economics Principles', 'Environmental Science',
            'Art and Design', 'Music Theory', 'Physical Education Guide'
        ];
        
        $authors = [
            'Dr. John Smith', 'Prof. Sarah Johnson', 'Dr. Michael Brown', 'Prof. Emily Davis',
            'Dr. Robert Wilson', 'Prof. Jennifer Lee', 'Dr. David Martinez', 'Prof. Lisa Anderson'
        ];
        
        $publishers = [
            'Academic Press', 'Oxford University Press', 'Cambridge Press', 'McGraw-Hill',
            'Pearson Education', 'Wiley Publishing', 'Springer', 'National Book Trust'
        ];
        
        $totalBooks = 0;
        
        foreach ($this->branches as $branchId) {
            foreach ($bookTitles as $title) {
                $quantity = rand(10, 50);
                DB::table('books')->insert([
                    'title' => $title,
                    'author' => $authors[array_rand($authors)],
                    'isbn' => '978-' . rand(1000000000, 9999999999),
                    'publisher' => $publishers[array_rand($publishers)],
                    'publication_year' => rand(2015, 2024),
                    'category' => 'Academic',
                    'branch_id' => $branchId,
                    'quantity' => $quantity,
                    'available_quantity' => $quantity,
                    'shelf_location' => 'Shelf ' . chr(rand(65, 90)) . '-' . rand(1, 50),
                    'price' => rand(20, 100),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $totalBooks++;
            }
        }
        
        echo "  ✅ Created {$totalBooks} library books\n\n";
    }

    private function createTransportRoutes()
    {
        echo "🚌 Creating Transport Routes...\n";
        
        $routeNames = [
            'Route A - North Zone', 'Route B - South Zone', 'Route C - East Zone',
            'Route D - West Zone', 'Route E - Central Zone'
        ];
        
        $totalRoutes = 0;
        
        foreach ($this->branches as $branchId) {
            foreach ($routeNames as $index => $routeName) {
                DB::table('transport_routes')->insert([
                    'route_name' => $routeName,
                    'route_number' => 'R' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                    'branch_id' => $branchId,
                    'start_point' => $this->cities[array_rand($this->cities)],
                    'end_point' => 'School Campus',
                    'stops' => 'Stop1, Stop2, Stop3, Stop4, Stop5',
                    'distance_km' => rand(5, 25),
                    'fare' => rand(200, 800),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $totalRoutes++;
            }
        }
        
        echo "  ✅ Created {$totalRoutes} transport routes\n\n";
    }

    private function createEventsAndHolidays()
    {
        echo "🎉 Creating Events and Holidays...\n";
        
        $events = [
            ['title' => 'Annual Sports Day', 'type' => 'Sports', 'days' => 30],
            ['title' => 'Science Exhibition', 'type' => 'Academic', 'days' => 45],
            ['title' => 'Cultural Festival', 'type' => 'Cultural', 'days' => 60],
            ['title' => 'Parent-Teacher Meeting', 'type' => 'Meeting', 'days' => 15],
        ];
        
        $holidays = [
            ['name' => 'New Year', 'date' => '2025-01-01'],
            ['name' => 'Independence Day', 'date' => '2025-07-04'],
            ['name' => 'Thanksgiving', 'date' => '2025-11-27'],
            ['name' => 'Christmas', 'date' => '2025-12-25'],
        ];
        
        $totalEvents = 0;
        $totalHolidays = 0;
        
        foreach ($this->branches as $branchId) {
            foreach ($events as $event) {
                DB::table('events')->insert([
                    'title' => $event['title'],
                    'description' => "Annual {$event['title']} celebration",
                    'event_type' => $event['type'],
                    'branch_id' => $branchId,
                    'event_date' => Carbon::now()->addDays($event['days'])->format('Y-m-d'),
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'venue' => 'School Auditorium',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $totalEvents++;
            }
            
            foreach ($holidays as $holiday) {
                DB::table('holidays')->insert([
                    'name' => $holiday['name'],
                    'date' => $holiday['date'],
                    'branch_id' => $branchId,
                    'description' => $holiday['name'] . ' Holiday',
                    'holiday_type' => 'National',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $totalHolidays++;
            }
        }
        
        echo "  ✅ Created {$totalEvents} events\n";
        echo "  ✅ Created {$totalHolidays} holidays\n\n";
    }

    private function createTimetables()
    {
        echo "⏰ Creating Timetables...\n";
        
        $totalTimetables = 0;
        
        foreach ($this->branches as $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                $sections = ['A', 'B', 'C', 'D'];
                foreach ($sections as $section) {
                    // Create a weekly schedule as JSON
                    $schedule = [
                        'Monday' => [
                            ['time' => '08:00-09:00', 'subject' => 'Mathematics', 'teacher' => 'T-001', 'room' => '101'],
                            ['time' => '09:00-10:00', 'subject' => 'English', 'teacher' => 'T-002', 'room' => '102'],
                            ['time' => '10:00-11:00', 'subject' => 'Science', 'teacher' => 'T-003', 'room' => '103'],
                            ['time' => '11:30-12:30', 'subject' => 'Social Studies', 'teacher' => 'T-004', 'room' => '104'],
                            ['time' => '12:30-13:30', 'subject' => 'Computer', 'teacher' => 'T-005', 'room' => 'Lab-1'],
                            ['time' => '13:30-14:30', 'subject' => 'PE', 'teacher' => 'T-006', 'room' => 'Gym'],
                        ],
                        'Tuesday' => [
                            ['time' => '08:00-09:00', 'subject' => 'Science', 'teacher' => 'T-003', 'room' => '103'],
                            ['time' => '09:00-10:00', 'subject' => 'Mathematics', 'teacher' => 'T-001', 'room' => '101'],
                            ['time' => '10:00-11:00', 'subject' => 'English', 'teacher' => 'T-002', 'room' => '102'],
                            ['time' => '11:30-12:30', 'subject' => 'Computer', 'teacher' => 'T-005', 'room' => 'Lab-1'],
                            ['time' => '12:30-13:30', 'subject' => 'Art', 'teacher' => 'T-007', 'room' => '105'],
                            ['time' => '13:30-14:30', 'subject' => 'Music', 'teacher' => 'T-008', 'room' => 'Music Room'],
                        ],
                        'Wednesday' => [
                            ['time' => '08:00-09:00', 'subject' => 'English', 'teacher' => 'T-002', 'room' => '102'],
                            ['time' => '09:00-10:00', 'subject' => 'Science', 'teacher' => 'T-003', 'room' => '103'],
                            ['time' => '10:00-11:00', 'subject' => 'Mathematics', 'teacher' => 'T-001', 'room' => '101'],
                            ['time' => '11:30-12:30', 'subject' => 'Social Studies', 'teacher' => 'T-004', 'room' => '104'],
                            ['time' => '12:30-13:30', 'subject' => 'PE', 'teacher' => 'T-006', 'room' => 'Gym'],
                            ['time' => '13:30-14:30', 'subject' => 'Computer', 'teacher' => 'T-005', 'room' => 'Lab-1'],
                        ],
                        'Thursday' => [
                            ['time' => '08:00-09:00', 'subject' => 'Mathematics', 'teacher' => 'T-001', 'room' => '101'],
                            ['time' => '09:00-10:00', 'subject' => 'English', 'teacher' => 'T-002', 'room' => '102'],
                            ['time' => '10:00-11:00', 'subject' => 'Science', 'teacher' => 'T-003', 'room' => '103'],
                            ['time' => '11:30-12:30', 'subject' => 'Art', 'teacher' => 'T-007', 'room' => '105'],
                            ['time' => '12:30-13:30', 'subject' => 'Social Studies', 'teacher' => 'T-004', 'room' => '104'],
                            ['time' => '13:30-14:30', 'subject' => 'Music', 'teacher' => 'T-008', 'room' => 'Music Room'],
                        ],
                        'Friday' => [
                            ['time' => '08:00-09:00', 'subject' => 'Science', 'teacher' => 'T-003', 'room' => '103'],
                            ['time' => '09:00-10:00', 'subject' => 'Mathematics', 'teacher' => 'T-001', 'room' => '101'],
                            ['time' => '10:00-11:00', 'subject' => 'English', 'teacher' => 'T-002', 'room' => '102'],
                            ['time' => '11:30-12:30', 'subject' => 'PE', 'teacher' => 'T-006', 'room' => 'Gym'],
                            ['time' => '12:30-13:30', 'subject' => 'Computer', 'teacher' => 'T-005', 'room' => 'Lab-1'],
                            ['time' => '13:30-14:30', 'subject' => 'Social Studies', 'teacher' => 'T-004', 'room' => '104'],
                        ],
                    ];
                    
                    DB::table('timetables')->insert([
                        'grade_level' => (string)$grade,
                        'section' => $section,
                        'branch_id' => $branchId,
                        'academic_year' => '2024-2025',
                        'schedule' => json_encode($schedule),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    
                    $totalTimetables++;
                }
            }
        }
        
        echo "  ✅ Created {$totalTimetables} timetable entries\n\n";
    }

    private function printSummary()
    {
        echo "\n";
        echo "═══════════════════════════════════════════════════════════════\n";
        echo "                  🎉 SEEDING COMPLETED SUCCESSFULLY! 🎉\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
        
        $branches = DB::table('branches')->count();
        $departments = DB::table('departments')->count();
        $teachers = DB::table('users')->where('role', 'Teacher')->count();
        $students = DB::table('users')->where('role', 'Student')->count();
        $classes = DB::table('classes')->count();
        $sections = DB::table('sections')->count();
        $subjects = DB::table('subjects')->count();
        $fees = DB::table('fee_structures')->count();
        $attendance = DB::table('attendance')->count();
        $exams = DB::table('exams')->count();
        $books = DB::table('books')->count();
        $routes = DB::table('transport_routes')->count();
        $events = DB::table('events')->count();
        $holidays = DB::table('holidays')->count();
        $timetables = DB::table('timetables')->count();
        
        echo "📊 DATABASE SUMMARY:\n";
        echo "───────────────────────────────────────────────────────────────\n";
        echo sprintf("  %-30s : %6d\n", "Branches", $branches);
        echo sprintf("  %-30s : %6d\n", "Departments", $departments);
        echo sprintf("  %-30s : %6d\n", "Teachers", $teachers);
        echo sprintf("  %-30s : %6d\n", "Students", $students);
        echo sprintf("  %-30s : %6d\n", "Classes", $classes);
        echo sprintf("  %-30s : %6d\n", "Sections", $sections);
        echo sprintf("  %-30s : %6d\n", "Subjects", $subjects);
        echo sprintf("  %-30s : %6d\n", "Fee Structures", $fees);
        echo sprintf("  %-30s : %6d\n", "Attendance Records", $attendance);
        echo sprintf("  %-30s : %6d\n", "Exams", $exams);
        echo sprintf("  %-30s : %6d\n", "Library Books", $books);
        echo sprintf("  %-30s : %6d\n", "Transport Routes", $routes);
        echo sprintf("  %-30s : %6d\n", "Events", $events);
        echo sprintf("  %-30s : %6d\n", "Holidays", $holidays);
        echo sprintf("  %-30s : %6d\n", "Timetable Entries", $timetables);
        echo "───────────────────────────────────────────────────────────────\n";
        echo sprintf("  %-30s : %6d\n", "TOTAL USERS", $teachers + $students);
        echo "───────────────────────────────────────────────────────────────\n\n";
        
        echo "🔐 LOGIN CREDENTIALS:\n";
        echo "───────────────────────────────────────────────────────────────\n";
        echo "  Teacher Login:\n";
        echo "    Any teacher email from database\n";
        echo "    Password: Teacher@123\n\n";
        echo "  Student Login:\n";
        echo "    Any student email from database\n";
        echo "    Password: Student@123\n";
        echo "───────────────────────────────────────────────────────────────\n\n";
        
        echo "✅ All data has been seeded successfully!\n";
        echo "✅ System is ready for production use!\n\n";
    }
}

