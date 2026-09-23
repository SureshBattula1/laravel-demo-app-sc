<?php

namespace App\Services;

use App\Models\AcademicYear;
use Carbon\Carbon;

class CompanyAcademicYearService
{
    /**
     * Reuse the company's current academic year, or create an Indian FY (1 Apr–31 Mar).
     */
    public function ensureCurrentIndianYear(int $companyId): AcademicYear
    {
        $existing = AcademicYear::query()
            ->where('company_id', $companyId)
            ->current()
            ->active()
            ->first();

        if ($existing) {
            return $existing;
        }

        [$name, $startDate, $endDate] = $this->indianFinancialYearWindow();

        $named = AcademicYear::query()
            ->where('company_id', $companyId)
            ->where('name', $name)
            ->first();

        AcademicYear::query()
            ->where('company_id', $companyId)
            ->update(['is_current' => false]);

        if ($named) {
            $named->update([
                'is_current' => true,
                'is_active' => true,
                'start_date' => $named->start_date ?: $startDate,
                'end_date' => $named->end_date ?: $endDate,
            ]);
            AcademicYearContext::clearCurrentCache($companyId);

            return $named->fresh();
        }

        $year = AcademicYear::create([
            'company_id' => $companyId,
            'name' => $name,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_current' => true,
            'is_active' => true,
            'description' => 'Default academic year created with school',
        ]);

        AcademicYearContext::clearCurrentCache($companyId);

        return $year;
    }

    /**
     * @return array{0: string, 1: string, 2: string} name, start_date, end_date
     */
    public function indianFinancialYearWindow(?Carbon $now = null): array
    {
        $now = $now ?? now();
        $startYear = $now->month >= 4 ? $now->year : $now->year - 1;

        return [
            $startYear.'-'.($startYear + 1),
            sprintf('%d-04-01', $startYear),
            sprintf('%d-03-31', $startYear + 1),
        ];
    }
}
