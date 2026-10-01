<?php

namespace App\NotificationCampaigns\Modules;

use App\NotificationCampaigns\Concerns\EligibleTargetHelpers;
use App\NotificationCampaigns\NotificationCampaignModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceCampaignModule implements NotificationCampaignModule
{
    use EligibleTargetHelpers;

    public function slug(): string
    {
        return 'attendance';
    }

    public function label(): string
    {
        return 'Attendance';
    }

    public function statuses(): array
    {
        return [
            ['key' => 'present', 'label' => 'Present'],
            ['key' => 'absent', 'label' => 'Absent'],
            ['key' => 'leave', 'label' => 'Leave'],
        ];
    }

    public function requiresEventDate(): bool
    {
        return true;
    }

    public function confirmSelection(): bool
    {
        return true;
    }

    public function eventDatePolicy(): string
    {
        return 'today_only';
    }

    public function eligibleTargets(int $branchId, string $date): array
    {
        // Use enrolled student grade so keys match class picker (students.grade) and campaign targets.
        $rows = DB::table('student_attendance as sa')
            ->join('students as s', 's.id', '=', 'sa.student_id')
            ->where('sa.branch_id', $branchId)
            ->whereDate('sa.date', $date)
            ->whereNotNull('sa.section')
            ->where('sa.section', '!=', '')
            ->selectRaw('s.grade as grade, sa.section as section, COUNT(*) as marked_count')
            ->groupBy('s.grade', 'sa.section')
            ->orderBy('s.grade')
            ->orderBy('sa.section')
            ->get();

        return $this->mapEligibleRows($rows, 'grade', 'marked_count');
    }

    /**
     * Per-section enrollment vs marks and P/A/L tally for schedule UI.
     *
     * @return array<string, array{enrolled_count:int,marked_count:int,present:int,absent:int,leave:int}>
     */
    public function sectionAttendanceSummaries(int $branchId, string $date): array
    {
        $map = [];

        $enrolledRows = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as enrolled_count')
            ->groupBy('grade', 'section')
            ->get();

        foreach ($enrolledRows as $row) {
            $key = $this->sectionKey((string) $row->grade, (string) $row->section);
            $map[$key] = [
                'enrolled_count' => (int) $row->enrolled_count,
                'marked_count' => 0,
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
            ];
        }

        $statusRows = DB::table('student_attendance as sa')
            ->join('students as s', 's.id', '=', 'sa.student_id')
            ->where('sa.branch_id', $branchId)
            ->whereDate('sa.date', $date)
            ->whereNotNull('sa.section')
            ->where('sa.section', '!=', '')
            ->selectRaw('s.grade as grade, sa.section as section, sa.status as status, COUNT(*) as tally')
            ->groupBy('s.grade', 'sa.section', 'sa.status')
            ->get();

        foreach ($statusRows as $row) {
            $key = $this->sectionKey((string) $row->grade, (string) $row->section);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'enrolled_count' => 0,
                    'marked_count' => 0,
                    'present' => 0,
                    'absent' => 0,
                    'leave' => 0,
                ];
            }
            $count = (int) $row->tally;
            $map[$key]['marked_count'] += $count;
            $bucket = $this->tallyBucket((string) $row->status);
            $map[$key][$bucket] += $count;
        }

        return $map;
    }

    private function sectionKey(string $grade, string $section): string
    {
        return trim($grade).'|'.trim($section);
    }

    private function tallyBucket(string $status): string
    {
        return match ($status) {
            'Absent' => 'absent',
            'Leave', 'Sick Leave' => 'leave',
            default => 'present',
        };
    }

    public function classifyByUserId(int $branchId, string $date, Collection $students): array
    {
        $userIds = $students->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('student_attendance')
            ->where('branch_id', $branchId)
            ->whereDate('date', $date)
            ->whereIn('student_id', $userIds)
            ->get(['student_id', 'status']);

        $map = [];
        foreach ($rows as $row) {
            $status = match ($row->status) {
                'Absent' => 'absent',
                'Leave', 'Sick Leave' => 'leave',
                'Present', 'Late', 'Half-Day' => 'present',
                default => null,
            };
            if ($status !== null) {
                $map[(int) $row->student_id] = $status;
            }
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        return $baseContext;
    }
}
