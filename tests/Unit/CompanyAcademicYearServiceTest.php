<?php

namespace Tests\Unit;

use App\Services\CompanyAcademicYearService;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class CompanyAcademicYearServiceTest extends TestCase
{
    public function test_indian_fy_starts_in_april_of_current_year(): void
    {
        $service = new CompanyAcademicYearService;
        [$name, $start, $end] = $service->indianFinancialYearWindow(Carbon::create(2026, 9, 23));

        $this->assertSame('2026-2027', $name);
        $this->assertSame('2026-04-01', $start);
        $this->assertSame('2027-03-31', $end);
    }

    public function test_indian_fy_before_april_uses_previous_year(): void
    {
        $service = new CompanyAcademicYearService;
        [$name, $start, $end] = $service->indianFinancialYearWindow(Carbon::create(2026, 2, 1));

        $this->assertSame('2025-2026', $name);
        $this->assertSame('2025-04-01', $start);
        $this->assertSame('2026-03-31', $end);
    }
}
