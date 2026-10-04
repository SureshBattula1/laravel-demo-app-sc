<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use PHPUnit\Framework\TestCase;

class AssignmentNotifyDeltaTest extends TestCase
{
    public function test_has_new_assignment_ids_when_extra_assignment_published(): void
    {
        $this->assertTrue(AssignmentsCampaignModule::hasNewAssignmentIds([1, 2, 3], [1, 2]));
    }

    public function test_no_delta_when_all_assignments_already_notified(): void
    {
        $this->assertFalse(AssignmentsCampaignModule::hasNewAssignmentIds([1, 2], [1, 2]));
        $this->assertFalse(AssignmentsCampaignModule::hasNewAssignmentIds([1, 2], [2, 1]));
    }

    public function test_first_send_notified_empty(): void
    {
        $this->assertTrue(AssignmentsCampaignModule::hasNewAssignmentIds([5], []));
    }
}
