<?php

namespace App\NotificationCampaigns\Modules;

use App\Models\Holiday;
use App\NotificationCampaigns\Concerns\EligibleTargetHelpers;
use App\NotificationCampaigns\NotificationCampaignModule;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class HolidaysCampaignModule implements NotificationCampaignModule
{
    use EligibleTargetHelpers;

    public function slug(): string
    {
        return 'holidays';
    }

    public function label(): string
    {
        return 'Holidays';
    }

    public function statuses(): array
    {
        return [
            ['key' => 'announcement', 'label' => 'Announcement'],
        ];
    }

    public function requiresEventDate(): bool
    {
        return true;
    }

    public function confirmSelection(): bool
    {
        return false;
    }

    public function eventDatePolicy(): string
    {
        return 'any';
    }

    public function eligibleTargets(int $branchId, string $date): array
    {
        if (!$this->holidayOnDate($branchId, $date)) {
            return [];
        }

        return $this->enrolledSections($branchId);
    }

    public function classifyByUserId(int $branchId, string $date, Collection $students): array
    {
        if (!$this->holidayOnDate($branchId, $date)) {
            return [];
        }

        $map = [];
        foreach ($students as $student) {
            $userId = (int) $student->user_id;
            if ($userId > 0) {
                $map[$userId] = 'announcement';
            }
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        $holiday = $this->findHoliday($branchId, $date);
        if ($holiday) {
            $baseContext['holiday_name'] = (string) ($holiday->title ?: $holiday->name ?: 'Holiday');
            $baseContext['holiday_date'] = $this->formatHolidayDateLabel($holiday, $date);
        }

        return $baseContext;
    }

    /**
     * Human-readable holiday date(s) for #holiday_date# — single day or inclusive range.
     */
    public function formatHolidayDateLabel(Holiday $holiday, string $fallbackEventDate): string
    {
        $start = $holiday->start_date ?? $holiday->date;
        $end = $holiday->end_date ?? $start ?? $holiday->date;

        return self::formatHolidayDateRange($start, $end, $fallbackEventDate);
    }

    /**
     * @param  \Carbon\CarbonInterface|string|null  $start
     * @param  \Carbon\CarbonInterface|string|null  $end
     */
    public static function formatHolidayDateRange($start, $end, string $fallbackEventDate): string
    {
        if ($start === null && $end === null) {
            return Carbon::parse($fallbackEventDate)->format('d M Y');
        }

        $startCarbon = Carbon::parse($start)->startOfDay();
        $endCarbon = Carbon::parse($end ?? $start)->startOfDay();

        if ($startCarbon->equalTo($endCarbon)) {
            return $startCarbon->format('d M Y');
        }

        return $startCarbon->format('d M Y').' – '.$endCarbon->format('d M Y');
    }

    private function holidayOnDate(int $branchId, string $date): bool
    {
        return $this->findHoliday($branchId, $date) !== null;
    }

    private function findHoliday(int $branchId, string $date): ?Holiday
    {
        return Holiday::query()
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)->orWhereNull('branch_id');
            })
            ->where(function ($q) use ($date) {
                $q->where(function ($q2) use ($date) {
                    $q2->whereDate('start_date', '<=', $date)
                        ->whereDate('end_date', '>=', $date);
                })->orWhereDate('date', $date);
            })
            ->where('is_active', true)
            ->orderByDesc('id')
            ->first();
    }
}
