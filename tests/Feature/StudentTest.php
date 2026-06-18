<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Student;
use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a branch for testing
        $this->branch = $this->createBranch();
    }

    /** @test */
    public function test_can_create_student_with_core_fields()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        $studentData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john.doe@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001',
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'section' => 'A',
            'academic_year' => '2025-2026',
            'date_of_birth' => '2010-05-15',
            'gender' => 'Male',
            'current_address' => '123 Main St',
            'city' => 'Test City',
            'state' => 'Test State',
            'pincode' => '123456',
            'father_name' => 'Father Doe',
            'father_phone' => '9876543210',
            'mother_name' => 'Mother Doe',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '5555555555',
        ];

        $response = $this->postJson('/api/students', $studentData);

        $response->assertStatus(201);
        $this->assertSuccessResponse($response, 201);
        
        $this->assertDatabaseHas('users', [
            'email' => 'john.doe@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        $this->assertDatabaseHas('students', [
            'admission_number' => 'ADM001',
            'grade' => '5',
        ]);
    }

    /** @test */
    public function test_cannot_create_student_without_required_fields()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        $studentData = [
            'first_name' => 'John',
            // Missing required fields
        ];

        $response = $this->postJson('/api/students', $studentData);

        $this->assertValidationError($response);
        $response->assertJsonValidationErrors(['last_name', 'email', 'password', 'branch_id', 'admission_number']);
    }

    /** @test */
    public function test_cannot_create_student_with_duplicate_email()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        // Create first student
        User::factory()->create(['email' => 'duplicate@example.com']);

        $studentData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'duplicate@example.com', // Duplicate email
            'phone' => '1234567890',
            'password' => 'password123',
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001',
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'academic_year' => '2025-2026',
            'date_of_birth' => '2010-05-15',
            'gender' => 'Male',
            'current_address' => '123 Main St',
            'city' => 'Test City',
            'state' => 'Test State',
            'pincode' => '123456',
            'father_name' => 'Father Doe',
            'father_phone' => '9876543210',
            'mother_name' => 'Mother Doe',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '5555555555',
        ];

        $response = $this->postJson('/api/students', $studentData);

        $this->assertValidationError($response, 'email');
    }

    /** @test */
    public function test_cannot_create_student_with_duplicate_admission_number()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        // Create first student
        $user1 = User::factory()->create();
        Student::create([
            'user_id' => $user1->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001',
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'academic_year' => '2025-2026',
        ]);

        $studentData = [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane.doe@example.com',
            'phone' => '1234567890',
            'password' => 'password123',
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001', // Duplicate admission number
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'academic_year' => '2025-2026',
            'date_of_birth' => '2010-05-15',
            'gender' => 'Female',
            'current_address' => '123 Main St',
            'city' => 'Test City',
            'state' => 'Test State',
            'pincode' => '123456',
            'father_name' => 'Father Doe',
            'father_phone' => '9876543210',
            'mother_name' => 'Mother Doe',
            'emergency_contact_name' => 'Emergency Contact',
            'emergency_contact_phone' => '5555555555',
        ];

        $response = $this->postJson('/api/students', $studentData);

        $this->assertValidationError($response, 'admission_number');
    }

    /** @test */
    public function test_can_retrieve_student()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        // Create a student
        $studentUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
        ]);
        
        $student = Student::create([
            'user_id' => $studentUser->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001',
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'academic_year' => '2025-2026',
        ]);

        $response = $this->getJson("/api/students/{$student->id}");

        $response->assertStatus(200);
        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.admission_number', 'ADM001');
    }

    /** @test */
    public function test_can_list_students()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        // Create multiple students
        for ($i = 1; $i <= 3; $i++) {
            $studentUser = User::factory()->create([
                'role' => 'Student',
                'branch_id' => $this->branch->id,
            ]);
            
            Student::create([
                'user_id' => $studentUser->id,
                'branch_id' => $this->branch->id,
                'admission_number' => "ADM00{$i}",
                'admission_date' => '2025-01-01',
                'grade' => '5',
                'academic_year' => '2025-2026',
            ]);
        }

        $response = $this->getJson('/api/students');

        $response->assertStatus(200);
        $this->assertSuccessResponse($response);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'admission_number',
                    'grade',
                ]
            ]
        ]);
    }

    /** @test */
    public function test_can_update_student()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        // Create a student
        $studentUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);
        
        $student = Student::create([
            'user_id' => $studentUser->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001',
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'academic_year' => '2025-2026',
        ]);

        $updateData = [
            'first_name' => 'Jane',
            'grade' => '6',
            'section' => 'B',
        ];

        $response = $this->putJson("/api/students/{$student->id}", $updateData);

        $response->assertStatus(200);
        $this->assertSuccessResponse($response);
        
        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'grade' => '6',
            'section' => 'B',
        ]);
    }

    /** @test */
    public function test_can_delete_student()
    {
        $user = $this->actingAsUser('BranchAdmin', ['branch_id' => $this->branch->id]);

        // Create a student
        $studentUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
        ]);
        
        $student = Student::create([
            'user_id' => $studentUser->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM001',
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'academic_year' => '2025-2026',
        ]);

        $response = $this->deleteJson("/api/students/{$student->id}");

        $response->assertStatus(200);
        $this->assertSuccessResponse($response);
        
        // Should be soft deleted
        $this->assertSoftDeleted('students', ['id' => $student->id]);
    }
}

