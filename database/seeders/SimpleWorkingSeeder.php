<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class SimpleWorkingSeeder extends Seeder
{
    public function run()
    {
        echo "\n🚀 Starting Simple Working Seeder...\n\n";
        
        try {
            // 1. Create Main Branch
            echo "📍 Creating Branch...\n";
            $branchId = DB::table('branches')->insertGetId([
                'name' => 'Global Education Network',
                'code' => 'GEN-001',
                'branch_type' => 'HeadOffice',
                'parent_branch_id' => null,
                'address' => '1000 Education Boulevard',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'United States',
                'pincode' => '10001',
                'phone' => '+1-212-555-0001',
                'email' => 'main@globaledu.com',
                'principal_name' => 'Dr. Robert Anderson',
                'principal_contact' => '+1-212-555-0100',
                'established_date' => '2010-01-15',
                'total_capacity' => 2000,
                'board' => 'International Baccalaureate',
                'status' => 'Active',
                'is_active' => true,
                'is_main_branch' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  ✅ Branch created (ID: {$branchId})\n\n";
            
            // 2. Create Departments
            echo "🏢 Creating Departments...\n";
            $departments = ['Mathematics', 'Science', 'English', 'Social Studies', 'Computer Science'];
            foreach ($departments as $dept) {
                DB::table('departments')->insert([
                    'name' => $dept,
                    'head' => "Dr. {$dept} Head",
                    'branch_id' => $branchId,
                    'students_count' => 0,
                    'teachers_count' => 0,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            echo "  ✅ Created " . count($departments) . " departments\n\n";
            
            // 3. Create Admin
            echo "👨‍💼 Creating Admin...\n";
            DB::table('users')->insert([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@myschool.com',
                'phone' => '+1-212-555-9999',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'branch_id' => $branchId,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  ✅ Admin created\n\n";
            
            // 4. Create Teachers (20 total)
            echo "👨‍🏫 Creating Teachers...\n";
            $teacherNames = [
                'James Smith', 'Mary Johnson', 'John Williams', 'Patricia Brown', 'Robert Jones',
                'Jennifer Garcia', 'Michael Miller', 'Linda Davis', 'William Rodriguez', 'Elizabeth Martinez',
                'David Hernandez', 'Barbara Lopez', 'Richard Gonzalez', 'Susan Wilson', 'Joseph Anderson',
                'Jessica Thomas', 'Charles Taylor', 'Sarah Moore', 'Christopher Jackson', 'Karen White'
            ];
            
            $teacherCount = 0;
            foreach ($teacherNames as $name) {
                list($firstName, $lastName) = explode(' ', $name);
                $teacherCount++;
                
                DB::table('users')->insert([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => strtolower($firstName . '.' . $lastName) . '@globaledu.com',
                    'phone' => '+1-212-555-' . str_pad(1000 + $teacherCount, 4, '0', STR_PAD_LEFT),
                    'password' => Hash::make('Teacher@123'),
                    'role' => 'Teacher',
                    'branch_id' => $branchId,
                    'is_active' => true,
                    'email_verified_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            echo "  ✅ Created {$teacherCount} teachers\n\n";
            
            // 5. Create Classes (Grade 1-12, Section A & B each)
            echo "🎓 Creating Classes...\n";
            $classCount = 0;
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach (['A', 'B'] as $section) {
                    DB::table('classes')->insert([
                        'class_name' => "Grade {$grade}-{$section}",
                        'grade' => (string)$grade,
                        'section' => $section,
                        'branch_id' => $branchId,
                        'academic_year' => '2024-2025',
                        'capacity' => 30,
                        'current_strength' => 0,
                        'room_number' => 'Room ' . (100 + $classCount),
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $classCount++;
                }
            }
            echo "  ✅ Created {$classCount} classes\n\n";
            
            // 6. Create Students (20 per class = 480 total)
            echo "👨‍🎓 Creating Students...\n";
            $firstNames = ['James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda', 'William', 'Elizabeth',
                           'David', 'Barbara', 'Richard', 'Susan', 'Joseph', 'Jessica', 'Thomas', 'Sarah', 'Charles', 'Karen'];
            $lastNames = ['Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez', 'Martinez'];
            
            $studentCount = 0;
            for ($grade = 1; $grade <= 12; $grade++) {
                foreach (['A', 'B'] as $section) {
                    echo "  📝 Grade {$grade}-{$section}...\n";
                    
                    for ($i = 0; $i < 20; $i++) {
                        $studentCount++;
                        $firstName = $firstNames[array_rand($firstNames)];
                        $lastName = $lastNames[array_rand($lastNames)];
                        
                        DB::table('users')->insert([
                            'first_name' => $firstName,
                            'last_name' => $lastName,
                            'email' => strtolower($firstName . '.' . $lastName . '.stu' . str_pad($studentCount, 4, '0', STR_PAD_LEFT)) . '@student.globaledu.com',
                            'phone' => '+1-' . rand(200, 999) . '-555-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT),
                            'password' => Hash::make('Student@123'),
                            'role' => 'Student',
                            'branch_id' => $branchId,
                            'is_active' => true,
                            'email_verified_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }
                }
            }
            echo "  ✅ Created {$studentCount} students\n\n";
            
            // 7. Create Subjects
            echo "📖 Creating Subjects...\n";
            $deptIds = DB::table('departments')->pluck('id')->toArray();
            $subjectCount = 0;
            
            for ($grade = 1; $grade <= 12; $grade++) {
                $subjects = $grade <= 5 
                    ? ['Mathematics', 'English', 'Science', 'Social Studies'] 
                    : ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science'];
                
                foreach ($subjects as $subject) {
                    DB::table('subjects')->insert([
                        'name' => $subject,
                        'code' => strtoupper(substr($subject, 0, 3)) . '-G' . $grade,
                        'description' => "{$subject} for Grade {$grade}",
                        'department_id' => $deptIds[array_rand($deptIds)],
                        'branch_id' => $branchId,
                        'grade_level' => (string)$grade,
                        'credits' => 4,
                        'type' => in_array($subject, ['Mathematics', 'English', 'Science']) ? 'Core' : 'Elective',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $subjectCount++;
                }
            }
            echo "  ✅ Created {$subjectCount} subjects\n\n";
            
            // Summary
            echo "\n";
            echo "═══════════════════════════════════════════════════════════════\n";
            echo "                  🎉 SEEDING COMPLETED! 🎉\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";
            echo "📊 DATABASE SUMMARY:\n";
            echo "───────────────────────────────────────────────────────────────\n";
            echo "  Branches:      1\n";
            echo "  Departments:   " . count($departments) . "\n";
            echo "  Teachers:      {$teacherCount}\n";
            echo "  Students:      {$studentCount}\n";
            echo "  Classes:       {$classCount}\n";
            echo "  Subjects:      {$subjectCount}\n";
            echo "───────────────────────────────────────────────────────────────\n\n";
            echo "🔐 LOGIN CREDENTIALS:\n";
            echo "───────────────────────────────────────────────────────────────\n";
            echo "  Admin:     admin@myschool.com / Admin@123\n";
            echo "  Teachers:  [any teacher email] / Teacher@123\n";
            echo "  Students:  [any student email] / Student@123\n";
            echo "───────────────────────────────────────────────────────────────\n\n";
            echo "✅ Database seeded successfully!\n\n";
            
        } catch (\Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n\n";
        }
    }
}

