<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Subject;
use Carbon\Carbon;

class CompleteSchoolManagementSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting Complete School Management System Seeder...');

        DB::beginTransaction();

        try {
            // 1. Create Branches
            $this->command->info('Creating branches...');
            $mainBranch = $this->createBranches();

            // 2. Create Departments
            $this->command->info('Creating departments...');
            $departments = $this->createDepartments($mainBranch->id);

            // 3. Create Subjects
            $this->command->info('Creating subjects...');
            $subjects = $this->createSubjects($departments);

            // 4. Create Admin Users
            $this->command->info('Creating admins...');
            $this->createAdmins($mainBranch->id);

            // 5. Create Teachers
            $this->command->info('Creating teachers...');
            $teachers = $this->createTeachers($mainBranch->id);

            // 6. Create Parents
            $this->command->info('Creating parents...');
            $parents = $this->createParents();

            // 7. Create Students
            $this->command->info('Creating students...');
            $students = $this->createStudents($mainBranch->id, $parents);

            // 8. Create Fee Types & Structures
            $this->command->info('Creating fee structures...');
            $this->createFeeStructures($mainBranch->id);

            // 9. Create Sample Data
            $this->command->info('Creating sample data...');
            $this->createSampleData($students, $teachers, $subjects);

            DB::commit();

            $this->command->info('✅ Seeding completed successfully!');
            $this->command->info('📊 Summary:');
            $this->command->info('   - Branches: ' . Branch::count());
            $this->command->info('   - Students: ' . DB::table('students')->count());
            $this->command->info('   - Teachers: ' . DB::table('teachers')->count());
            $this->command->info('   - Parents: ' . DB::table('parents')->count());
            $this->command->info('   - Subjects: ' . Subject::count());

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            throw $e;
        }
    }

    private function createBranches()
    {
        $mainBranch = Branch::firstOrCreate(
            ['code' => 'MAIN001'],
            [
            'name' => 'Main Campus',
            'address' => '123 Education Street',
            'city' => 'New York',
            'state' => 'NY',
            'country' => 'USA',
            'pincode' => '10001',
            'phone' => '+1234567890',
            'email' => 'main@school.com',
            'principal_name' => 'Dr. John Smith',
            'is_main_branch' => true,
            'is_active' => true
        ]);

        // Create branch settings if table exists
        if (Schema::hasTable('branch_settings')) {
            DB::table('branch_settings')->insert([
                'branch_id' => $mainBranch->id,
                'setting_key' => 'academic_year',
                'setting_value' => '2024-2025',
                'setting_type' => 'string',
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        return $mainBranch;
    }

    private function createDepartments($branchId)
    {
        $departments = [
            ['name' => 'Science', 'head' => 'Science Head'],
            ['name' => 'Mathematics', 'head' => 'Math Head'],
            ['name' => 'Languages', 'head' => 'Language Head'],
            ['name' => 'Social Studies', 'head' => 'Social Head'],
        ];

        $created = [];
        foreach ($departments as $dept) {
            $created[] = Department::firstOrCreate(
                ['name' => $dept['name'], 'branch_id' => $branchId],
                [
                    'head' => $dept['head'],
                    'description' => $dept['name'] . ' Department',
                    'is_active' => true
                ]
            );
        }

        return collect($created);
    }

    private function createSubjects($departments)
    {
        $subjects = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'department' => 'Mathematics', 'grade' => '10'],
            ['name' => 'Physics', 'code' => 'PHY', 'department' => 'Science', 'grade' => '10'],
            ['name' => 'Chemistry', 'code' => 'CHEM', 'department' => 'Science', 'grade' => '10'],
            ['name' => 'English', 'code' => 'ENG', 'department' => 'Languages', 'grade' => '10'],
            ['name' => 'History', 'code' => 'HIST', 'department' => 'Social Studies', 'grade' => '10'],
        ];

        $created = [];
        $branchId = $departments->first()->branch_id;
        
        foreach ($subjects as $subj) {
            $dept = $departments->firstWhere('name', $subj['department']);
            
            $created[] = Subject::firstOrCreate(
                ['code' => $subj['code']],
                [
                    'name' => $subj['name'],
                    'department_id' => $dept->id,
                    'grade_level' => $subj['grade'],
                    'branch_id' => $branchId,
                    'description' => $subj['name'] . ' subject',
                    'is_active' => true
                ]
            );
        }

        return collect($created);
    }

    private function createAdmins($branchId)
    {
        // Create SuperAdmin
        $superAdminUser = User::firstOrCreate(
            ['email' => 'admin@school.com'],
            [
            'first_name' => 'Super',
            'last_name' => 'Admin',
            'email' => 'admin@school.com',
            'phone' => '9999999999',
            'password' => Hash::make('Admin@123'),
            'role' => 'SuperAdmin',
            'user_type' => 'Admin',
            'branch_id' => $branchId,
            'is_active' => true
        ]);

        DB::table('admins')->insert([
            'user_id' => $superAdminUser->id,
            'branch_id' => null,
            'admin_type' => 'SuperAdmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $superAdminUser->update(['user_type_id' => DB::getPdo()->lastInsertId()]);

        // Create Branch Admin
        $branchAdminUser = User::firstOrCreate(
            ['email' => 'branchadmin@school.com'],
            [
            'first_name' => 'Branch',
            'last_name' => 'Admin',
            'email' => 'branchadmin@school.com',
            'phone' => '9999999998',
            'password' => Hash::make('Admin@123'),
            'role' => 'BranchAdmin',
            'user_type' => 'Admin',
            'branch_id' => $branchId,
            'is_active' => true
        ]);

        DB::table('admins')->insert([
            'user_id' => $branchAdminUser->id,
            'branch_id' => $branchId,
            'admin_type' => 'BranchAdmin',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $branchAdminUser->update(['user_type_id' => DB::getPdo()->lastInsertId()]);
    }

    private function createTeachers($branchId)
    {
        $teachersData = [
            ['John', 'Anderson', 'john.anderson@teacher.com', '9876543210'],
            ['Mary', 'Williams', 'mary.williams@teacher.com', '9876543211'],
            ['Robert', 'Johnson', 'robert.johnson@teacher.com', '9876543212'],
        ];

        $teachers = [];

        foreach ($teachersData as $index => $teacherData) {
            // Create user
            $user = User::create([
                'first_name' => $teacherData[0],
                'last_name' => $teacherData[1],
                'email' => $teacherData[2],
                'phone' => $teacherData[3],
                'password' => Hash::make('Teacher@123'),
                'role' => 'Teacher',
                'user_type' => 'Teacher',
                'branch_id' => $branchId,
                'is_active' => true
            ]);

            // Create teacher record
            DB::table('teachers')->insert([
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'employee_id' => 'EMP' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'joining_date' => Carbon::now()->subYears(rand(1, 5)),
                'designation' => ['Senior Teacher', 'Teacher', 'Assistant Teacher'][rand(0, 2)],
                'employee_type' => 'Permanent',
                'subjects' => json_encode([1, 2]),
                'classes_assigned' => json_encode([['grade' => '10', 'sections' => ['A', 'B']]]),
                'is_class_teacher' => $index == 0,
                'date_of_birth' => Carbon::now()->subYears(rand(25, 45)),
                'gender' => ['Male', 'Female'][rand(0, 1)],
                'address' => $index + 100 . ' Teacher Lane, New York',
                'basic_salary' => rand(30000, 60000),
                'teacher_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $teacherId = DB::getPdo()->lastInsertId();
            $user->update(['user_type_id' => $teacherId]);
            $teachers[] = $teacherId;
        }

        return $teachers;
    }

    private function createParents()
    {
        $parentsData = [
            ['James', 'Doe', 'james.doe@parent.com', '9876500001'],
            ['Sarah', 'Smith', 'sarah.smith@parent.com', '9876500002'],
            ['Michael', 'Brown', 'michael.brown@parent.com', '9876500003'],
        ];

        $parents = [];

        foreach ($parentsData as $parentData) {
            // Create user
            $user = User::create([
                'first_name' => $parentData[0],
                'last_name' => $parentData[1],
                'email' => $parentData[2],
                'phone' => $parentData[3],
                'password' => Hash::make('Parent@123'),
                'role' => 'Parent',
                'user_type' => 'Parent',
                'is_active' => true
            ]);

            // Create parent record
            DB::table('parents')->insert([
                'user_id' => $user->id,
                'first_name' => $parentData[0],
                'last_name' => $parentData[1],
                'phone' => $parentData[3],
                'occupation' => ['Engineer', 'Doctor', 'Business'][rand(0, 2)],
                'can_pay_fees' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $parentId = DB::getPdo()->lastInsertId();
            $user->update(['user_type_id' => $parentId]);
            $parents[] = $user->id;
        }

        return $parents;
    }

    private function createStudents($branchId, $parents)
    {
        $studentsData = [
            ['John', 'Doe', 'john.doe@student.com', '9876000001', '10', 'A'],
            ['Alice', 'Smith', 'alice.smith@student.com', '9876000002', '10', 'A'],
            ['Bob', 'Brown', 'bob.brown@student.com', '9876000003', '10', 'B'],
            ['Emma', 'Wilson', 'emma.wilson@student.com', '9876000004', '9', 'A'],
            ['David', 'Martinez', 'david.martinez@student.com', '9876000005', '9', 'B'],
        ];

        $students = [];

        foreach ($studentsData as $index => $studentData) {
            // Create user
            $user = User::create([
                'first_name' => $studentData[0],
                'last_name' => $studentData[1],
                'email' => $studentData[2],
                'phone' => $studentData[3],
                'password' => Hash::make('Student@123'),
                'role' => 'Student',
                'user_type' => 'Student',
                'branch_id' => $branchId,
                'is_active' => true
            ]);

            // Create student record
            DB::table('students')->insert([
                'user_id' => $user->id,
                'branch_id' => $branchId,
                'admission_number' => 'ADM2024' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'admission_date' => Carbon::now()->subMonths(rand(1, 12)),
                'roll_number' => str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'grade' => $studentData[4],
                'section' => $studentData[5],
                'academic_year' => '2024-2025',
                'date_of_birth' => Carbon::now()->subYears(rand(14, 18)),
                'gender' => ['Male', 'Female'][rand(0, 1)],
                'blood_group' => ['A+', 'B+', 'O+', 'AB+'][rand(0, 3)],
                'current_address' => ($index + 1) . ' Student Street, New York, NY 10001',
                'city' => 'New York',
                'state' => 'NY',
                'pincode' => '10001',
                'parent_id' => $parents[array_rand($parents)],
                'father_name' => 'Father of ' . $studentData[0],
                'father_phone' => '98765' . str_pad($index, 5, '0', STR_PAD_LEFT),
                'mother_name' => 'Mother of ' . $studentData[0],
                'mother_phone' => '98766' . str_pad($index, 5, '0', STR_PAD_LEFT),
                'emergency_contact_name' => 'Emergency for ' . $studentData[0],
                'emergency_contact_phone' => '98767' . str_pad($index, 5, '0', STR_PAD_LEFT),
                'student_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $studentId = DB::getPdo()->lastInsertId();
            $user->update(['user_type_id' => $studentId]);
            $students[] = $studentId;
        }

        return $students;
    }

    private function createFeeStructures($branchId)
    {
        // Create fee types if table exists
        if (Schema::hasTable('fee_types')) {
            $feeTypes = [
                ['name' => 'Tuition Fee', 'code' => 'TUITION', 'is_mandatory' => true],
                ['name' => 'Transport Fee', 'code' => 'TRANSPORT', 'is_mandatory' => false],
                ['name' => 'Exam Fee', 'code' => 'EXAM', 'is_mandatory' => true],
                ['name' => 'Library Fee', 'code' => 'LIBRARY', 'is_mandatory' => false],
            ];

            foreach ($feeTypes as $type) {
                DB::table('fee_types')->insert(array_merge($type, [
                    'is_refundable' => false,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]));
            }
        }

        // Create fee structures
        $feeStructures = [
            [
                'name' => 'Grade 9 Fee Structure',
                'grade_level' => '9',
                'academic_year' => '2024-2025',
                'tuition_fee' => 12000,
                'exam_fee' => 1000,
                'library_fee' => 500,
                'transport_fee' => 2000,
                'total_amount' => 15500,
                'branch_id' => $branchId,
            ],
            [
                'name' => 'Grade 10 Fee Structure',
                'grade_level' => '10',
                'academic_year' => '2024-2025',
                'tuition_fee' => 15000,
                'exam_fee' => 1500,
                'library_fee' => 500,
                'transport_fee' => 2000,
                'total_amount' => 19000,
                'branch_id' => $branchId,
            ],
        ];

        foreach ($feeStructures as $fee) {
            DB::table('fee_structures')->insertOrIgnore(array_merge($fee, [
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]));
        }
    }

    private function createSampleData($students, $teachers, $subjects)
    {
        // Create student attendance (last 20 days)
        if (Schema::hasTable('student_attendance') && count($students) > 0) {
            foreach ($students as $studentId) {
                // Get student info
                $student = DB::table('students')->find($studentId);
                if (!$student) continue;
                
                for ($i = 0; $i < 20; $i++) {
                    $date = Carbon::now()->subDays($i);
                    
                    if (!$date->isWeekend()) {
                        try {
                            DB::table('student_attendance')->insert([
                                'student_id' => $student->user_id, // Uses user_id, not student.id
                                'grade_level' => $student->grade,
                                'section' => $student->section ?? 'A',
                                'date' => $date,
                                'status' => rand(1, 10) > 2 ? 'Present' : 'Absent',
                                'branch_id' => $student->branch_id,
                                'academic_year' => $student->academic_year,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        } catch (\Exception $e) {
                            // Skip if duplicate
                            continue;
                        }
                    }
                }
            }
        }

        $this->command->info('✅ Sample data created successfully (attendance records added)');
    }
}

