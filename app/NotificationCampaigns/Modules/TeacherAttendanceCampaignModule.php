<?php

namespace App\NotificationCampaigns\Modules;

use App\NotificationCampaigns\NotificationCampaignModule;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeacherAttendanceCampaignModule implements NotificationCampaignModule
{
    public const SECTION_KEY = 'Teachers';

    public function slug(): string
    {
        return 'teacher_attendance';
    }

    public function label(): string
    {
        return 'Teacher attendance';
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
        $summaries = $this->departmentAttendanceSummaries($branchId, $date);
        $out = [];
        foreach ($summaries as $deptKey => $row) {
            $out[] = [
                'grade' => $deptKey,
                'section' => self::SECTION_KEY,
                'class_name' => $row['label'],
                'student_count' => $row['marked_count'],
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label:string,enrolled_count:int,marked_count:int,present:int,absent:int,leave:int}>
     */
    public function departmentAttendanceSummaries(int $branchId, string $date): array
    {
        $deptNames = DB::table('departments')
            ->where('branch_id', $branchId)
            ->pluck('name', 'id');

        $map = [];

        $enrolledRows = DB::table('teachers')
            ->where('branch_id', $branchId)
            ->where('teacher_status', 'Active')
            ->whereNotNull('user_id')
            ->selectRaw('COALESCE(department_id, 0) as dept_key, COUNT(*) as enrolled_count')
            ->groupBy('dept_key')
            ->get();

        foreach ($enrolledRows as $row) {
            $key = (string) (int) $row->dept_key;
            $map[$key] = [
                'label' => $key === '0' ? 'Teachers (no department)' : (string) ($deptNames[(int) $key] ?? 'Department '.$key),
                'enrolled_count' => (int) $row->enrolled_count,
                'marked_count' => 0,
                'present' => 0,
                'absent' => 0,
                'leave' => 0,
            ];
        }

        $statusRows = DB::table('teacher_attendance as ta')
            ->join('teachers as t', 't.user_id', '=', 'ta.teacher_id')
            ->where('ta.branch_id', $branchId)
            ->whereDate('ta.date', $date)
            ->where('t.teacher_status', 'Active')
            ->selectRaw('COALESCE(t.department_id, 0) as dept_key, ta.status as status, COUNT(*) as tally')
            ->groupBy('dept_key', 'ta.status')
            ->get();

        foreach ($statusRows as $row) {
            $key = (string) (int) $row->dept_key;
            if (! isset($map[$key])) {
                $map[$key] = [
                    'label' => $key === '0' ? 'Teachers (no department)' : (string) ($deptNames[(int) $key] ?? 'Department '.$key),
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

    public function classifyByUserId(int $branchId, string $date, Collection $students): array
    {
        return [];
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    public function classifyTeacherUserIds(int $branchId, string $date, array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = DB::table('teacher_attendance')
            ->where('branch_id', $branchId)
            ->whereDate('date', $date)
            ->whereIn('teacher_id', $userIds)
            ->get(['teacher_id', 'status']);

        $map = [];
        foreach ($rows as $row) {
            $status = match ($row->status) {
                'Absent' => 'absent',
                'Leave', 'Sick Leave' => 'leave',
                'Present', 'Late', 'Half-Day' => 'present',
                default => null,
            };
            if ($status !== null) {
                $map[(int) $row->teacher_id] = $status;
            }
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        return $baseContext;
    }

    private function tallyBucket(string $status): string
    {
        return match ($status) {
            'Absent' => 'absent',
            'Leave', 'Sick Leave' => 'leave',
            default => 'present',
        };
    }
}
