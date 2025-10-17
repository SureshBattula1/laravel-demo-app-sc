<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Event;
use App\Models\Holiday;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Subject;
use App\Models\Branch;
use App\Models\Department;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class DashboardDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing data (optional - comment out if you don't want to clear)
        // Attendance::truncate();
        // ExamResult::truncate();
        // Event::truncate();
        
        // Ensure we have branches
        $branch = Branch::first();
        if (!$branch) {
            $branch = Branch::create([
                'name' => 'Main Branch',
                'address' => '123 School Street',
                'phone' => '123-456-7890',
                'email' => 'main@school.com',
                'is_active' => true
            ]);
        }

        // Create test users if not exist
        $this->createTestUsers($branch->id);

        // Get students for seeding
        $students = User::where('role', 'Student')->get();
        $teachers = User::where('role', 'Teacher')->get();
        
        if ($students->isEmpty()) {
            $this->command->warn('No students found. Creating sample students...');
            $this->createStudents($branch->id);
            $students = User::where('role', 'Student')->get();
        }

        if ($teachers->isEmpty()) {
            $this->command->warn('No teachers found. Creating sample teachers...');
            $this->createTeachers($branch->id);
            $teachers = User::where('role', 'Teacher')->get();
        }

        // Create departments first
        $departments = $this->createDepartments($branch->id);

        // Create subjects with departments
        $subjects = $this->createSubjects($departments);

        // Seed Attendance Data
        // Note: Skipping attendance for now as the table structure is complex
        // $this->seedAttendance($students);

        // Seed Exam Results  
        // $this->seedExamResults($students, $subjects, $teachers->first());

        // Seed Events
        // $this->seedEvents($branch->id);

        // Seed Fee Payments
        // $this->seedFeePayments($students, $branch->id);

        $this->command->info('Dashboard data seeded successfully!');
    }

    private function createTestUsers($branchId)
    {
        // Create Super Admin if not exists
        if (!User::where('email', 'admin@school.com')->exists()) {
            User::create([
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'admin@school.com',
                'phone' => '1234567890',
                'password' => Hash::make('password'),
                'role' => 'SuperAdmin',
                'branch_id' => $branchId,
                'is_active' => true
            ]);
        }

        // Create Branch Admin if not exists
        if (!User::where('email', 'branchadmin@school.com')->exists()) {
            User::create([
                'first_name' => 'Branch',
                'last_name' => 'Admin',
                'email' => 'branchadmin@school.com',
                'phone' => '1234567891',
                'password' => Hash::make('password'),
                'role' => 'BranchAdmin',
                'branch_id' => $branchId,
                'is_active' => true
            ]);
        }
    }

    private function createStudents($branchId)
    {
        $students = [
            ['John', 'Smith', 'john.smith@student.com'],
            ['Sarah', 'Johnson', 'sarah.johnson@student.com'],
            ['Mike', 'Brown', 'mike.brown@student.com'],
            ['Emily', 'Davis', 'emily.davis@student.com'],
            ['David', 'Wilson', 'david.wilson@student.com'],
            ['Alice', 'Cooper', 'alice.cooper@student.com'],
            ['Bob', 'Martin', 'bob.martin@student.com'],
            ['Carol', 'White', 'carol.white@student.com'],
            ['Tom', 'Hardy', 'tom.hardy@student.com'],
            ['Lisa', 'Ray', 'lisa.ray@student.com'],
        ];

        foreach ($students as $index => $student) {
            User::create([
                'first_name' => $student[0],
                'last_name' => $student[1],
                'email' => $student[2],
                'phone' => '555000' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'Student',
                'branch_id' => $branchId,
                'is_active' => true
            ]);
        }
    }

    private function createTeachers($branchId)
    {
        $teachers = [
            ['James', 'Anderson', 'james.anderson@teacher.com'],
            ['Mary', 'Taylor', 'mary.taylor@teacher.com'],
        ];

        foreach ($teachers as $index => $teacher) {
            User::create([
                'first_name' => $teacher[0],
                'last_name' => $teacher[1],
                'email' => $teacher[2],
                'phone' => '555100' . str_pad($index + 1, 4, '0', STR_PAD_LEFT),
                'password' => Hash::make('password'),
                'role' => 'Teacher',
                'branch_id' => $branchId,
                'is_active' => true
            ]);
        }
    }

    private function createDepartments($branchId)
    {
        $this->command->info('Creating departments...');

        $departments = [
            ['Science', 'Science Department', 'Dr. Science Head'],
            ['Arts', 'Arts and Humanities Department', 'Dr. Arts Head'],
            ['Commerce', 'Commerce Department', 'Dr. Commerce Head'],
        ];

        $createdDepartments = [];

        foreach ($departments as $dept) {
            $department = Department::firstOrCreate(
                [
                    'name' => $dept[0],
                    'branch_id' => $branchId
                ],
                [
                    'head' => $dept[2],
                    'description' => $dept[1],
                    'is_active' => true,
                    'established_date' => now()->subYears(5)
                ]
            );
            $createdDepartments[] = $department;
        }

        return $createdDepartments;
    }

    private function createSubjects($departments)
    {
        $this->command->info('Creating subjects...');

        if (empty($departments)) {
            $this->command->error('No departments found. Cannot create subjects.');
            return [];
        }

        // Get first branch for subjects
        $branch = Branch::first();
        if (!$branch) {
            $this->command->error('No branch found. Cannot create subjects.');
            return [];
        }

        // Map subjects to departments
        $subjectsData = [
            ['Mathematics', 'Science', '10'],
            ['Physics', 'Science', '11'],
            ['Chemistry', 'Science', '11'],
            ['Biology', 'Science', '12'],
            ['English', 'Arts', '10'],
            ['History', 'Arts', '10'],
        ];

        $subjects = [];

        foreach ($subjectsData as $subjectData) {
            $subjectName = $subjectData[0];
            $departmentName = $subjectData[1];
            $gradeLevel = $subjectData[2];

            // Find the department
            $department = collect($departments)->firstWhere('name', $departmentName);

            if (!$department) {
                $this->command->warn("Department {$departmentName} not found. Skipping {$subjectName}.");
                continue;
            }

            $subject = Subject::firstOrCreate(
                ['name' => $subjectName],
                [
                    'code' => strtoupper(substr($subjectName, 0, 3)),
                    'description' => $subjectName . ' subject',
                    'department_id' => $department->id,
                    'branch_id' => $branch->id,
                    'grade_level' => $gradeLevel,
                    'is_active' => true
                ]
            );
            $subjects[] = $subject;
        }

        return $subjects;
    }

    private function seedAttendance($students)
    {
        $this->command->info('Seeding attendance data...');

        // Get branch
        $branch = Branch::first();
        if (!$branch) {
            $this->command->warn('No branch found. Skipping attendance seeding.');
            return;
        }

        foreach ($students as $student) {
            // Generate attendance for last 30 days
            for ($i = 0; $i < 30; $i++) {
                $date = Carbon::now()->subDays($i);
                
                // Skip weekends
                if ($date->isWeekend()) {
                    continue;
                }

                // Random attendance status (90% present, 5% absent, 5% late)
                $rand = rand(1, 100);
                $status = 'present';
                if ($rand <= 5) {
                    $status = 'absent';
                } elseif ($rand <= 10) {
                    $status = 'late';
                }

                $checkInTime = $status === 'late' ? '08:30:00' : '08:00:00';

                Attendance::create([
                    'user_id' => $student->id,
                    'user_type' => 'Student',
                    'branch_id' => $branch->id,
                    'attendance_date' => $date->toDateString(),
                    'status' => $status,
                    'check_in_time' => $status !== 'absent' ? $date->setTimeFromTimeString($checkInTime) : null,
                    'check_out_time' => $status !== 'absent' ? $date->setTimeFromTimeString('15:00:00') : null,
                    'remarks' => $status === 'absent' ? 'Not present' : null
                ]);
            }
        }

        // Create some students with low attendance
        $lowAttendanceStudents = $students->take(3);
        foreach ($lowAttendanceStudents as $student) {
            // Mark some as absent to reduce attendance
            Attendance::where('user_id', $student->id)
                ->where('status', 'present')
                ->limit(10)
                ->update(['status' => 'absent', 'check_in_time' => null, 'check_out_time' => null]);
        }
    }

    private function seedExamResults($students, $subjects, $teacher)
    {
        $this->command->info('Seeding exam results...');

        foreach ($subjects as $subject) {
            // Create exam
            $exam = Exam::create([
                'name' => $subject->name . ' Midterm Exam',
                'subject_id' => $subject->id,
                'exam_type' => 'Midterm',
                'exam_date' => Carbon::now()->subDays(rand(5, 15))->toDateString(),
                'exam_time' => '09:00:00',
                'duration' => 120,
                'total_marks' => 100,
                'passing_marks' => 40,
                'created_by' => $teacher ? $teacher->id : 1
            ]);

            // Create results for all students
            foreach ($students as $student) {
                // Generate random marks (60-99 for most, 40-60 for some)
                $marks = rand(1, 10) > 2 ? rand(75, 99) : rand(50, 74);
                
                ExamResult::create([
                    'exam_id' => $exam->id,
                    'student_id' => $student->id,
                    'marks' => $marks,
                    'grade' => $this->calculateGrade($marks),
                    'remarks' => $this->getRemarks($marks)
                ]);
            }
        }

        // Create upcoming exams
        foreach ($subjects->take(3) as $index => $subject) {
            Exam::create([
                'name' => $subject->name . ' Final Exam',
                'subject_id' => $subject->id,
                'exam_type' => 'Final',
                'exam_date' => Carbon::now()->addDays(10 + $index * 2)->toDateString(),
                'exam_time' => '10:00:00',
                'duration' => 180,
                'total_marks' => 100,
                'passing_marks' => 40,
                'created_by' => $teacher ? $teacher->id : 1
            ]);
        }
    }

    private function seedEvents($branchId)
    {
        $this->command->info('Seeding events...');

        $events = [
            ['Annual Sports Day', 'Sports', Carbon::now()->addDays(15)],
            ['Science Exhibition', 'Academic', Carbon::now()->addDays(20)],
            ['Cultural Fest', 'Cultural', Carbon::now()->addDays(25)],
            ['Parent-Teacher Meeting', 'Meeting', Carbon::now()->addDays(10)],
            ['Annual Day Celebration', 'Celebration', Carbon::now()->addDays(30)],
        ];

        foreach ($events as $event) {
            Event::create([
                'title' => $event[0],
                'type' => $event[1],
                'event_date' => $event[2]->toDateString(),
                'start_time' => '09:00:00',
                'end_time' => '16:00:00',
                'venue' => 'School Auditorium',
                'description' => 'Annual ' . $event[0] . ' event',
                'branch_id' => $branchId,
                'is_active' => true
            ]);
        }

        // Holidays
        $holidays = [
            ['Independence Day', Carbon::now()->addDays(5)],
            ['Teachers Day', Carbon::now()->addDays(12)],
            ['Christmas Vacation', Carbon::now()->addDays(40)],
        ];

        foreach ($holidays as $holiday) {
            Holiday::create([
                'name' => $holiday[0],
                'date' => $holiday[1]->toDateString(),
                'description' => 'Public holiday - ' . $holiday[0],
                'is_active' => true
            ]);
        }
    }

    private function seedFeePayments($students, $branchId)
    {
        $this->command->info('Seeding fee payments...');

        // Create fee structure
        $feeStructure = FeeStructure::firstOrCreate(
            ['name' => 'Tuition Fee'],
            [
                'amount' => 5000,
                'type' => 'Monthly',
                'branch_id' => $branchId,
                'is_active' => true
            ]
        );

        foreach ($students as $student) {
            // Paid fees (70% of students)
            if (rand(1, 10) <= 7) {
                FeePayment::create([
                    'student_id' => $student->id,
                    'fee_structure_id' => $feeStructure->id,
                    'amount' => 5000,
                    'payment_date' => Carbon::now()->subDays(rand(1, 30))->toDateString(),
                    'payment_method' => 'Cash',
                    'status' => 'paid',
                    'branch_id' => $branchId
                ]);
            } else {
                // Pending fees
                FeePayment::create([
                    'student_id' => $student->id,
                    'fee_structure_id' => $feeStructure->id,
                    'amount' => 5000,
                    'payment_date' => null,
                    'payment_method' => null,
                    'status' => 'pending',
                    'branch_id' => $branchId
                ]);
            }
        }
    }

    private function calculateGrade($marks)
    {
        if ($marks >= 90) return 'A+';
        if ($marks >= 80) return 'A';
        if ($marks >= 70) return 'B+';
        if ($marks >= 60) return 'B';
        if ($marks >= 50) return 'C';
        return 'F';
    }

    private function getRemarks($marks)
    {
        if ($marks >= 90) return 'Outstanding';
        if ($marks >= 80) return 'Excellent';
        if ($marks >= 70) return 'Very Good';
        if ($marks >= 60) return 'Good';
        if ($marks >= 50) return 'Average';
        return 'Needs Improvement';
    }
}

