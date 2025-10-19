<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CompleteRealisticSeeder extends Seeder
{
    public function run()
    {
        echo "\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "  🚀 COMPLETE REALISTIC DATA SEEDER - ALL TABLES\n";
        echo "  Creates data in users + students + teachers + all sub-tables\n";
        echo "════════════════════════════════════════════════════════════════\n\n";
        
        $startTime = microtime(true);
        
        try {
            // STEP 1: Branches (1 Main + 5 Sub)
            echo "📍 STEP 1/12: Creating Branches...\n";
            $branches = $this->createBranches();
            echo "  ✅ Created 6 branches\n\n";
            
            // STEP 2: Departments
            echo "🏢 STEP 2/12: Creating Departments...\n";
            $departments = $this->createDepartments($branches);
            echo "  ✅ Created " . count($departments) . " departments\n\n";
            
            // STEP 3: Admin User
            echo "👨‍💼 STEP 3/12: Creating Admin...\n";
            $this->createAdmin($branches[0]);
            echo "  ✅ Admin created\n\n";
            
            // STEP 4: Teachers (with teacher table entries)
            echo "👨‍🏫 STEP 4/12: Creating Teachers (20 per branch)...\n";
            $teachers = $this->createTeachers($branches, $departments);
            echo "  ✅ Created " . count($teachers) . " teachers\n\n";
            
            // STEP 5: Classes
            echo "🎓 STEP 5/12: Creating Classes...\n";
            $classes = $this->createClasses($branches);
            echo "  ✅ Created " . count($classes) . " classes\n\n";
            
            // STEP 5.5: Sections
            echo "📑 STEP 5.5/12: Creating Sections...\n";
            $sections = $this->createSections($branches);
            echo "  ✅ Created " . count($sections) . " sections\n\n";
            
            // STEP 6: Students (with student table entries)
            echo "👨‍🎓 STEP 6/12: Creating Students (50 per section)...\n";
            $students = $this->createStudents($branches, $classes);
            echo "  ✅ Created " . count($students) . " students\n\n";
            
            // STEP 7: Parents (for students)
            echo "👨‍👩‍👧 STEP 7/12: Creating Parents...\n";
            $parents = $this->createParents($branches, $students);
            echo "  ✅ Created " . count($parents) . " parents\n\n";
            
            // STEP 8: Subjects
            echo "📖 STEP 8/12: Creating Subjects...\n";
            $subjects = $this->createSubjects($branches, $departments, $teachers);
            echo "  ✅ Created " . count($subjects) . " subjects\n\n";
            
            // STEP 9: Fee Structures
            echo "💰 STEP 9/12: Creating Fee Structures...\n";
            $fees = $this->createFeeStructures($branches);
            echo "  ✅ Created " . count($fees) . " fee structures\n\n";
            
            // STEP 10: Library Books
            echo "📚 STEP 10/12: Creating Library Books...\n";
            $books = $this->createLibraryBooks($branches);
            echo "  ✅ Created " . count($books) . " books\n\n";
            
            // STEP 11: Transport Routes
            echo "🚌 STEP 11/12: Creating Transport Routes...\n";
            $routes = $this->createTransportRoutes($branches);
            echo "  ✅ Created " . count($routes) . " routes\n\n";
            
            // STEP 12: Events & Holidays
            echo "🎉 STEP 12/12: Creating Events & Holidays...\n";
            $eventsHolidays = $this->createEventsAndHolidays($branches);
            echo "  ✅ Created events & holidays\n\n";
            
            $this->printFinalSummary($startTime);
            
        } catch (\Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "📄 File: " . $e->getFile() . "\n";
            echo "📍 Line: " . $e->getLine() . "\n\n";
            throw $e;
        }
    }
    
    private function createBranches()
    {
        $branches = [];
        
        // Main Branch
        $branches[] = DB::table('branches')->insertGetId([
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
            'principal_name' => 'Dr. Robert Anderson',
            'principal_contact' => '+1-212-555-0100',
            'established_date' => '2010-01-15',
            'total_capacity' => 3000,
            'board' => 'International Baccalaureate',
            'status' => 'Active',
            'is_active' => true,
            'is_main_branch' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // 5 Sub-branches
        $subBranches = [
            ['name' => 'Downtown Campus', 'code' => 'DTC', 'city' => 'New York', 'state' => 'NY'],
            ['name' => 'Westside Academy', 'code' => 'WSA', 'city' => 'Los Angeles', 'state' => 'CA'],
            ['name' => 'Lakeside School', 'code' => 'LSS', 'city' => 'Chicago', 'state' => 'IL'],
            ['name' => 'Sunrise Campus', 'code' => 'SRC', 'city' => 'Houston', 'state' => 'TX'],
            ['name' => 'Valley View', 'code' => 'VVS', 'city' => 'Phoenix', 'state' => 'AZ']
        ];
        
        foreach ($subBranches as $data) {
            $branches[] = DB::table('branches')->insertGetId([
                'name' => 'Global Education Network - ' . $data['name'],
                'code' => 'GEN-' . $data['code'],
                'branch_type' => 'School',
                'parent_branch_id' => $branches[0],
                'address' => rand(100, 999) . ' Education Street',
                'city' => $data['city'],
                'state' => $data['state'],
                'country' => 'United States',
                'pincode' => (string)rand(10000, 99999),
                'phone' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'email' => strtolower($data['code']) . '@globaledu.com',
                'principal_name' => 'Dr. Principal ' . $data['code'],
                'principal_contact' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                'established_date' => now()->subYears(rand(5, 10))->format('Y-m-d'),
                'total_capacity' => 2000,
                'board' => 'Cambridge International',
                'status' => 'Active',
                'is_active' => true,
                'is_main_branch' => false,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }
        
        return $branches;
    }
    
    private function createDepartments($branches)
    {
        $deptNames = [
            'Mathematics', 'Science', 'English Language', 'Social Studies', 'Computer Science',
            'Physical Education', 'Arts & Music', 'Foreign Languages', 'Commerce', 'Biology'
        ];
        
        $departments = [];
        foreach ($branches as $branchId) {
            foreach ($deptNames as $deptName) {
                $departments[] = DB::table('departments')->insertGetId([
                    'name' => $deptName,
                    'head' => "Dr. {$deptName} Head",
                    'branch_id' => $branchId,
                    'students_count' => 0,
                    'teachers_count' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        return $departments;
    }
    
    private function createAdmin($branchId)
    {
        DB::table('users')->insert([
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@myschool.com',
            'phone' => '+1-800-555-0001',
            'password' => Hash::make('Admin@123'),
            'role' => 'SuperAdmin',
            'branch_id' => $branchId,
            'is_active' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    private function createTeachers($branches, $departments)
    {
        $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
                       'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
                       'Thomas', 'Sarah', 'Charles', 'Karen'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
        
        $teachers = [];
        $teacherCount = 0;
        
        foreach ($branches as $branchId) {
            for ($i = 0; $i < 20; $i++) {
                $teacherCount++;
                $firstName = $firstNames[$i];
                $lastName = $lastNames[array_rand($lastNames)];
                
                // 1. Create user first
                $userId = DB::table('users')->insertGetId([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName . '.' . $lastName . '.t' . $teacherCount) . '@globaledu.com',
                    'phone' => '+1-' . str_pad(1000000 + $teacherCount, 10, '0', STR_PAD_LEFT),
                    'password' => Hash::make('Teacher@123'),
                    'role' => 'Teacher',
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                // 2. Create teacher record (matching actual schema)
                $teacherId = DB::table('teachers')->insertGetId([
                    'user_id' => $userId,
                    'branch_id' => $branchId,
                    'employee_id' => 'EMP' . str_pad($teacherCount, 6, '0', STR_PAD_LEFT),
                    'joining_date' => now()->subYears(rand(1, 10))->format('Y-m-d'),
                    'designation' => ['Assistant Professor', 'Professor', 'Senior Teacher', 'Teacher'][array_rand(['Assistant Professor', 'Professor', 'Senior Teacher', 'Teacher'])],
                    'employee_type' => 'Permanent',
                    'subjects' => json_encode([]),
                    'classes_assigned' => json_encode([]),
                    'is_class_teacher' => false,
                    'date_of_birth' => now()->subYears(rand(25, 55))->format('Y-m-d'),
                    'gender' => ['Male', 'Female'][array_rand(['Male', 'Female'])],
                    'address' => rand(100, 999) . ' Teacher Street, New York, NY ' . rand(10000, 99999),
                    'basic_salary' => rand(40000, 80000),
                    'bank_account_number' => 'BANK' . str_pad($teacherCount, 10, '0', STR_PAD_LEFT),
                    'teacher_status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                $teachers[] = ['user_id' => $userId, 'teacher_id' => $teacherId];
            }
        }
        
        return $teachers;
    }
    
    private function createClasses($branches)
    {
        $classes = [];
        foreach ($branches as $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach (['A', 'B', 'C', 'D'] as $section) {
                    $classes[] = DB::table('classes')->insertGetId([
                        'class_name' => "Grade {$grade}-{$section}",
                        'grade' => (string)$grade,
                        'section' => $section,
                        'branch_id' => $branchId,
                        'academic_year' => '2024-2025',
                        'capacity' => 50,
                        'current_strength' => 0,
                        'room_number' => 'Room ' . rand(101, 599),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
        return $classes;
    }
    
    private function createSections($branches)
    {
        $sections = [];
        $sectionCounter = 0;
        
        foreach ($branches as $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach (['A', 'B', 'C', 'D'] as $sectionName) {
                    $sectionCounter++;
                    $sections[] = DB::table('sections')->insertGetId([
                        'name' => $sectionName,
                        'code' => 'SEC-G' . $grade . '-' . $sectionName . '-B' . $branchId,
                        'grade_level' => (string)$grade,
                        'branch_id' => $branchId,
                        'capacity' => 50,
                        'current_strength' => 0,
                        'room_number' => 'Room ' . (100 + $sectionCounter),
                        'class_teacher_id' => null,
                        'description' => "Section {$sectionName} for Grade {$grade}",
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
        }
        
        return $sections;
    }
    
    private function createStudents($branches, $classes)
    {
        echo "  Creating students in both users and students tables...\n";
        
        $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
                       'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
                       'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa'];
        $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
                      'Rodriguez', 'Martinez', 'Hernandez', 'Lopez'];
        
        $students = [];
        $studentCount = 0;
        $batchSize = 50;
        $userBatch = [];
        $studentBatch = [];
        
        foreach ($branches as $branchIdx => $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach (['A', 'B', 'C', 'D'] as $section) {
                    for ($i = 0; $i < 50; $i++) {
                        $studentCount++;
                        $firstName = $firstNames[array_rand($firstNames)];
                        $lastName = $lastNames[array_rand($lastNames)];
                        
                        // Generate unique identifiers
                        $phoneNum = 2000000 + $studentCount;
                        $phoneFormatted = substr($phoneNum, 0, 3) . '-555-' . substr($phoneNum, 3);
                        $admissionNumber = 'ADM' . date('Y') . str_pad($studentCount, 5, '0', STR_PAD_LEFT);
                        $rollNumber = 'ROLL' . $grade . $section . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                        
                        // 1. Prepare user record
                        $userBatch[] = [
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => strtolower($firstName . '.' . $lastName . '.stu' . str_pad($studentCount, 5, '0', STR_PAD_LEFT)) . '@student.globaledu.com',
                            'phone' => '+1-' . $phoneFormatted,
                            'password' => Hash::make('Student@123'),
                            'role' => 'Student',
                            'branch_id' => $branchId,
                            'is_active' => true,
                            'email_verified_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                        
                        // Insert users in batches and get IDs
                        if (count($userBatch) >= $batchSize) {
                            DB::table('users')->insert($userBatch);
                            
                            // Get the user IDs we just inserted
                            $startUserId = DB::table('users')->orderBy('id', 'desc')->skip(0)->take($batchSize)->pluck('id')->toArray();
                            
                            // 2. Create corresponding student records
                            foreach ($startUserId as $idx => $userId) {
                                $adjustedCount = $studentCount - $batchSize + $idx + 1;
                                $currentGrade = 1 + floor(($adjustedCount - 1) / (50 * 4)) % 12;
                                $sectionIdx = floor((($adjustedCount - 1) / 50) % 4);
                                $sectionName = ['A', 'B', 'C', 'D'][$sectionIdx];
                                $studentNum = (($adjustedCount - 1) % 50) + 1;
                                
                                DB::table('students')->insert([
                                    'user_id' => $userId,
                                    'branch_id' => $branchId,
                                    'admission_number' => 'ADM' . date('Y') . str_pad($adjustedCount, 5, '0', STR_PAD_LEFT),
                                    'admission_date' => now()->subYears($currentGrade)->format('Y-m-d'),
                                    'roll_number' => 'ROLL' . $currentGrade . $sectionName . str_pad($studentNum, 3, '0', STR_PAD_LEFT),
                                    'grade' => (string)$currentGrade,
                                    'section' => $sectionName,
                                    'academic_year' => '2024-2025',
                                    'date_of_birth' => now()->subYears(5 + $currentGrade)->format('Y-m-d'),
                                    'gender' => ['Male', 'Female'][array_rand(['Male', 'Female'])],
                                    'blood_group' => ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'][array_rand(['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'])],
                                    'current_address' => rand(100, 999) . ' Student Street, New York, NY ' . rand(10000, 99999),
                                    'city' => ['New York', 'Los Angeles', 'Chicago'][array_rand(['New York', 'Los Angeles', 'Chicago'])],
                                    'state' => 'NY',
                                    'pincode' => (string)rand(10000, 99999),
                                    'parent_id' => null,
                                    'father_name' => 'Mr. Father of ' . $userBatch[$idx]['first_name'],
                                    'father_phone' => '+1-' . str_pad(5000000 + $adjustedCount, 10, '0', STR_PAD_LEFT),
                                    'mother_name' => 'Mrs. Mother of ' . $userBatch[$idx]['first_name'],
                                    'mother_phone' => '+1-' . str_pad(6000000 + $adjustedCount, 10, '0', STR_PAD_LEFT),
                                    'emergency_contact_name' => 'Emergency Contact',
                                    'emergency_contact_phone' => '+1-' . str_pad(7000000 + $adjustedCount, 10, '0', STR_PAD_LEFT),
                                    'student_status' => 'Active',
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);
                            }
                            
                            $userBatch = [];
                            
                            if ($studentCount % 500 == 0) {
                                echo "    ✓ {$studentCount} students (users + students table)...\n";
                            }
                        }
                        
                        $students[] = $studentCount;
                    }
                }
            }
        }
        
        // Insert remaining
        if (count($userBatch) > 0) {
            DB::table('users')->insert($userBatch);
            
            $startUserId = DB::table('users')->orderBy('id', 'desc')->skip(0)->take(count($userBatch))->pluck('id')->toArray();
            
            foreach ($startUserId as $idx => $userId) {
                $adjustedCount = $studentCount - count($userBatch) + $idx + 1;
                DB::table('students')->insert([
                    'user_id' => $userId,
                    'branch_id' => $branches[count($branches) - 1],
                    'admission_number' => 'ADM' . date('Y') . str_pad($adjustedCount, 5, '0', STR_PAD_LEFT),
                    'admission_date' => now()->subYears(1)->format('Y-m-d'),
                    'roll_number' => 'ROLL' . str_pad($adjustedCount, 6, '0', STR_PAD_LEFT),
                    'grade' => '1',
                    'section' => 'A',
                    'academic_year' => '2024-2025',
                    'date_of_birth' => now()->subYears(6)->format('Y-m-d'),
                    'gender' => ['Male', 'Female'][array_rand(['Male', 'Female'])],
                    'blood_group' => 'O+',
                    'current_address' => rand(100, 999) . ' Student Street, New York, NY ' . rand(10000, 99999),
                    'city' => 'New York',
                    'state' => 'NY',
                    'pincode' => (string)rand(10000, 99999),
                    'parent_id' => null,
                    'father_name' => 'Father Name',
                    'father_phone' => '+1-' . str_pad(5000000 + $adjustedCount, 10, '0', STR_PAD_LEFT),
                    'mother_name' => 'Mother Name',
                    'mother_phone' => '+1-' . str_pad(6000000 + $adjustedCount, 10, '0', STR_PAD_LEFT),
                    'emergency_contact_name' => 'Emergency Contact',
                    'emergency_contact_phone' => '+1-' . str_pad(7000000 + $adjustedCount, 10, '0', STR_PAD_LEFT),
                    'student_status' => 'Active',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        return $students;
    }
    
    private function createParents($branches, $students)
    {
        // Create 100 parent users
        $parents = [];
        for ($i = 1; $i <= 100; $i++) {
            DB::table('users')->insert([
                'first_name' => 'Parent',
                'last_name' => 'User' . $i,
                'email' => 'parent' . $i . '@globaledu.com',
                'phone' => '+1-' . str_pad(3000000 + $i, 10, '0', STR_PAD_LEFT),
                'password' => Hash::make('Parent@123'),
                'role' => 'Parent',
                'branch_id' => $branches[array_rand($branches)],
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            $parents[] = $i;
        }
        
        return $parents;
    }
    
    private function createSubjects($branches, $departments, $teachers)
    {
        $subjectsByGrade = [
            '1-5' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art'],
            '6-8' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer'],
            '9-12' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science']
        ];
        
        $subjects = [];
        foreach ($branches as $branchId) {
            foreach ($subjectsByGrade as $gradeRange => $subjectNames) {
                list($start, $end) = explode('-', $gradeRange);
                
                for ($grade = $start; $grade <= $end; $grade++) {
                    foreach ($subjectNames as $subject) {
                        $subjects[] = DB::table('subjects')->insertGetId([
                            'name' => $subject,
                            'code' => strtoupper(substr($subject, 0, 3)) . '-G' . $grade . '-B' . $branchId,
                            'description' => "{$subject} for Grade {$grade}",
                            'department_id' => $departments[array_rand($departments)],
                            'branch_id' => $branchId,
                            'grade_level' => (string)$grade,
                            'credits' => rand(3, 5),
                            'type' => in_array($subject, ['Mathematics', 'English', 'Science']) ? 'Core' : 'Elective',
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
        }
        
        return $subjects;
    }
    
    private function createFeeStructures($branches)
    {
        $fees = [];
        foreach ($branches as $branchId) {
            for ($grade = 1; $grade <= 12; $grade++) {
                $fees[] = DB::table('fee_structures')->insertGetId([
                    'id' => Str::uuid(),
                    'fee_type' => 'Tuition',
                    'grade' => (string)$grade,
                    'branch_id' => $branchId,
                    'amount' => rand(800, 1500),
                    'is_recurring' => true,
                    'recurrence_period' => 'Monthly',
                    'due_date' => now()->addDays(30)->format('Y-m-d'),
                    'academic_year' => '2024-2025',
                    'description' => 'Monthly tuition fee',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        return $fees;
    }
    
    private function createLibraryBooks($branches)
    {
        $bookTitles = [
            'Introduction to Mathematics', 'Physics Fundamentals', 'Chemistry Lab Manual',
            'Biology Textbook', 'World History', 'English Literature', 'Computer Science Basics'
        ];
        
        $books = [];
        foreach ($branches as $branchId) {
            foreach ($bookTitles as $title) {
                $qty = rand(10, 30);
                $books[] = DB::table('books')->insertGetId([
                    'title' => $title,
                    'author' => 'Dr. ' . ['Smith', 'Johnson'][array_rand(['Smith', 'Johnson'])],
                    'isbn' => '978-' . rand(1000000000, 9999999999),
                    'category' => 'Academic',
                    'publisher' => 'Oxford Press',
                    'published_year' => rand(2018, 2024),
                    'language' => 'English',
                    'total_copies' => $qty,
                    'available_copies' => $qty,
                    'location' => 'Shelf ' . chr(rand(65, 75)) . '-' . rand(1, 20),
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        return $books;
    }
    
    private function createTransportRoutes($branches)
    {
        $routes = [];
        foreach ($branches as $branchId) {
            for ($i = 1; $i <= 5; $i++) {
                $routes[] = DB::table('transport_routes')->insertGetId([
                    'route_name' => "Route " . chr(64 + $i),
                    'route_number' => 'R' . str_pad(count($routes) + 1, 3, '0', STR_PAD_LEFT),
                    'branch_id' => $branchId,
                    'start_point' => 'Zone ' . $i,
                    'end_point' => 'School',
                    'distance_km' => rand(5, 20),
                    'fare' => rand(200, 500),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
        }
        
        return $routes;
    }
    
    private function createEventsAndHolidays($branches)
    {
        $count = 0;
        foreach ($branches as $branchId) {
            DB::table('events')->insert([
                ['title' => 'Annual Sports Day', 'event_type' => 'Sports', 'branch_id' => $branchId,
                 'event_date' => now()->addDays(30)->format('Y-m-d'), 'start_time' => '09:00:00',
                 'end_time' => '17:00:00', 'venue' => 'School Ground', 'is_active' => true,
                 'created_at' => now(), 'updated_at' => now()]
            ]);
            
            DB::table('holidays')->insert([
                ['name' => 'New Year', 'date' => '2025-01-01', 'branch_id' => $branchId,
                 'holiday_type' => 'National', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
            ]);
            $count += 2;
        }
        
        return $count;
    }
    
    private function printFinalSummary($startTime)
    {
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        
        echo "\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "              ✅ COMPLETE SEEDING SUCCESSFUL! ✅\n";
        echo "════════════════════════════════════════════════════════════════\n\n";
        
        // Get actual counts
        $counts = [
            'Branches' => DB::table('branches')->count(),
            'Departments' => DB::table('departments')->count(),
            'Users' => DB::table('users')->count(),
            'Teachers (users table)' => DB::table('users')->where('role', 'Teacher')->count(),
            'Students (users table)' => DB::table('users')->where('role', 'Student')->count(),
            'Teachers (teachers table)' => DB::table('teachers')->count(),
            'Students (students table)' => DB::table('students')->count(),
            'Parents' => DB::table('users')->where('role', 'Parent')->count(),
            'Classes' => DB::table('classes')->count(),
            'Subjects' => DB::table('subjects')->count(),
            'Fee Structures' => DB::table('fee_structures')->count(),
            'Library Books' => DB::table('books')->count(),
            'Transport Routes' => DB::table('transport_routes')->count(),
            'Events' => DB::table('events')->count(),
            'Holidays' => DB::table('holidays')->count(),
        ];
        
        echo "📊 FINAL DATABASE SUMMARY:\n";
        echo "────────────────────────────────────────────────────────────────\n";
        foreach ($counts as $label => $count) {
            printf("  %-30s : %6d\n", $label, $count);
        }
        echo "────────────────────────────────────────────────────────────────\n\n";
        
        echo "🔐 LOGIN CREDENTIALS:\n";
        echo "────────────────────────────────────────────────────────────────\n";
        echo "  🔑 Super Admin:\n";
        echo "     Email:    admin@myschool.com\n";
        echo "     Password: Admin@123\n\n";
        echo "  🔑 Sample Teacher:\n";
        echo "     Email:    james.smith.t1@globaledu.com\n";
        echo "     Password: Teacher@123\n\n";
        echo "  🔑 All Students:\n";
        echo "     Email Format: firstname.lastname.stuXXXXX@student.globaledu.com\n";
        echo "     Password: Student@123\n\n";
        echo "  💡 All teachers: Teacher@123\n";
        echo "  💡 All students: Student@123\n";
        echo "  💡 All parents:  Parent@123\n";
        echo "────────────────────────────────────────────────────────────────\n\n";
        
        echo "⏱️  Execution Time: {$duration} seconds\n";
        echo "✅ All tables populated successfully!\n\n";
    }
}

