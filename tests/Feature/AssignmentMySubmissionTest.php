<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentRecipient;
use App\Models\AssignmentSubmission;
use App\Models\Department;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AssignmentMySubmissionTest extends TestCase
{
    protected $branch;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->createBranch();
    }

    /** @test */
    public function student_recipient_can_mark_assignment_complete(): void
    {
        [$assignment, $studentUser] = $this->createPublishedAssignmentForStudent();

        $this->actingAs($studentUser, 'sanctum');

        $response = $this->postJson("/api/assignments/{$assignment->id}/my-submission", [
            'submission_text' => 'Finished pages 1–3',
        ]);

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.my_submission.status', 'Submitted');
        $response->assertJsonPath('data.my_submission.submission_text', 'Finished pages 1–3');
        $response->assertJsonPath('data.my_submission.is_complete', true);

        $this->assertDatabaseHas('assignment_submissions', [
            'assignment_id' => $assignment->id,
            'student_id' => Student::where('user_id', $studentUser->id)->value('id'),
            'status' => 'Submitted',
        ]);
    }

    /** @test */
    public function past_due_submission_is_marked_late(): void
    {
        [$assignment, $studentUser] = $this->createPublishedAssignmentForStudent([
            'due_date' => now()->subDays(2),
        ]);

        $this->actingAs($studentUser, 'sanctum');

        $response = $this->postJson("/api/assignments/{$assignment->id}/my-submission", []);

        $this->assertSuccessResponse($response);
        $response->assertJsonPath('data.my_submission.status', 'Late');
    }

    /** @test */
    public function non_recipient_student_cannot_submit(): void
    {
        [$assignment, $studentUser] = $this->createPublishedAssignmentForStudent();
        AssignmentRecipient::query()->where('assignment_id', $assignment->id)->delete();

        $this->actingAs($studentUser, 'sanctum');

        $response = $this->postJson("/api/assignments/{$assignment->id}/my-submission", []);

        $response->assertStatus(404);
    }

    /** @test */
    public function teacher_cannot_submit_my_submission(): void
    {
        [$assignment] = $this->createPublishedAssignmentForStudent();
        $teacher = User::factory()->create([
            'role' => 'Teacher',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($teacher, 'sanctum');

        $response = $this->postJson("/api/assignments/{$assignment->id}/my-submission", []);

        $response->assertStatus(403);
    }

    /** @test */
    public function duplicate_submit_returns_conflict(): void
    {
        [$assignment, $studentUser] = $this->createPublishedAssignmentForStudent();
        $studentId = Student::where('user_id', $studentUser->id)->value('id');

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
            'status' => 'Submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($studentUser, 'sanctum');

        $response = $this->postJson("/api/assignments/{$assignment->id}/my-submission", [
            'submission_text' => 'Again',
        ]);

        $response->assertStatus(409);
    }

    /** @test */
    public function index_includes_my_status_for_student(): void
    {
        [$assignment, $studentUser] = $this->createPublishedAssignmentForStudent();
        $studentId = Student::where('user_id', $studentUser->id)->value('id');

        AssignmentSubmission::create([
            'assignment_id' => $assignment->id,
            'student_id' => $studentId,
            'status' => 'Submitted',
            'submitted_at' => now(),
        ]);

        $this->actingAs($studentUser, 'sanctum');

        $response = $this->getJson('/api/assignments');

        $this->assertSuccessResponse($response);
        $rows = collect($response->json('data'));
        $row = $rows->firstWhere('id', $assignment->id);
        $this->assertNotNull($row);
        $this->assertSame('submitted', $row['my_status']);
    }

    /**
     * @param  array<string, mixed>  $assignmentOverrides
     * @return array{0: Assignment, 1: User}
     */
    private function createPublishedAssignmentForStudent(array $assignmentOverrides = []): array
    {
        $teacher = User::factory()->create([
            'role' => 'Teacher',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $studentUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $student = Student::create([
            'user_id' => $studentUser->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM-'.uniqid(),
            'admission_date' => '2025-01-01',
            'grade' => '5',
            'section' => 'A',
            'academic_year' => '2025-2026',
            'date_of_birth' => '2010-05-15',
            'gender' => 'Male',
            'current_address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'pincode' => '123456',
            'father_name' => 'Father',
            'father_phone' => '9876543210',
            'mother_name' => 'Mother',
            'emergency_contact_name' => 'Emergency',
            'emergency_contact_phone' => '5555555555',
        ]);

        $department = Department::create([
            'branch_id' => $this->branch->id,
            'name' => 'Science',
            'head' => 'Head',
            'is_active' => true,
        ]);

        $subject = Subject::create([
            'branch_id' => $this->branch->id,
            'department_id' => $department->id,
            'name' => 'English',
            'code' => 'ENG-'.strtoupper(substr(uniqid(), -6)),
            'grade_level' => '5',
        ]);

        $due = $assignmentOverrides['due_date'] ?? now()->addDays(3);
        if ($due instanceof Carbon) {
            $due = $due->copy();
        }

        $assignment = Assignment::create(array_merge([
            'branch_id' => $this->branch->id,
            'grade' => '5',
            'section' => 'A',
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
            'title' => 'Test assignment',
            'due_date' => $due,
            'is_published' => true,
            'published_at' => now(),
            'created_by' => $teacher->id,
            'audience_mode' => 'custom',
        ], $assignmentOverrides));

        AssignmentRecipient::create([
            'assignment_id' => $assignment->id,
            'student_id' => $student->id,
        ]);

        return [$assignment, $studentUser];
    }
}
