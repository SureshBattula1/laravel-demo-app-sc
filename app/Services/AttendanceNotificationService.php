<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceNotificationService
{
    public function __construct(
        protected InboxNotificationService $inbox,
        protected AssignmentRecipientResolver $resolver
    ) {}

    /**
     * @param  list<array{id:int,status:string,grade_level?:string,section?:string}>  $items
     */
    public function fanOutStudentBulk(
        int $branchId,
        ?int $schoolId,
        string $date,
        array $items,
        int $createdBy
    ): void {
        $groups = [];
        foreach ($items as $item) {
            $grade = (string) ($item['grade_level'] ?? '');
            $section = (string) ($item['section'] ?? '');
            if ($grade === '' || $section === '') {
                continue;
            }
            $key = $grade . '|' . $section;
            $groups[$key][] = $item;
        }

        foreach ($groups as $groupItems) {
            $first = $groupItems[0];
            $this->fanOutClass(
                $branchId,
                $schoolId,
                (string) $first['grade_level'],
                (string) $first['section'],
                $date,
                $groupItems,
                $createdBy
            );
        }
    }

    public function fanOutSingleStudent(
        int $branchId,
        ?int $schoolId,
        int $studentUserId,
        string $grade,
        string $section,
        string $date,
        string $status,
        int $createdBy
    ): void {
        $this->fanOutClass($branchId, $schoolId, $grade, $section, $date, [
            ['id' => $studentUserId, 'status' => $status, 'grade_level' => $grade, 'section' => $section],
        ], $createdBy);
    }

    /**
     * @param  list<array{id:int,status:string}>  $items
     */
    public function fanOutTeacherBulk(int $branchId, ?int $schoolId, string $date, array $items, int $createdBy): void
    {
        $userIds = collect($items)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $staff = $this->resolver->staffUserIds($branchId, $schoolId, $createdBy);
        $label = Carbon::parse($date)->format('d M Y');
        $groupKey = "attendance:teacher:{$branchId}:{$date}";
        $title = 'Teacher attendance';
        $message = 'Attendance was marked for ' . count($userIds) . " teacher(s) on {$label}.";

        $this->inbox->insertForUsers(
            $userIds,
            $branchId,
            $title,
            'Your attendance was marked for ' . $label . '.',
            [
                'source' => 'attendance',
                'event' => 'created',
                'audience' => 'teacher',
                'group_key' => $groupKey,
                'date' => $date,
            ],
            $createdBy,
            'Info',
            'Medium',
            '/attendance'
        );

        $this->inbox->insertForUsers(
            array_merge($staff['teachers'], $staff['admins'], [$createdBy]),
            $branchId,
            $title,
            $message,
            [
                'source' => 'attendance',
                'event' => 'created',
                'audience' => 'admin',
                'group_key' => $groupKey,
                'date' => $date,
            ],
            $createdBy,
            'Info',
            'Medium',
            '/attendance'
        );
    }

    /**
     * @param  list<array{id:int,status:string}>  $items  id is users.id
     */
    private function fanOutClass(
        int $branchId,
        ?int $schoolId,
        string $grade,
        string $section,
        string $date,
        array $items,
        int $createdBy
    ): void {
        $label = Carbon::parse($date)->format('d M Y');
        $classLabel = "{$grade}-{$section}";
        $groupKey = "attendance:{$branchId}:{$grade}:{$section}:{$date}";
        $counts = collect($items)->groupBy('status')->map->count();
        $present = (int) ($counts['Present'] ?? 0);
        $absent = (int) ($counts['Absent'] ?? 0);
        $other = count($items) - $present - $absent;

        $staff = $this->resolver->staffUserIds($branchId, $schoolId, null);
        $summary = "Attendance marked for {$classLabel} on {$label}: {$present} present, {$absent} absent"
            . ($other > 0 ? ", {$other} other" : '') . '.';

        $this->inbox->insertForUsers(
            array_values(array_unique(array_merge($staff['teachers'], $staff['admins'], [$createdBy]))),
            $branchId,
            "Attendance · {$classLabel}",
            $summary,
            [
                'source' => 'attendance',
                'event' => 'created',
                'audience' => 'staff',
                'group_key' => $groupKey,
                'grade' => $grade,
                'section' => $section,
                'date' => $date,
                'present' => $present,
                'absent' => $absent,
            ],
            $createdBy,
            'Info',
            'Medium',
            '/attendance'
        );

        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? 'Marked');
            $this->inbox->insertForUsers(
                [(int) $item['id']],
                $branchId,
                "Attendance · {$classLabel}",
                "You were marked {$status} for {$classLabel} on {$label}.",
                [
                    'source' => 'attendance',
                    'event' => 'created',
                    'audience' => 'student',
                    'group_key' => $groupKey,
                    'grade' => $grade,
                    'section' => $section,
                    'date' => $date,
                    'status' => $status,
                ],
                $createdBy,
                $status === 'Absent' ? 'Warning' : 'Info',
                $status === 'Absent' ? 'High' : 'Medium',
                '/attendance'
            );
        }
    }

    /**
     * Admin-triggered student status notify for classes with created attendance.
     *
     * @param  list<array{grade:string,section:string}>  $classes
     * @return array{
     *   student_count:int,
     *   skipped_no_login:int,
     *   campaigns: list<array{grade:string,section:string,group_key:string,student_count:int,sent_at:string}>
     * }
     */
    public function notifyStudentsForClasses(
        int $branchId,
        string $date,
        array $classes,
        int $createdBy
    ): array {
        $label = Carbon::parse($date)->format('d M Y');
        $campaigns = [];
        $studentCount = 0;
        $skipped = 0;

        foreach ($classes as $class) {
            $grade = (string) ($class['grade'] ?? '');
            $section = (string) ($class['section'] ?? '');
            if ($grade === '' || $section === '') {
                continue;
            }

            $rows = DB::table('student_attendance')
                ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('student_attendance.branch_id', $branchId)
                ->where('student_attendance.grade_level', $grade)
                ->where('student_attendance.section', $section)
                ->whereDate('student_attendance.date', $date)
                ->whereNull('students.deleted_at')
                ->select(
                    'students.user_id',
                    'students.roll_number',
                    'users.first_name',
                    'users.last_name',
                    'student_attendance.status'
                )
                ->orderBy('students.roll_number')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $groupKey = 'attendance_notify:' . $branchId . ':' . $grade . ':' . $section . ':' . $date . ':' . now()->timestamp;
            $classLabel = "Grade {$grade} - {$section}";
            $sentForClass = 0;

            foreach ($rows as $row) {
                $userId = (int) ($row->user_id ?? 0);
                $name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? ''));
                $roll = $row->roll_number ? (string) $row->roll_number : '—';
                $status = (string) ($row->status ?? 'Marked');

                $statusLabel = match ($status) {
                    'Present' => 'Present',
                    'Absent' => 'Absent',
                    'Late' => 'Late',
                    'Half-Day' => 'Half-Day',
                    'Sick Leave' => 'on Sick Leave',
                    'Leave' => 'on Leave',
                    default => $status,
                };
                $description = "{$name} is {$statusLabel}";
                $optional = "Date: {$label} · Class: {$classLabel} · Roll: {$roll}";

                if ($userId <= 0) {
                    $skipped++;
                    continue;
                }

                $this->inbox->insertForUsers(
                    [$userId],
                    $branchId,
                    'Attendance',
                    $description,
                    [
                        'source' => 'attendance_notify',
                        'event' => 'admin_notify',
                        'audience' => 'student',
                        'group_key' => $groupKey,
                        'grade' => $grade,
                        'section' => $section,
                        'date' => $date,
                        'status' => $status,
                        'roll_number' => $roll,
                        'student_name' => $name,
                        'description' => $description,
                        'optional_description' => $optional,
                    ],
                    $createdBy,
                    $status === 'Absent' ? 'Warning' : 'Info',
                    $status === 'Absent' ? 'High' : 'Medium',
                    '/attendance'
                );
                $sentForClass++;
                $studentCount++;
            }

            if ($sentForClass > 0) {
                $campaigns[] = [
                    'grade' => $grade,
                    'section' => $section,
                    'group_key' => $groupKey,
                    'student_count' => $sentForClass,
                    'sent_at' => now()->toDateTimeString(),
                ];
            }
        }

        return [
            'student_count' => $studentCount,
            'skipped_no_login' => $skipped,
            'campaigns' => $campaigns,
        ];
    }
}
