<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\Student;
use App\Models\User;
use Tests\TestCase;

class CustomBroadcastAudienceTest extends TestCase
{
    protected $branch;

    protected User $sender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->createBranch();
        $this->sender = User::factory()->create([
            'role' => 'SuperAdmin',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    public function test_legacy_broadcast_sets_module_custom_metadata(): void
    {
        $studentUser = $this->seedStudent('6', 'B');

        $this->actingAs($this->sender, 'sanctum');

        $response = $this->postJson('/api/communications/notifications/broadcast', [
            'title' => 'Hello',
            'description' => 'Message body',
            'grade' => '6',
            'section' => 'B',
            'audience_mode' => 'all',
            'branch_id' => $this->branch->id,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('notifications', [
            'user_id' => $studentUser->id,
            'title' => 'Hello',
        ]);

        $row = Notification::query()->where('user_id', $studentUser->id)->first();
        $meta = is_array($row->metadata) ? $row->metadata : [];
        $this->assertSame('custom', $meta['source'] ?? null);
        $this->assertSame('custom', $meta['module'] ?? null);
    }

    public function test_staff_only_broadcast(): void
    {
        $staffUser = User::factory()->create([
            'role' => 'Staff',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->sender, 'sanctum');

        $response = $this->postJson('/api/communications/notifications/broadcast', [
            'title' => 'Staff note',
            'description' => 'For team',
            'include_students' => false,
            'include_staff' => true,
            'staff_audience_mode' => 'custom',
            'staff_user_ids' => [$staffUser->id],
            'branch_id' => $this->branch->id,
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.staff_count', 1);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $staffUser->id,
            'title' => 'Staff note',
        ]);
    }

    public function test_multi_target_students_appear_under_module_custom_inbox(): void
    {
        $studentA = $this->seedStudent('7', 'A');
        $studentB = $this->seedStudent('8', 'C');

        $this->actingAs($studentA, 'sanctum');
        $inboxBefore = $this->getJson('/api/communications/notifications?status=all&module=custom');
        $inboxBefore->assertOk();

        $this->actingAs($this->sender, 'sanctum');
        $response = $this->postJson('/api/communications/notifications/broadcast', [
            'title' => 'Multi class',
            'description' => 'Both sections',
            'include_students' => true,
            'include_staff' => false,
            'student_audience_mode' => 'all',
            'targets' => [
                ['grade' => '7', 'section' => 'A'],
                ['grade' => '8', 'section' => 'C'],
            ],
            'branch_id' => $this->branch->id,
        ]);
        $response->assertCreated();

        $this->actingAs($studentA, 'sanctum');
        $inbox = $this->getJson('/api/communications/notifications?status=all&module=custom');
        $inbox->assertOk();
        $titles = collect($inbox->json('data'))->pluck('title')->all();
        $this->assertContains('Multi class', $titles);

        $this->actingAs($studentB, 'sanctum');
        $inboxB = $this->getJson('/api/communications/notifications?status=all&module=custom');
        $titlesB = collect($inboxB->json('data'))->pluck('title')->all();
        $this->assertContains('Multi class', $titlesB);
    }

    private function seedStudent(string $grade, string $section): User
    {
        $studentUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        Student::create([
            'user_id' => $studentUser->id,
            'branch_id' => $this->branch->id,
            'admission_number' => 'ADM-'.uniqid(),
            'admission_date' => '2025-01-01',
            'grade' => $grade,
            'section' => $section,
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

        return $studentUser;
    }
}
