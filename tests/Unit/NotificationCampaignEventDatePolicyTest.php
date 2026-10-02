<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use App\NotificationCampaigns\Modules\AttendanceCampaignModule;
use App\NotificationCampaigns\Modules\ExamsCampaignModule;
use App\NotificationCampaigns\Modules\FeesCampaignModule;
use App\NotificationCampaigns\Modules\HolidaysCampaignModule;
use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use App\Services\InboxNotificationService;
use App\Services\NotificationCampaignService;
use App\Services\SmsTemplateTagRenderer;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class NotificationCampaignEventDatePolicyTest extends TestCase
{
    private function service(): NotificationCampaignService
    {
        $registry = new NotificationCampaignModuleRegistry;
        $registry->register(new AttendanceCampaignModule);
        $registry->register(new ExamsCampaignModule);
        $registry->register(new FeesCampaignModule);
        $registry->register(new HolidaysCampaignModule);
        $registry->register(new AssignmentsCampaignModule);

        return new NotificationCampaignService(
            new SmsTemplateTagRenderer,
            $this->createMock(InboxNotificationService::class),
            $registry,
        );
    }

    public function test_attendance_rejects_non_today_dates(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-10-01 10:00:00'));
        $service = $this->service();

        $this->assertNotNull($service->validateEventDatePolicy('attendance', '2026-09-30'));
        $this->assertNull($service->validateEventDatePolicy('attendance', '2026-10-01'));
        $this->assertNotNull($service->validateEventDatePolicy('attendance', '2026-10-02'));

        Carbon::setTestNow();
    }

    public function test_exams_allows_any_date(): void
    {
        $service = $this->service();
        $this->assertNull($service->validateEventDatePolicy('exams', '2020-01-01'));
    }
}
