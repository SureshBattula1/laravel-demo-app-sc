<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Teacher;
use Carbon\Carbon;

class TeacherSeeder extends Seeder
{
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
    
    private $subjects = [
        'Mathematics', 'English', 'Science', 'Physics', 'Chemistry', 'Biology', 
        'History', 'Geography', 'Computer Science', 'Physical Education', 
        'Music', 'Art', 'Social Studies', 'Economics', 'Business Studies',
        'Literature', 'Environmental Science', 'Political Science', 'Psychology', 'Sociology'
    ];
    
    private $bloodGroups = ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'];
    private $nationalities = ['American', 'Canadian', 'British', 'Indian', 'Australian'];
    
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🚀 Starting Teacher Seeding...');
            
            // Get all active branches
            $branches = Branch::active()->get();
            
            if ($branches->isEmpty()) {
                $this->command->warn('⚠️ No branches found. Please seed branches first.');
                DB::rollBack();
                return;
            }
            
            $totalTeachers = 0;
            
            foreach ($branches as $branch) {
                $this->command->info("📍 Creating teachers for: {$branch->name}");
                
                $teachersCreated = $this->createTeachersForBranch($branch);
                $totalTeachers += $teachersCreated;
                
                $this->command->info("  ✓ Created {$teachersCreated} teachers for {$branch->name}");
            }
            
            DB::commit();
            
            $this->command->info('');
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info('✅ Teacher Seeding Completed Successfully!');
            $this->command->info('═══════════════════════════════════════════════════════');
            $this->command->info("📊 Summary:");
            $this->command->info("   🏢 Branches: " . $branches->count());
            $this->command->info("   👨‍🏫 Total Teachers Created: {$totalTeachers}");
            $this->command->info("   📧 Teachers per Branch: 20");
            $this->command->info('');
            $this->command->info('🔑 Default Password: Teacher@123');
            $this->command->info('═══════════════════════════════════════════════════════');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Seeding failed: ' . $e->getMessage());
            $this->command->error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }
    
    /**
     * Create 20 teachers for a specific branch
     */
    private function createTeachersForBranch(Branch $branch): int
    {
        $designations = ['Senior Teacher', 'Teacher', 'Junior Teacher', 'PGT (Post Graduate Teacher)', 
                        'TGT (Trained Graduate Teacher)', 'PRT (Primary Teacher)', 'Subject Head', 
                        'Assistant Teacher', 'Lead Teacher', 'Master Teacher'];
        
        $qualifications = ['B.Ed', 'M.Ed', 'B.A. B.Ed', 'M.A. B.Ed', 'M.Sc. B.Ed', 
                          'Ph.D. in Education', 'B.Sc. B.Ed', 'M.Phil. Education'];
        
        $categoryTypes = ['Teaching', 'Teaching', 'Teaching', 'Non-Teaching']; // 75% teaching
        
        $employeeTypes = ['Permanent', 'Contract']; // Only use values that definitely work
        
        $departments = Department::where('branch_id', $branch->id)->get();
        
        $teachersCreated = 0;
        
        for ($i = 1; $i <= 20; $i++) {
            $gender = rand(0, 1) ? 'Male' : 'Female';
            $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
            $lastName = $this->lastNames[array_rand($this->lastNames)];
            
            // Create unique email
            $emailBase = strtolower($firstName . '.' . $lastName);
            $email = $emailBase . '.' . $branch->id . '.' . $i . '@excellenceacademy.com';
            
            // Check if user already exists with this email
            $existingUser = User::where('email', $email)->first();
            if ($existingUser) {
                // User exists, check if teacher record exists
                $existingTeacher = Teacher::where('user_id', $existingUser->id)->first();
                if ($existingTeacher) {
                    continue; // Skip if teacher already exists
                }
                $user = $existingUser;
            } else {
                // Create User
                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => '+1-' . rand(200, 999) . '-' . rand(200, 999) . '-' . rand(1000, 9999),
                    'password' => Hash::make('Teacher@123'),
                    'role' => 'Teacher',
                    'user_type' => 'Teacher',
                    'branch_id' => $branch->id,
                    'is_active' => true,
                    'email_verified_at' => now()
                ]);
            }
            
            // Generate employee ID
            $employeeId = 'EMP-' . $branch->code . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
            
            // Random department
            $department = $departments->isNotEmpty() ? $departments->random() : null;
            
            // Random subjects (2-4 subjects)
            $teacherSubjects = $this->getRandomSubjects();
            
            // Random joining date (1 month to 10 years ago)
            $joiningDate = Carbon::now()->subMonths(rand(1, 120));
            
            // Random date of birth (25-60 years old)
            $dateOfBirth = Carbon::now()->subYears(rand(25, 60))->subMonths(rand(0, 11));
            
            // Random salary based on experience
            $experienceYears = Carbon::now()->diffInYears($joiningDate);
            $baseSalary = rand(30000, 50000) + ($experienceYears * rand(2000, 5000));
            
            // Create Teacher Record - only using columns that exist in the database
            $currentAddress = rand(100, 9999) . ' ' . $this->getRandomStreet();
            $permanentAddress = rand(100, 9999) . ' ' . $this->getRandomStreet();
            
            // Create Teacher Record - Using the same minimal approach as DemoDataSeeder
            $teacher = Teacher::create([
                'user_id' => $user->id,
                'branch_id' => $branch->id,
                'employee_id' => $employeeId,
                'category_type' => $categoryTypes[array_rand($categoryTypes)],
                'designation' => $designations[array_rand($designations)],
                'employee_type' => $employeeTypes[array_rand($employeeTypes)],
                'joining_date' => $joiningDate->format('Y-m-d'),
                'date_of_birth' => $dateOfBirth->format('Y-m-d'),
                'gender' => $gender,
                'address' => $currentAddress,
                'basic_salary' => $baseSalary,
                'subjects' => $teacherSubjects,
                'classes_assigned' => [],
                'is_class_teacher' => false,
                'teacher_status' => 'Active',
            ]);
            
            $teachersCreated++;
        }
        
        return $teachersCreated;
    }
    
    /**
     * Get random subjects for a teacher
     */
    private function getRandomSubjects(): array
    {
        shuffle($this->subjects);
        return array_slice($this->subjects, 0, rand(2, 4));
    }
    
    /**
     * Generate random street address
     */
    private function getRandomStreet(): string
    {
        $streets = ['Main St', 'Oak Ave', 'Maple Dr', 'Park Blvd', 'Washington St', 
                   'Lake View Rd', 'Hill St', 'Cedar Ln', 'Elm St', 'Pine Ave',
                   'Broadway', 'First Street', 'Second Avenue', 'Lincoln Way', 'Madison Drive'];
        return $streets[array_rand($streets)];
    }
    
    /**
     * Generate random bank account number
     */
    private function generateBankAccount(): string
    {
        return str_pad(rand(1000000000, 9999999999), 12, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get random bank name
     */
    private function getRandomBankName(): string
    {
        $banks = ['Chase Bank', 'Bank of America', 'Wells Fargo', 'Citibank', 'US Bank',
                 'PNC Bank', 'Capital One', 'TD Bank', 'Citizens Bank', 'HSBC'];
        return $banks[array_rand($banks)];
    }
    
    /**
     * Generate random IFSC code
     */
    private function generateIFSC(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= $letters[rand(0, 25)];
        }
        $code .= '0' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT);
        return $code;
    }
    
    /**
     * Generate random PAN number
     */
    private function generatePAN(): string
    {
        $letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $pan = '';
        for ($i = 0; $i < 5; $i++) {
            $pan .= $letters[rand(0, 25)];
        }
        $pan .= str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
        $pan .= $letters[rand(0, 25)];
        return $pan;
    }
    
    /**
     * Generate random Aadhaar number
     */
    private function generateAadhaar(): string
    {
        return str_pad(rand(100000000000, 999999999999), 12, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get random name
     */
    private function getRandomName($gender = null): string
    {
        if (!$gender) {
            $gender = rand(0, 1) ? 'Male' : 'Female';
        }
        $firstName = $this->firstNames[$gender][array_rand($this->firstNames[$gender])];
        $lastName = $this->lastNames[array_rand($this->lastNames)];
        return $firstName . ' ' . $lastName;
    }
    
    /**
     * Get random certifications
     */
    private function getRandomCertifications(): array
    {
        $certifications = [
            'TEFL Certification',
            'Advanced Pedagogy Certificate',
            'Technology Integration in Education',
            'Classroom Management Certification',
            'Special Education Training',
            'Educational Leadership Certificate'
        ];
        
        shuffle($certifications);
        return array_slice($certifications, 0, rand(1, 3));
    }
    
    /**
     * Get random technical skills
     */
    private function getRandomTechnicalSkills(): array
    {
        $skills = [
            'Microsoft Office',
            'Google Classroom',
            'Learning Management Systems',
            'Educational Technology Tools',
            'Smart Board Operations',
            'Video Conferencing',
            'Online Assessment Tools',
            'Content Management Systems'
        ];
        
        shuffle($skills);
        return array_slice($skills, 0, rand(3, 6));
    }
}

