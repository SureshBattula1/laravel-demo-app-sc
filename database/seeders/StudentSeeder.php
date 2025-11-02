<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Student;
use App\Models\Branch;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class StudentSeeder extends Seeder
{
    private $firstNames = [
        'male' => ['Aarav', 'Vivaan', 'Aditya', 'Vihaan', 'Arjun', 'Sai', 'Arnav', 'Ayaan', 'Krishna', 'Ishaan', 
                   'Shaurya', 'Atharva', 'Advik', 'Pranav', 'Reyansh', 'Aadhya', 'Kabir', 'Shivansh', 'Ansh', 'Daksh',
                   'Rohan', 'Aayan', 'Rayan', 'Ayush', 'Dhruv', 'Yash', 'Rudra', 'Kian', 'Advait', 'Vedant'],
        'female' => ['Aadhya', 'Ananya', 'Pari', 'Anika', 'Navya', 'Angel', 'Diya', 'Myra', 'Sara', 'Ira',
                     'Saanvi', 'Aaradhya', 'Avni', 'Kavya', 'Kiara', 'Riya', 'Shanaya', 'Zara', 'Ishita', 'Aditi',
                     'Prisha', 'Anvi', 'Siya', 'Aanya', 'Divya', 'Nisha', 'Tara', 'Mira', 'Sia', 'Ahana']
    ];

    private $lastNames = ['Sharma', 'Verma', 'Patel', 'Kumar', 'Singh', 'Gupta', 'Reddy', 'Shah', 'Joshi', 'Mehta',
                         'Nair', 'Pillai', 'Rao', 'Iyer', 'Menon', 'Desai', 'Kulkarni', 'Agarwal', 'Bansal', 'Jain',
                         'Malhotra', 'Kapoor', 'Chopra', 'Mishra', 'Pandey', 'Saxena', 'Sinha', 'Bhatia', 'Khanna', 'Sethi'];

    private $cities = ['Mumbai', 'Delhi', 'Bangalore', 'Hyderabad', 'Chennai', 'Kolkata', 'Pune', 'Ahmedabad', 'Jaipur', 'Lucknow'];
    private $states = ['Maharashtra', 'Delhi', 'Karnataka', 'Telangana', 'Tamil Nadu', 'West Bengal', 'Gujarat', 'Rajasthan', 'Uttar Pradesh'];
    private $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    private $religions = ['Hindu', 'Muslim', 'Christian', 'Sikh', 'Jain', 'Buddhist'];
    private $sections = ['A', 'B', 'C', 'D'];
    
    // Grade configuration with age ranges
    private $gradeConfig = [
        'PlaySchool' => ['min_age' => 2, 'max_age' => 3, 'students' => 8],
        'Nursery' => ['min_age' => 3, 'max_age' => 4, 'students' => 8],
        'LKG' => ['min_age' => 4, 'max_age' => 5, 'students' => 10],
        'UKG' => ['min_age' => 5, 'max_age' => 6, 'students' => 10],
        '1' => ['min_age' => 6, 'max_age' => 7, 'students' => 12],
        '2' => ['min_age' => 7, 'max_age' => 8, 'students' => 12],
        '3' => ['min_age' => 8, 'max_age' => 9, 'students' => 12],
        '4' => ['min_age' => 9, 'max_age' => 10, 'students' => 12],
        '5' => ['min_age' => 10, 'max_age' => 11, 'students' => 12],
        '6' => ['min_age' => 11, 'max_age' => 12, 'students' => 14],
        '7' => ['min_age' => 12, 'max_age' => 13, 'students' => 14],
        '8' => ['min_age' => 13, 'max_age' => 14, 'students' => 14],
        '9' => ['min_age' => 14, 'max_age' => 15, 'students' => 16],
        '10' => ['min_age' => 15, 'max_age' => 16, 'students' => 16],
        '11' => ['min_age' => 16, 'max_age' => 17, 'students' => 10],
        '12' => ['min_age' => 17, 'max_age' => 18, 'students' => 10],
    ];

    public function run(): void
    {
        $this->command->info('👨‍🎓 Starting Student Seeder...');
        $this->command->info(str_repeat('=', 60));
        
        // Ensure at least one branch exists
        $branch = Branch::first();
        if (!$branch) {
            $this->command->error('❌ No branches found! Please run BranchSeeder first.');
            return;
        }

        // Get Student role
        $studentRole = Role::where('name', 'Student')->first();
        if (!$studentRole) {
            $this->command->error('❌ Student role not found! Please run PermissionSeeder first.');
            return;
        }

        $this->command->info('✅ Branch: ' . $branch->name);
        $this->command->info('✅ Student Role ID: ' . $studentRole->id);
        $this->command->info('');

        $totalCreated = 0;
        $totalSkipped = 0;

        // Create students for each grade
        foreach ($this->gradeConfig as $grade => $config) {
            $this->command->info("📚 Creating students for Grade: {$grade}");
            
            $studentsToCreate = $config['students'];
            $studentsPerSection = ceil($studentsToCreate / count($this->sections));
            
            foreach ($this->sections as $section) {
                $sectionStudents = min($studentsPerSection, $studentsToCreate - $totalCreated % $studentsToCreate);
                
                for ($i = 0; $i < $sectionStudents; $i++) {
                    $result = $this->createStudent($branch, $studentRole, $grade, $section, $config);
                    if ($result) {
                        $totalCreated++;
                    } else {
                        $totalSkipped++;
                    }
                }
            }
        }

        $this->command->info('');
        $this->command->info(str_repeat('=', 60));
        $this->command->info("✅ Students Created: {$totalCreated}");
        $this->command->info("⏭️  Students Skipped: {$totalSkipped}");
        $this->command->info(str_repeat('=', 60));
    }

    private function createStudent(Branch $branch, Role $studentRole, string $grade, string $section, array $config): bool
    {
        try {
            // Generate student details
            $gender = rand(0, 1) ? 'Male' : 'Female';
            $firstName = $this->firstNames[$gender === 'Male' ? 'male' : 'female'][array_rand($this->firstNames[$gender === 'Male' ? 'male' : 'female'])];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            $email = strtolower($firstName . '.' . $lastName . rand(1, 999)) . '@student.school.com';
            
            // Check if email already exists
            if (User::where('email', $email)->exists()) {
                return false;
            }

            // Generate admission number
            $admissionNumber = 'STU-' . strtoupper($branch->code ?? 'MAIN') . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            // Check if admission number exists
            if (Student::where('admission_number', $admissionNumber)->exists()) {
                return false;
            }

            // Calculate age-appropriate DOB
            $age = rand($config['min_age'], $config['max_age']);
            $dob = now()->subYears($age)->subDays(rand(1, 365))->format('Y-m-d');

            DB::beginTransaction();

            // Create User
            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => '9' . rand(100000000, 999999999),
                'password' => Hash::make('password123'),
                'role' => 'Student',
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            // Assign Student role via pivot table
            $user->roles()->attach($studentRole->id);

            // Create Student
            $student = Student::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'admission_number' => $admissionNumber,
                'admission_date' => now()->subDays(rand(30, 365))->format('Y-m-d'),
                'roll_number' => str_pad(rand(1, 50), 2, '0', STR_PAD_LEFT),
                'grade' => $grade,
                'section' => $section,
                'academic_year' => (date('Y') - 1) . '-' . date('Y'),
                
                // Personal Information
                'date_of_birth' => $dob,
                'gender' => $gender,
                'blood_group' => $this->bloodGroups[array_rand($this->bloodGroups)],
                'religion' => $this->religions[array_rand($this->religions)],
                'nationality' => 'Indian',
                
                // Address Information
                'current_address' => $this->getRandomAddress(),
                'permanent_address' => $this->getRandomAddress(),
                'city' => $this->cities[array_rand($this->cities)],
                'state' => $this->states[array_rand($this->states)],
                'pincode' => rand(100000, 999999),
                
                // Parent/Guardian Information
                'father_name' => $this->getRandomParentName('male'),
                'father_phone' => '9' . rand(100000000, 999999999),
                'father_email' => strtolower($lastName) . '.father' . rand(1, 999) . '@parent.com',
                'father_occupation' => $this->getRandomOccupation(),
                
                'mother_name' => $this->getRandomParentName('female'),
                'mother_phone' => '9' . rand(100000000, 999999999),
                'mother_email' => strtolower($lastName) . '.mother' . rand(1, 999) . '@parent.com',
                'mother_occupation' => $this->getRandomOccupation(),
                
                // Emergency Contact
                'emergency_contact_name' => $this->getRandomParentName($gender === 'Male' ? 'female' : 'male'),
                'emergency_contact_phone' => '9' . rand(100000000, 999999999),
                'emergency_contact_relation' => rand(0, 1) ? 'Father' : 'Mother',
                
                // Academic Information
                'previous_school' => rand(0, 1) ? $this->getRandomSchoolName() : null,
                'previous_grade' => null,
                
                // Medical Information
                'medical_history' => rand(0, 10) > 8 ? $this->getRandomMedicalCondition() : null,
                'allergies' => rand(0, 10) > 7 ? $this->getRandomAllergy() : null,
                
                // Status
                'student_status' => 'Active',
            ]);

            DB::commit();

            $this->command->info("  ✓ {$firstName} {$lastName} - Grade {$grade}-{$section} (Age: {$age})");
            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("  ✗ Failed to create student: " . $e->getMessage());
            return false;
        }
    }

    private function getRandomAddress(): string
    {
        $houseNo = rand(1, 999);
        $streets = ['MG Road', 'Park Street', 'Main Road', 'Station Road', 'Gandhi Nagar', 'Nehru Street', 'Market Road'];
        $areas = ['Sector', 'Block', 'Phase', 'Extension', 'Colony', 'Nagar'];
        
        return "{$houseNo}, {$streets[array_rand($streets)]}, {$areas[array_rand($areas)]} " . rand(1, 20);
    }

    private function getRandomParentName(string $gender): string
    {
        $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
        $lastName = $this->lastNames[array_rand($this->lastNames)];
        return $firstName . ' ' . $lastName;
    }

    private function getRandomOccupation(): string
    {
        $occupations = [
            'Software Engineer', 'Doctor', 'Teacher', 'Business Owner', 'Accountant',
            'Manager', 'Consultant', 'Engineer', 'Architect', 'Lawyer',
            'Banker', 'Salesperson', 'Entrepreneur', 'Government Employee', 'Pharmacist'
        ];
        return $occupations[array_rand($occupations)];
    }

    private function getRandomSchoolName(): string
    {
        $schools = [
            'St. Mary\'s School',
            'Delhi Public School',
            'Modern School',
            'Ryan International',
            'DAV Public School',
            'Kendriya Vidyalaya'
        ];
        return $schools[array_rand($schools)];
    }

    private function getRandomMedicalCondition(): string
    {
        $conditions = ['Asthma', 'Diabetes', 'Epilepsy', 'Heart Condition', 'None'];
        return $conditions[array_rand($conditions)];
    }

    private function getRandomAllergy(): string
    {
        $allergies = ['Peanuts', 'Dust', 'Pollen', 'Milk Products', 'Eggs', 'None'];
        return $allergies[array_rand($allergies)];
    }
}

