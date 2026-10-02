<?php

namespace App\Providers;

use App\NotificationCampaigns\Modules\AssignmentsCampaignModule;
use App\NotificationCampaigns\Modules\AttendanceCampaignModule;
use App\NotificationCampaigns\Modules\ExamsCampaignModule;
use App\NotificationCampaigns\Modules\FeesCampaignModule;
use App\NotificationCampaigns\Modules\HolidaysCampaignModule;
use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use Illuminate\Support\ServiceProvider;

class NotificationCampaignServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(NotificationCampaignModuleRegistry::class, function () {
            $registry = new NotificationCampaignModuleRegistry;
            $registry->register(new AttendanceCampaignModule);
            $registry->register(new ExamsCampaignModule);
            $registry->register(new FeesCampaignModule);
            $registry->register(new HolidaysCampaignModule);
            $registry->register(new AssignmentsCampaignModule);

            return $registry;
        });
    }
}
