<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use App\NotificationCampaigns\Modules\AttendanceCampaignModule;
use App\NotificationCampaigns\Modules\ExamsCampaignModule;
use App\NotificationCampaigns\Modules\FeesCampaignModule;
use App\NotificationCampaigns\Modules\HolidaysCampaignModule;
use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use PHPUnit\Framework\TestCase;

class NotificationCampaignModuleRegistryTest extends TestCase
{
    private function fullRegistry(): NotificationCampaignModuleRegistry
    {
        $registry = new NotificationCampaignModuleRegistry;
        $registry->register(new AttendanceCampaignModule);
        $registry->register(new ExamsCampaignModule);
        $registry->register(new FeesCampaignModule);
        $registry->register(new HolidaysCampaignModule);
        $registry->register(new AssignmentsCampaignModule);

        return $registry;
    }

    public function test_registry_exposes_all_builtin_modules(): void
    {
        $statuses = $this->fullRegistry()->statusesByModule();

        $this->assertArrayHasKey('attendance', $statuses);
        $this->assertArrayHasKey('exams', $statuses);
        $this->assertArrayHasKey('fees', $statuses);
        $this->assertArrayHasKey('holidays', $statuses);
        $this->assertArrayHasKey('assignments', $statuses);
    }

    public function test_attendance_meta_requires_date_and_confirm(): void
    {
        $meta = $this->fullRegistry()->metaByModule()['attendance'];

        $this->assertTrue($meta['requires_event_date']);
        $this->assertTrue($meta['confirm_selection']);
        $this->assertSame('today_only', $meta['event_date_policy']);
    }

    public function test_exams_module_requires_confirm_like_attendance(): void
    {
        $module = new ExamsCampaignModule;
        $this->assertFalse($module->requiresEventDate());
        $this->assertTrue($module->confirmSelection());
    }

    public function test_fees_module_confirm_selection_and_status_keys(): void
    {
        $module = new FeesCampaignModule;
        $this->assertTrue($module->requiresEventDate());
        $this->assertTrue($module->confirmSelection());
        $keys = array_column($module->statuses(), 'key');
        $this->assertSame(['due', 'overdue', 'structure'], $keys);
    }

    public function test_fees_meta_confirm_selection_in_registry(): void
    {
        $meta = $this->fullRegistry()->metaByModule()['fees'];
        $this->assertTrue($meta['confirm_selection']);
        $this->assertTrue($meta['requires_event_date']);
    }

    public function test_attendance_status_keys_unchanged(): void
    {
        $keys = array_column($this->fullRegistry()->statusesByModule()['attendance'], 'key');
        $this->assertSame(['present', 'absent', 'leave'], $keys);
    }
}
