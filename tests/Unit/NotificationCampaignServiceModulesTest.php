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
use PHPUnit\Framework\TestCase;

class NotificationCampaignServiceModulesTest extends TestCase
{
    public function test_modules_meta_is_present_for_each_module(): void
    {
        $registry = new NotificationCampaignModuleRegistry;
        $registry->register(new AttendanceCampaignModule);
        $registry->register(new ExamsCampaignModule);
        $registry->register(new FeesCampaignModule);
        $registry->register(new HolidaysCampaignModule);
        $registry->register(new AssignmentsCampaignModule);

        $inbox = $this->createMock(InboxNotificationService::class);
        $service = new NotificationCampaignService(new SmsTemplateTagRenderer, $inbox, $registry);

        $modules = $service->modules();
        $meta = $service->modulesMeta();

        foreach (array_keys($modules) as $slug) {
            $this->assertArrayHasKey($slug, $meta);
            $this->assertArrayHasKey('label', $meta[$slug]);
            $this->assertArrayHasKey('requires_event_date', $meta[$slug]);
            $this->assertArrayHasKey('confirm_selection', $meta[$slug]);
            $this->assertArrayHasKey('event_date_policy', $meta[$slug]);
        }
    }
}
