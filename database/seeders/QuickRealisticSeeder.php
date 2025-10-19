
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class QuickRealisticSeeder extends Seeder
{
    public function run()
    {
        echo "\n🚀 Starting Quick Realistic Seeder...\n\n";
        
        DB::beginTransaction();
        
        try {
            // 1. Create Branches (1 Main + 2 Sub)
            echo "📍 Creating Branches...\n";
            $mainBranch = DB::table('branches')->insertGetId([
                'name' => 'Global Education Network - Main Campus',
                'code' => 'GEN-MAIN',
                'branch_type' => 'HeadOffice',
                'parent_branch_id' => null,
                'address' => '1000 Education Boulevard',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'United States',
                'pincode' => '10001',
                'phone' => '+1-212-555-0001',
                'email' => 'main@globaledu.com',
                'website' => 'www.globaledu.com',
                'principal_name' => 'Dr. Robert Anderson',
                'principal_email' => 'principal@globaledu.com',
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
            
            $branch2 = DB::table('branches')->insertGetId([
                'name' => 'Global Education Network - Downtown',
                'code' => 'GEN-DT',
                'branch_type' => 'School',
                'parent_branch_id' => $mainBranch,
                'address' => '500 Learning Avenue',
                'city' => 'New York',
                'state' => 'NY',
                'country' => 'United States',
                'pincode' => '10002',
                'phone' => '+1-212-555-0002',
                'email' => 'downtown@globaledu.com',
                'website' => 'www.globaledu.com/downtown',
                'principal_name' => 'Dr. Sarah Johnson',
                'principal_email' => 'principal.dt@globaledu.com',
                'principal_contact' => '+1-212-555-0200',
                'established_date' => '2015-01-15',
                'total_capacity' => 1500,
                'current_enrollment' => 0,
                'board' => 'Cambridge International',
                'status' => 'Active',
                'is_active' => true,
                'is_main_branch' => false,
                'timezone' => 'America/New_York',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            echo "  ✅ Created 2 branches\n\n";
            
            // 2. Create Departments
            echo "🏢 Creating Departments...\n";
            $deptData = [
                'Mathematics', 'Science', 'English', 'Social Studies', 'Computer Science', 'Arts'
            ];
            
            foreach ([$mainBranch, $branch2] as $branchId) {
                foreach ($deptData as $deptName) {
                    DB::table('departments')->insert([
                        'name' => $deptName,
                        'head' => "Dr. " . $deptName . " Head",
                        'head_id' => null,
                        'description' => "Department of {$deptName}",
                        'branch_id' => $branchId,
                        'students_count' => 0,
                        'teachers_count' => 0,
                        'is_active' => true,
                        'established_date' => now()->subYears(2)->format('Y-m-d'),
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            }
            echo "  ✅ Created 12 departments\n\n";
            
            // 3. Create Admin User
            echo "👨‍💼 Creating Admin User...\n";
            DB::table('users')->insert([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@myschool.com',
                'phone' => '+1-212-555-9999',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'branch_id' => $mainBranch,
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
            echo "  ✅ Admin created\n\n";
            
            // 4. Create Teachers (10 per branch)
            echo "👨‍🏫 Creating Teachers...\n";
            $teacherNames = [
                ['James', 'Smith'], ['Mary', 'Johnson'], ['John', 'Williams'],
                ['Patricia', 'Brown'], ['Robert', 'Jones'], ['Jennifer', 'Garcia'],
                ['Michael', 'Miller'], ['Linda', 'Davis'], ['William', 'Rodriguez'],
                ['Elizabeth', 'Martinez']
            ];
            
            foreach ([$mainBranch, $branch2] as $branchId) {
                foreach ($teacherNames as $idx => $name) {
                    DB::table('users')->insert([
                        'first_name' => $name[0],
                        'last_name' => $name[1],
                        'email' => strtolower($name[0] . '.' . $name[1] . $branchId) . '@globaledu.com',
                        'phone' => '+1-212-555-' . str_pad(1000 + $idx + ($branchId * 100), 4, '0', STR_PAD_LEFT),
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
            echo "  ✅ Created 20 teachers\n\n";
            
            // 5. Create Classes for Grade 1-12
            echo "🎓 Creating Classes...\n";
            $classCount = 0;
            foreach ([$mainBranch, $branch2] as $branchId) {
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach (['A', 'B'] as $section) {
                        DB::table('classes')->insert([
                            'class_name' => "Grade {$grade}-{$section}",
                            'grade' => (string)$grade,
                            'section' => $section,
                            'branch_id' => $branchId,
                            'academic_year' => '2024-2025',
                            'class_teacher_id' => null,
                            'capacity' => 40,
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
            
            // 6. Create Students (15 per class = 720 total)
            echo "👨‍🎓 Creating Students...\n";
            $studentNames = [
                'James', 'Mary', 'John', 'Patricia', 'Robert', 'Jennifer', 'Michael', 'Linda',
                'William', 'Elizabeth', 'David', 'Barbara', 'Richard', 'Susan', 'Joseph'
            ];
            $lastNames = [
                'Smith', 'Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis'
            ];
            
            $studentCount = 0;
            foreach ([$mainBranch, $branch2] as $branchId) {
                for ($grade = 1; $grade <= 12; $grade++) {
                    foreach (['A', 'B'] as $section) {
                        for ($i = 0; $i < 15; $i++) {
                            $studentCount++;
                            $firstName = $studentNames[array_rand($studentNames)];
                            $lastName = $lastNames[array_rand($lastNames)];
                            
                            DB::table('users')->insert([
                                'first_name' => $firstName,
                                'last_name' => $lastName,
                                'email' => strtolower($firstName . '.' . $lastName . '.' . $studentCount) . '@student.globaledu.com',
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
                        
                        if ($studentCount % 100 == 0) {
                            echo "  📊 Created {$studentCount} students...\n";
                        }
                    }
                }
            }
            echo "  ✅ Created {$studentCount} students\n\n";
            
            // 7. Create Subjects
            echo "📖 Creating Subjects...\n";
            $departments = DB::table('departments')->get();
            $subjectsByGrade = [
                '1-5' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Art'],
                '6-8' => ['Mathematics', 'English', 'Science', 'Social Studies', 'Computer'],
                '9-12' => ['Mathematics', 'English', 'Physics', 'Chemistry', 'Biology', 'Computer Science']
            ];
            
            $subjectCount = 0;
            foreach ([$mainBranch, $branch2] as $branchId) {
                $branchDepts = $departments->where('branch_id', $branchId);
                
                foreach ($subjectsByGrade as $gradeRange => $subjects) {
                    list($startGrade, $endGrade) = explode('-', $gradeRange);
                    
                    for ($grade = $startGrade; $grade <= $endGrade; $grade++) {
                        foreach ($subjects as $subjectName) {
                            $dept = $branchDepts->random();
                            
                            DB::table('subjects')->insert([
                                'name' => $subjectName,
                                'code' => strtoupper(substr($subjectName, 0, 3)) . '-G' . $grade . '-B' . $branchId,
                                'description' => "{$subjectName} for Grade {$grade}",
                                'department_id' => $dept->id,
                                'teacher_id' => null,
                                'branch_id' => $branchId,
                                'grade_level' => (string)$grade,
                                'credits' => rand(3, 5),
                                'type' => in_array($subjectName, ['Mathematics', 'English', 'Science']) ? 'Core' : 'Elective',
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
            
            DB::commit();
            
            // Print Summary
            echo "\n";
            echo "═══════════════════════════════════════════════════════════════\n";
            echo "                  🎉 SEEDING COMPLETED! 🎉\n";
            echo "═══════════════════════════════════════════════════════════════\n\n";
            echo "📊 DATABASE SUMMARY:\n";
            echo "───────────────────────────────────────────────────────────────\n";
            echo "  Branches:      2\n";
            echo "  Departments:   12\n";
            echo "  Teachers:      20\n";
            echo "  Students:      {$studentCount}\n";
            echo "  Classes:       {$classCount}\n";
            echo "  Subjects:      {$subjectCount}\n";
            echo "───────────────────────────────────────────────────────────────\n\n";
            echo "🔐 LOGIN CREDENTIALS:\n";
            echo "───────────────────────────────────────────────────────────────\n";
            echo "  Admin:\n";
            echo "    Email:    admin@myschool.com\n";
            echo "    Password: Admin@123\n\n";
            echo "  Teachers: Password = Teacher@123\n";
            echo "  Students: Password = Student@123\n";
            echo "───────────────────────────────────────────────────────────────\n\n";
            echo "✅ Database seeded successfully!\n\n";
            
        } catch (\Exception $e) {
            DB::rollBack();
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            echo "File: " . $e->getFile() . "\n";
            echo "Line: " . $e->getLine() . "\n";
        }
    }
}

