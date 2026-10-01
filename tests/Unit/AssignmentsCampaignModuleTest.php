<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use PHPUnit\Framework\TestCase;

class AssignmentsCampaignModuleTest extends TestCase
{
    public function test_due_status_when_due_date_matches_notification_date(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-15',
            'published_at' => '2026-10-01',
        ];

        $this->assertSame('due', AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }

    public function test_published_status_when_published_today_but_due_later(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-20',
            'published_at' => '2026-10-15',
        ];

        $this->assertSame('published', AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }

    public function test_no_status_when_assignment_unrelated_to_date(): void
    {
        $assignment = (object) [
            'due_date' => '2026-10-20',
            'published_at' => '2026-10-01',
        ];

        $this->assertNull(AssignmentsCampaignModule::statusKeyForAssignmentOnDate($assignment, '2026-10-15'));
    }
}
