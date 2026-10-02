<?php

namespace Tests\Unit;

use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use App\Services\InboxNotificationService;
use App\Services\NotificationCampaignService;
use App\Services\SmsTemplateTagRenderer;
use PHPUnit\Framework\TestCase;

class NotificationCampaignStaffRecipientsTest extends TestCase
{
    private function service(): NotificationCampaignService
    {
        return new NotificationCampaignService(
            $this->createMock(SmsTemplateTagRenderer::class),
            $this->createMock(InboxNotificationService::class),
            new NotificationCampaignModuleRegistry,
        );
    }

    public function test_filter_valid_staff_user_ids_empty_input(): void
    {
        $ids = $this->service()->filterValidStaffUserIds(1, []);
        $this->assertSame([], $ids);
    }

    public function test_staff_status_key_constant(): void
    {
        $this->assertSame('staff', NotificationCampaignService::STAFF_STATUS_KEY);
    }
}
