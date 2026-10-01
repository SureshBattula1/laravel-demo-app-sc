<?php

namespace Tests\Unit;

use App\NotificationCampaigns\Modules\HolidaysCampaignModule;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class HolidaysCampaignModuleTest extends TestCase
{
    public function test_format_holiday_date_range_single_day(): void
    {
        $this->assertSame(
            '03 Oct 2026',
            HolidaysCampaignModule::formatHolidayDateRange('2026-10-03', '2026-10-03', '2026-10-03')
        );
    }

    public function test_format_holiday_date_range_multi_day(): void
    {
        $this->assertSame(
            '03 Oct 2026 – 06 Oct 2026',
            HolidaysCampaignModule::formatHolidayDateRange('2026-10-03', '2026-10-06', '2026-10-03')
        );
    }

    public function test_format_holiday_date_range_falls_back_to_event_date(): void
    {
        $this->assertSame(
            Carbon::parse('2026-10-03')->format('d M Y'),
            HolidaysCampaignModule::formatHolidayDateRange(null, null, '2026-10-03')
        );
    }
}
