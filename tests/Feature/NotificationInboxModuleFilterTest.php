<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\User;
use Tests\TestCase;

class NotificationInboxModuleFilterTest extends TestCase
{
    protected $branch;

    protected User $inboxUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branch = $this->createBranch();
        $this->inboxUser = User::factory()->create([
            'role' => 'Student',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);
    }

    public function test_module_assignments_includes_direct_and_campaign(): void
    {
        $this->seedNotification(['source' => 'assignment'], 'Direct assignment');
        $this->seedNotification([
            'source' => 'notification_campaign',
            'module' => 'assignments',
        ], 'Campaign assignment');
        $this->seedNotification([
            'source' => 'notification_campaign',
            'module' => 'fees',
        ], 'Fees only');

        $this->actingAs($this->inboxUser, 'sanctum');

        $response = $this->getJson('/api/communications/notifications?status=all&module=assignments');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Direct assignment', $titles);
        $this->assertContains('Campaign assignment', $titles);
        $this->assertNotContains('Fees only', $titles);
    }

    public function test_module_attendance_includes_legacy_sources(): void
    {
        $this->seedNotification(['source' => 'attendance'], 'Attendance legacy');
        $this->seedNotification(['source' => 'attendance_notify'], 'Attendance notify');
        $this->seedNotification([
            'source' => 'notification_campaign',
            'module' => 'teacher_attendance',
        ], 'Teacher attendance campaign');
        $this->seedNotification(['source' => 'custom'], 'Custom message');

        $this->actingAs($this->inboxUser, 'sanctum');

        $response = $this->getJson('/api/communications/notifications?status=all&module=attendance');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Attendance legacy', $titles);
        $this->assertContains('Attendance notify', $titles);
        $this->assertContains('Teacher attendance campaign', $titles);
        $this->assertNotContains('Custom message', $titles);
    }

    public function test_module_custom_and_period_today(): void
    {
        $this->seedNotification(['source' => 'custom'], 'Custom today');
        $this->seedNotification([
            'source' => 'notification_campaign',
            'module' => 'custom',
        ], 'Campaign custom');
        $this->seedNotification([
            'source' => 'notification_campaign',
            'module' => 'holidays',
        ], 'Holiday');

        $this->actingAs($this->inboxUser, 'sanctum');

        $response = $this->getJson('/api/communications/notifications?status=all&module=custom&period=today');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->all();
        $this->assertContains('Custom today', $titles);
        $this->assertContains('Campaign custom', $titles);
        $this->assertNotContains('Holiday', $titles);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function seedNotification(array $meta, string $title): void
    {
        Notification::query()->create([
            'branch_id' => $this->branch->id,
            'user_id' => $this->inboxUser->id,
            'title' => $title,
            'message' => $title,
            'type' => 'Info',
            'priority' => 'Medium',
            'status' => 'Sent',
            'metadata' => $meta,
            'sent_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
