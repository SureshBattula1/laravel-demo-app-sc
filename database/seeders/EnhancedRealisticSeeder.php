<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EnhancedRealisticSeeder extends Seeder
{
    public function run()
    {
        echo "\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "  🚀 ENHANCED REALISTIC DATA SEEDER\n";
        echo "  1 Main Branch + 5 Sub-Branches + Full School Data\n";
        echo "════════════════════════════════════════════════════════════════\n\n";
        
        $startTime = microtime(true);
        
        try {
            // ===== STEP 1: CREATE BRANCHES =====
            echo "📍 STEP 1: Creating Branches (1 Main + 5 Sub)...\n";
            
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
            
            $branches = [$mainBranch];
            $branchData = [
                ['name' => 'Downtown Campus', 'code' => 'DTC', 'city' => 'New York', 'state' => 'NY'],
                ['name' => 'Westside Academy', 'code' => 'WSA', 'city' => 'Los Angeles', 'state' => 'CA'],
                ['name' => 'Lakeside School', 'code' => 'LSS', 'city' => 'Chicago', 'state' => 'IL'],
                ['name' => 'Sunrise Campus', 'code' => 'SRC', 'city' => 'Houston', 'state' => 'TX'],
                ['name' => 'Valley View', 'code' => 'VVS', 'city' => 'Phoenix', 'state' => 'AZ']
            ];
            
            foreach ($branchData as $data) {
                $branchId = DB::table('branches')->insertGetId([
                    'name' => 'Global Education Network - ' . $data['name'],
                    'code' => 'GEN-' . $data['code'],
                    'branch_type' => 'School',
                    'parent_branch_id' => $mainBranch,
                    'address' => rand(100, 999) . ' Education Street',
                    'city' => $data['city'],
                    'state' => $data['state'],
                    'country' => 'United States',
                    'pincode' => (string)rand(10000, 99999),
                    'phone' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'email' => strtolower($data['code']) . '@globaledu.com',
                    'principal_name' => 'Dr. Principal ' . $data['code'],
                    'principal_contact' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                    'established_date' => now()->subYears(rand(5, 15))->format('Y-m-d'),
                    'total_capacity' => 2000,
                    'board' => 'Cambridge International',
                    'status' => 'Active',
                    'is_active' => true,
                    'is_main_branch' => false,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                $branches[] = $branchId;
            }
            echo "  ✅ Created 6 branches (1 HQ + 5 Schools)\n\n";
            
            // ===== STEP 2: CREATE DEPARTMENTS =====
            echo "🏢 STEP 2: Creating Departments (10 per branch)...\n";
            $deptNames = [
                'Mathematics', 'Science', 'English Language', 'Social Studies', 'Computer Science',
                'Physical Education', 'Arts & Music', 'Foreign Languages', 'Commerce', 'Biology'
            ];
            
            $deptCount = 0;
            foreach ($branches as $branchId) {
                foreach ($deptNames as $deptName) {
                    DB::table('departments')->insert([
                        'name' => $deptName,
                        'head' => "Dr. {$deptName} Head",
                        'branch_id' => $branchId,
                        'students_count' => 0,
                        'teachers_count' => 0,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $deptCount++;
                }
            }
            echo "  ✅ Created {$deptCount} departments\n\n";
            
            // ===== STEP 3: CREATE ADMIN =====
            echo "👨‍💼 STEP 3: Creating Admin User...\n";
            DB::table('users')->insert([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@myschool.com',
                'phone' => '+1-800-555-0001',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  ✅ Admin created: admin@myschool.com / Admin@123\n\n";
            
            // ===== STEP 4: CREATE TEACHERS =====
            echo "👨‍🏫 STEP 4: Creating Teachers (20 per branch)...\n";
            $teacherFirstNames = [
                'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
                'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
                'Thomas', 'Sarah', 'Charles', 'Karen'
            ];
            $teacherLastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'];
            
            $teacherCount = 0;
            foreach ($branches as $branchId) {
                for ($i = 0; $i < 20; $i++) {
                    $firstName = $teacherFirstNames[$i];
                    $lastName = $teacherLastNames[array_rand($teacherLastNames)];
                    $teacherCount++;
                    
                    // Generate unique phone using teacher count
                    $phoneNum = 1000000 + $teacherCount;
                    $phoneFormatted = substr($phoneNum, 0, 3) . '-555-' . substr($phoneNum, 3);
                    
                    DB::table('users')->insert([
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'email' => strtolower($firstName . '.' . $lastName . '.t' . $teacherCount) . '@globaledu.com',
                        'phone' => '+1-' . $phoneFormatted,
                        'password' => Hash::make('Teacher@123'),
                        'role' => 'Teacher',
                        'branch_id' => $branchId,
                        'is_active' => true,
                        'email_verified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            echo "  ✅ Created {$teacherCount} teachers\n\n";
            
            // ===== STEP 5: CREATE CLASSES =====
            echo "🎓 STEP 5: Creating Classes (Grade 1-12, 4 sections each)...\n";
            $classCount = 0;
            foreach ($branches as $branchId) {
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach (['A', 'B', 'C', 'D'] as $section) {
                        DB::table('classes')->insert([
                            'class_name' => "Grade {$grade}-{$section}",
                            'grade' => (string)$grade,
                            'section' => $section,
                            'branch_id' => $branchId,
                            'academic_year' => '2024-2025',
                            'capacity' => 50,
                            'current_strength' => 0,
                            'room_number' => 'Room ' . (100 + $classCount),
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $classCount++;
                    }
                }
            }
            echo "  ✅ Created {$classCount} classes\n\n";
            
            // ===== STEP 6: CREATE STUDENTS =====
            echo "👨‍🎓 STEP 6: Creating Students (50 per section)...\n";
            echo "  This will create " . ($classCount * 50) . " students...\n";
            
            $studentFirstNames = [
                'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
                'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica',
                'Thomas', 'Sarah', 'Charles', 'Karen', 'Christopher', 'Nancy', 'Daniel', 'Lisa',
                'Matthew', 'Betty', 'Anthony', 'Margaret', 'Mark', 'Sandra', 'Donald', 'Ashley',
                'Steven', 'Kimberly', 'Paul', 'Emily', 'Andrew', 'Donna', 'Joshua', 'Michelle',
                'Kenneth', 'Dorothy', 'Kevin', 'Carol', 'Brian', 'Amanda', 'George', 'Melissa',
                'Edward', 'Deborah'
            ];
            $studentLastNames = [
                'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis',
                'Rodriguez', 'Martinez', 'Hernandez', 'Lopez', 'Gonzalez', 'Wilson'
            ];
            
            $studentCount = 0;
            $batchSize = 50;
            $students = [];
            
            foreach ($branches as $branchIdx => $branchId) {
                echo "  📚 Branch " . ($branchIdx + 1) . "...\n";
                
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach (['A', 'B', 'C', 'D'] as $section) {
                        for ($i = 0; $i < 50; $i++) {
                            $studentCount++;
                            $firstName = $studentFirstNames[array_rand($studentFirstNames)];
                            $lastName = $studentLastNames[array_rand($studentLastNames)];
                            
                            // Use student count to generate unique phone numbers
                            $phoneBase = 2000000 + $studentCount;
                            $phoneFormatted = substr($phoneBase, 0, 3) . '-555-' . substr($phoneBase, 3);
                            
                            $students[] = [
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
                            
                            // Insert in batches
                            if (count($students) >= $batchSize) {
                                DB::table('users')->insert($students);
                                $students = [];
                                echo "    ✓ {$studentCount} students created...\n";
                            }
                        }
                    }
                }
            }
            
            // Insert remaining
            if (count($students) > 0) {
                DB::table('users')->insert($students);
            }
            echo "  ✅ Created {$studentCount} students\n\n";
            
            // ===== STEP 7: CREATE SUBJECTS =====
            echo "📖 STEP 7: Creating Subjects...\n";
            $deptIds = DB::table('departments')->pluck('id')->toArray();
            $subjectCount = 0;
            
            $subjectsByGrade = [
                '1-5' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art', 'Physical Education'],
                '6-8' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer', 'Physical Education'],
                '9-12' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science', 'History', 'Economics']
            ];
            
            foreach ($branches as $branchId) {
                foreach ($subjectsByGrade as $gradeRange => $subjects) {
                    list($start, $end) = explode('-', $gradeRange);
                    
                    for ($grade = $start; $grade <= $end; $grade++) {
                        foreach ($subjects as $subject) {
                            DB::table('subjects')->insert([
                                'name' => $subject,
                                'code' => strtoupper(substr($subject, 0, 3)) . '-G' . $grade . '-B' . $branchId,
                                'description' => "{$subject} for Grade {$grade}",
                                'department_id' => $deptIds[array_rand($deptIds)],
                                'branch_id' => $branchId,
                                'grade_level' => (string)$grade,
                                'credits' => rand(3, 5),
                                'type' => in_array($subject, ['Mathematics', 'English', 'Science']) ? 'Core' : 'Elective',
                                'is_active' => true,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                            $subjectCount++;
                        }
                    }
                }
            }
            echo "  ✅ Created {$subjectCount} subjects\n\n";
            
            // ===== STEP 8: CREATE FEE STRUCTURES =====
            echo "💰 STEP 8: Creating Fee Structures...\n";
            $feeTypes = [
                ['type' => 'Tuition', 'amount' => 1000, 'period' => 'Monthly'],
                ['type' => 'Library', 'amount' => 200, 'period' => 'Annually'],
                ['type' => 'Laboratory', 'amount' => 500, 'period' => 'Annually'],
                ['type' => 'Sports', 'amount' => 300, 'period' => 'Annually'],
            ];
            
            $feeCount = 0;
            foreach ($branches as $branchId) {
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach ($feeTypes as $feeType) {
                        DB::table('fee_structures')->insert([
                            'id' => \Illuminate\Support\Str::uuid(),
                            'fee_type' => $feeType['type'],
                            'grade' => (string)$grade,
                            'branch_id' => $branchId,
                            'amount' => $feeType['amount'],
                            'is_recurring' => $feeType['period'] != 'Annually',
                            'recurrence_period' => $feeType['period'],
                            'due_date' => now()->addDays(30)->format('Y-m-d'),
                            'academic_year' => '2024-2025',
                            'description' => $feeType['type'] . ' fee for Grade ' . $grade,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $feeCount++;
                    }
                }
            }
            echo "  ✅ Created {$feeCount} fee structures\n\n";
            
            // ===== STEP 9: CREATE LIBRARY BOOKS =====
            echo "📚 STEP 9: Creating Library Books...\n";
            $bookTitles = [
                'Introduction to Mathematics', 'Physics Fundamentals', 'Chemistry Lab Manual',
                'Biology Textbook', 'World History', 'English Literature', 'Computer Science Basics',
                'Advanced Mathematics', 'Environmental Science', 'Business Studies'
            ];
            
            $bookCount = 0;
            foreach ($branches as $branchId) {
                foreach ($bookTitles as $title) {
                    $qty = rand(10, 30);
                    DB::table('books')->insert([
                        'title' => $title,
                        'author' => 'Dr. ' . ['Smith', 'Johnson', 'Williams'][array_rand(['Smith', 'Johnson', 'Williams'])],
                        'isbn' => '978-' . rand(1000000000, 9999999999),
                        'publisher' => ['Oxford Press', 'Cambridge Press', 'McGraw-Hill'][array_rand(['Oxford Press', 'Cambridge Press', 'McGraw-Hill'])],
                        'publication_year' => rand(2018, 2024),
                        'category' => 'Academic',
                        'branch_id' => $branchId,
                        'quantity' => $qty,
                        'available_quantity' => $qty,
                        'shelf_location' => 'Shelf ' . chr(rand(65, 75)) . '-' . rand(1, 20),
                        'price' => rand(20, 100),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $bookCount++;
                }
            }
            echo "  ✅ Created {$bookCount} library books\n\n";
            
            // ===== STEP 10: CREATE TRANSPORT ROUTES =====
            echo "🚌 STEP 10: Creating Transport Routes...\n";
            $routeCount = 0;
            foreach ($branches as $branchId) {
                for ($i = 1; $i <= 5; $i++) {
                    DB::table('transport_routes')->insert([
                        'route_name' => "Route " . chr(64 + $i),
                        'route_number' => 'R' . str_pad($routeCount + 1, 3, '0', STR_PAD_LEFT),
                        'branch_id' => $branchId,
                        'start_point' => 'City Zone ' . $i,
                        'end_point' => 'School Campus',
                        'stops' => 'Stop1, Stop2, Stop3',
                        'distance_km' => rand(5, 20),
                        'fare' => rand(200, 500),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $routeCount++;
                }
            }
            echo "  ✅ Created {$routeCount} transport routes\n\n";
            
            // ===== STEP 11: CREATE EVENTS & HOLIDAYS =====
            echo "🎉 STEP 11: Creating Events and Holidays...\n";
            $eventCount = 0;
            $holidayCount = 0;
            
            foreach ($branches as $branchId) {
                // Events
                DB::table('events')->insert([
                    ['title' => 'Annual Sports Day', 'event_type' => 'Sports', 'branch_id' => $branchId, 
                     'event_date' => now()->addDays(30)->format('Y-m-d'), 'start_time' => '09:00:00', 
                     'end_time' => '17:00:00', 'venue' => 'School Ground', 'is_active' => true, 
                     'created_at' => now(), 'updated_at' => now()],
                    ['title' => 'Science Exhibition', 'event_type' => 'Academic', 'branch_id' => $branchId,
                     'event_date' => now()->addDays(45)->format('Y-m-d'), 'start_time' => '10:00:00',
                     'end_time' => '16:00:00', 'venue' => 'Science Lab', 'is_active' => true,
                     'created_at' => now(), 'updated_at' => now()]
                ]);
                $eventCount += 2;
                
                // Holidays
                DB::table('holidays')->insert([
                    ['name' => 'New Year', 'date' => '2025-01-01', 'branch_id' => $branchId,
                     'holiday_type' => 'National', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()],
                    ['name' => 'Independence Day', 'date' => '2025-07-04', 'branch_id' => $branchId,
                     'holiday_type' => 'National', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]
                ]);
                $holidayCount += 2;
            }
            echo "  ✅ Created {$eventCount} events\n";
            echo "  ✅ Created {$holidayCount} holidays\n\n";
            
            // ===== FINAL SUMMARY =====
            $endTime = microtime(true);
            $duration = round($endTime - $startTime, 2);
            
            echo "\n";
            echo "════════════════════════════════════════════════════════════════\n";
            echo "              ✅ SEEDING COMPLETED SUCCESSFULLY! ✅\n";
            echo "════════════════════════════════════════════════════════════════\n\n";
            echo "📊 FINAL DATABASE SUMMARY:\n";
            echo "────────────────────────────────────────────────────────────────\n";
            printf("  %-25s : %6d\n", "Branches", 6);
            printf("  %-25s : %6d\n", "Departments", $deptCount);
            printf("  %-25s : %6d\n", "Teachers", $teacherCount);
            printf("  %-25s : %6d\n", "Students", $studentCount);
            printf("  %-25s : %6d\n", "Classes", $classCount);
            printf("  %-25s : %6d\n", "Subjects", $subjectCount);
            printf("  %-25s : %6d\n", "Fee Structures", $feeCount);
            printf("  %-25s : %6d\n", "Library Books", $bookCount);
            printf("  %-25s : %6d\n", "Transport Routes", $routeCount);
            printf("  %-25s : %6d\n", "Events", $eventCount);
            printf("  %-25s : %6d\n", "Holidays", $holidayCount);
            echo "────────────────────────────────────────────────────────────────\n";
            printf("  %-25s : %6d\n", "TOTAL USERS", $teacherCount + $studentCount + 1);
            echo "────────────────────────────────────────────────────────────────\n\n";
            
            echo "🔐 LOGIN CREDENTIALS:\n";
            echo "────────────────────────────────────────────────────────────────\n";
            echo "  🔑 Super Admin:\n";
            echo "     Email:    admin@myschool.com\n";
            echo "     Password: Admin@123\n\n";
            echo "  🔑 Sample Teacher:\n";
            echo "     Email:    james.smith.t1@globaledu.com\n";
            echo "     Password: Teacher@123\n\n";
            echo "  🔑 Sample Student:\n";
            echo "     Email:    [check database for student emails]\n";
            echo "     Password: Student@123\n\n";
            echo "  💡 All teachers: Password = Teacher@123\n";
            echo "  💡 All students: Password = Student@123\n";
            echo "────────────────────────────────────────────────────────────────\n\n";
            
            echo "⏱️  Execution Time: {$duration} seconds\n";
            echo "✅ System ready for use!\n\n";
            
        } catch (\Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "📄 File: " . $e->getFile() . "\n";
            echo "📍 Line: " . $e->getLine() . "\n\n";
        }
    }
}

