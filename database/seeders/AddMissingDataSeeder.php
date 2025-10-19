<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AddMissingDataSeeder extends Seeder
{
    public function run()
    {
        echo "\n🔧 Adding Missing Data to Existing Database...\n\n";
        
        try {
            $branches = DB::table('branches')->pluck('id')->toArray();
            $departments = DB::table('departments')->pluck('id')->toArray();
            
            // 1. Create Sections
            echo "📑 Creating Sections...\n";
            $sectionCounter = 0;
            foreach ($branches as $branchId) {
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach (['A', 'B', 'C', 'D'] as $sectionName) {
                        $sectionCounter++;
                        DB::table('sections')->insert([
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
            echo "  ✅ Created {$sectionCounter} sections\n\n";
            
            // 2. Create Subjects
            echo "📖 Creating Subjects...\n";
            $subjectsByGrade = [
                '1-5' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art'],
                '6-8' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer'],
                '9-12' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science']
            ];
            
            $subjectCount = 0;
            foreach ($branches as $branchId) {
                foreach ($subjectsByGrade as $gradeRange => $subjectNames) {
                    list($start, $end) = explode('-', $gradeRange);
                    
                    for ($grade = $start; $grade <= $end; $grade++) {
                        foreach ($subjectNames as $subject) {
                            DB::table('subjects')->insert([
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
                            $subjectCount++;
                        }
                    }
                }
            }
            echo "  ✅ Created {$subjectCount} subjects\n\n";
            
            // 3. Create Fee Structures
            echo "💰 Creating Fee Structures...\n";
            $feeCount = 0;
            foreach ($branches as $branchId) {
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach (['Tuition', 'Library', 'Laboratory', 'Sports'] as $feeType) {
                        DB::table('fee_structures')->insert([
                            'id' => Str::uuid(),
                            'fee_type' => $feeType,
                            'grade' => (string)$grade,
                            'branch_id' => $branchId,
                            'amount' => rand(500, 1500),
                            'is_recurring' => $feeType == 'Tuition',
                            'recurrence_period' => $feeType == 'Tuition' ? 'Monthly' : 'Annually',
                            'due_date' => now()->addDays(30)->format('Y-m-d'),
                            'academic_year' => '2024-2025',
                            'description' => $feeType . ' fee for Grade ' . $grade,
                            'is_active' => true,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                        $feeCount++;
                    }
                }
            }
            echo "  ✅ Created {$feeCount} fee structures\n\n";
            
            // 4. Create Library Books
            echo "📚 Creating Library Books...\n";
            $bookTitles = [
                'Introduction to Mathematics', 'Physics Fundamentals', 'Chemistry Lab Manual',
                'Biology Textbook', 'World History', 'English Literature', 'Computer Science Basics'
            ];
            
            $bookCount = 0;
            foreach ($branches as $branchId) {
                foreach ($bookTitles as $title) {
                    $qty = rand(10, 30);
                    DB::table('books')->insert([
                        'title' => $title,
                        'author' => 'Dr. ' . ['Smith', 'Johnson', 'Williams'][array_rand(['Smith', 'Johnson', 'Williams'])],
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
                    $bookCount++;
                }
            }
            echo "  ✅ Created {$bookCount} books\n\n";
            
            // 5. Create Transport Routes
            echo "🚌 Creating Transport Routes...\n";
            $routeCount = 0;
            foreach ($branches as $branchId) {
                for ($i = 1; $i <= 5; $i++) {
                    $routeCount++;
                    DB::table('transport_routes')->insert([
                        'route_name' => "Route " . chr(64 + $i),
                        'route_number' => 'R-B' . $branchId . '-' . str_pad($i, 2, '0', STR_PAD_LEFT),
                        'branch_id' => $branchId,
                        'description' => 'Route ' . chr(64 + $i) . ' - Daily school transport',
                        'stops' => json_encode(['Stop 1', 'Stop 2', 'Stop 3', 'Stop 4', 'School']),
                        'distance' => rand(5, 20),
                        'estimated_time' => rand(30, 90),
                        'fare' => rand(200, 500),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            echo "  ✅ Created {$routeCount} routes\n\n";
            
            // 6. Create Events & Holidays
            echo "🎉 Creating Events & Holidays...\n";
            foreach ($branches as $branchId) {
                DB::table('events')->insert([
                    'title' => 'Annual Sports Day',
                    'event_type' => 'Sports',
                    'branch_id' => $branchId,
                    'event_date' => now()->addDays(30)->format('Y-m-d'),
                    'start_time' => '09:00:00',
                    'end_time' => '17:00:00',
                    'venue' => 'School Ground',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
                
                DB::table('holidays')->insert([
                    'name' => 'New Year',
                    'date' => '2025-01-01',
                    'branch_id' => $branchId,
                    'holiday_type' => 'National',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            echo "  ✅ Created events & holidays\n\n";
            
            echo "════════════════════════════════════════════════════════════════\n";
            echo "              ✅ MISSING DATA ADDED SUCCESSFULLY! ✅\n";
            echo "════════════════════════════════════════════════════════════════\n\n";
            
            $this->printSummary();
            
        } catch (\Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "📄 File: " . $e->getFile() . "\n";
            echo "📍 Line: " . $e->getLine() . "\n\n";
        }
    }
    
    private function printSummary()
    {
        echo "📊 FINAL DATABASE STATUS:\n";
        echo "────────────────────────────────────────────────────────────────\n";
        printf("  %-30s : %6d\n", "Branches", DB::table('branches')->count());
        printf("  %-30s : %6d\n", "Departments", DB::table('departments')->count());
        printf("  %-30s : %6d\n", "Classes", DB::table('classes')->count());
        printf("  %-30s : %6d\n", "Sections", DB::table('sections')->count());
        printf("  %-30s : %6d\n", "Users (Total)", DB::table('users')->count());
        printf("  %-30s : %6d\n", "Teachers (users table)", DB::table('users')->where('role', 'Teacher')->count());
        printf("  %-30s : %6d\n", "Teachers (teachers table)", DB::table('teachers')->count());
        printf("  %-30s : %6d\n", "Students (users table)", DB::table('users')->where('role', 'Student')->count());
        printf("  %-30s : %6d\n", "Students (students table)", DB::table('students')->count());
        printf("  %-30s : %6d\n", "Subjects", DB::table('subjects')->count());
        printf("  %-30s : %6d\n", "Fee Structures", DB::table('fee_structures')->count());
        printf("  %-30s : %6d\n", "Library Books", DB::table('books')->count());
        printf("  %-30s : %6d\n", "Transport Routes", DB::table('transport_routes')->count());
        printf("  %-30s : %6d\n", "Events", DB::table('events')->count());
        printf("  %-30s : %6d\n", "Holidays", DB::table('holidays')->count());
        echo "────────────────────────────────────────────────────────────────\n\n";
        
        echo "🔐 LOGIN CREDENTIALS:\n";
        echo "────────────────────────────────────────────────────────────────\n";
        echo "  Admin:    admin@myschool.com / Admin@123\n";
        echo "  Teachers: [any teacher email] / Teacher@123\n";
        echo "  Students: [any student email] / Student@123\n";
        echo "────────────────────────────────────────────────────────────────\n\n";
        echo "✅ Complete data in ALL tables!\n\n";
    }
}

