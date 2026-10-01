<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\ExamMark;
use App\Models\FeeDue;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\NotificationCampaignTarget;
use App\Models\SmsTemplate;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class NotificationCampaignService
{
    public function __construct(
        protected SmsTemplateTagRenderer $renderer,
        protected InboxNotificationService $inbox
    ) {}

    /**
     * @return array<string, list<array{key:string,label:string}>>
     */
    public function modules(): array
    {
        return [
            'attendance' => [
                ['key' => 'present', 'label' => 'Present'],
                ['key' => 'absent', 'label' => 'Absent'],
                ['key' => 'leave', 'label' => 'Leave'],
            ],
            'exams' => [
                ['key' => 'scheduled', 'label' => 'Scheduled'],
                ['key' => 'result', 'label' => 'Result'],
            ],
            'fees' => [
                ['key' => 'due', 'label' => 'Due'],
                ['key' => 'paid', 'label' => 'Paid'],
                ['key' => 'overdue', 'label' => 'Overdue'],
            ],
            'holidays' => [
                ['key' => 'announcement', 'label' => 'Announcement'],
            ],
            'assignments' => [
                ['key' => 'published', 'label' => 'Published'],
                ['key' => 'due', 'label' => 'Due'],
            ],
        ];
    }

    public function statusKeys(string $module): array
    {
        return array_column($this->modules()[$module] ?? [], 'key');
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     * @return list<array<string, mixed>>
     */
    public function resolveRecipients(string $module, int $branchId, ?string $eventDate, array $targets, array $templateMap): array
    {
        $students = $this->studentsForTargets($branchId, $targets);
        if ($students->isEmpty()) {
            return [];
        }

        $date = $eventDate ?: now()->toDateString();
        $userIds = $students->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        $statusByUser = $this->statusByUser($module, $branchId, $date, $students, $userIds);
        $mapped = array_keys(array_filter($templateMap));

        $rows = [];
        foreach ($students as $student) {
            $userId = (int) $student->user_id;
            if ($userId <= 0) {
                continue;
            }
            $statusKey = $statusByUser[$userId] ?? null;
            if (!$statusKey || !in_array($statusKey, $mapped, true)) {
                continue;
            }
            $user = $student->user;
            $name = $user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) : '';
            $rows[] = [
                'user_id' => $userId,
                'student_id' => $student->id,
                'student_name' => $name,
                'grade' => (string) $student->grade,
                'section' => (string) ($student->section ?? ''),
                'status_key' => $statusKey,
                'context' => [
                    'student_name' => $name,
                    'grade' => (string) $student->grade,
                    'section' => (string) ($student->section ?? ''),
                    'class_name' => trim($student->grade . ' ' . ($student->section ?? '')),
                    'roll_number' => (string) ($student->roll_number ?? ''),
                    'father_name' => (string) ($student->father_name ?? ''),
                    'mother_name' => (string) ($student->mother_name ?? ''),
                    'mobile' => (string) ($user->phone ?? ''),
                    'date' => Carbon::parse($date)->format('d M Y'),
                    'attendance_date' => Carbon::parse($date)->format('d M Y'),
                    'status' => ucfirst($statusKey),
                ],
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     * @return list<array{status_key:string,label:string,message:string,student_name:string}>
     */
    public function previewSamples(string $module, int $branchId, ?string $eventDate, array $targets, array $templateMap): array
    {
        $recipients = $this->resolveRecipients($module, $branchId, $eventDate, $targets, $templateMap);
        $templates = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', array_values($templateMap))
            ->get()
            ->keyBy('id');

        $samples = [];
        foreach ($this->modules()[$module] ?? [] as $status) {
            $templateId = $templateMap[$status['key']] ?? null;
            if (!$templateId || !$templates->has($templateId)) {
                continue;
            }
            $example = collect($recipients)->firstWhere('status_key', $status['key']);
            $context = $example['context'] ?? [
                'student_name' => 'Sample Student',
                'grade' => $targets[0]['grade'] ?? '',
                'section' => $targets[0]['section'] ?? '',
                'class_name' => trim(($targets[0]['grade'] ?? '') . ' ' . ($targets[0]['section'] ?? '')),
                'date' => $eventDate ? Carbon::parse($eventDate)->format('d M Y') : now()->format('d M Y'),
                'attendance_date' => $eventDate ? Carbon::parse($eventDate)->format('d M Y') : now()->format('d M Y'),
                'status' => $status['label'],
            ];
            $samples[] = [
                'status_key' => $status['key'],
                'label' => $status['label'],
                'student_name' => $example['student_name'] ?? 'Sample Student',
                'message' => $this->renderer->render((string) $templates[$templateId]->body, $context),
                'recipient_count' => collect($recipients)->where('status_key', $status['key'])->count(),
            ];
        }

        return $samples;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     */
    public function createCampaign(string $module, int $branchId, ?string $eventDate, array $targets, array $templateMap, int $createdBy): NotificationCampaign
    {
        $recipients = $this->resolveRecipients($module, $branchId, $eventDate, $targets, $templateMap);
        if ($recipients === []) {
            throw new \InvalidArgumentException('No students match the selected classes and template statuses.');
        }

        return DB::transaction(function () use ($module, $branchId, $eventDate, $targets, $templateMap, $createdBy, $recipients) {
            $campaign = NotificationCampaign::create([
                'module' => $module,
                'branch_id' => $branchId,
                'event_date' => $eventDate,
                'scheduled_at' => now(),
                'status' => 'pending',
                'template_map' => $templateMap,
                'target_count' => count($targets),
                'recipient_count' => count($recipients),
                'created_by' => $createdBy,
            ]);

            $targetIds = [];
            foreach ($targets as $target) {
                $count = collect($recipients)->where('grade', $target['grade'])->where('section', $target['section'])->count();
                $row = NotificationCampaignTarget::create([
                    'campaign_id' => $campaign->id,
                    'grade' => $target['grade'],
                    'section' => $target['section'],
                    'student_count' => $count,
                    'status' => 'pending',
                ]);
                $targetIds[$target['grade'] . '|' . $target['section']] = $row->id;
            }

            foreach (array_chunk($recipients, 200) as $chunk) {
                $now = now();
                NotificationCampaignRecipient::insert(array_map(function (array $row) use ($campaign, $targetIds, $now) {
                    return [
                        'campaign_id' => $campaign->id,
                        'target_id' => $targetIds[$row['grade'] . '|' . $row['section']] ?? null,
                        'user_id' => $row['user_id'],
                        'student_id' => $row['student_id'],
                        'student_name' => $row['student_name'],
                        'grade' => $row['grade'],
                        'section' => $row['section'],
                        'status_key' => $row['status_key'],
                        'delivery_status' => 'pending',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }, $chunk));
            }

            return $campaign;
        });
    }

    public function sendPending(NotificationCampaign $campaign, int $limit = 40): void
    {
        $campaign->update(['status' => 'sending']);
        $templates = SmsTemplate::query()
            ->where('branch_id', $campaign->branch_id)
            ->whereIn('id', array_values($campaign->template_map ?? []))
            ->get()
            ->keyBy('id');

        $pending = $campaign->recipients()->where('delivery_status', 'pending')->limit($limit)->get();
        $dateLabel = optional($campaign->event_date)->format('d M Y') ?: now()->format('d M Y');

        foreach ($pending as $recipient) {
            try {
                $templateId = (int) ($campaign->template_map[$recipient->status_key] ?? 0);
                $template = $templates->get($templateId);
                if (!$template) {
                    throw new \RuntimeException('Template missing for ' . $recipient->status_key);
                }
                $message = $this->renderer->render((string) $template->body, [
                    'student_name' => (string) $recipient->student_name,
                    'grade' => (string) $recipient->grade,
                    'section' => (string) $recipient->section,
                    'class_name' => trim($recipient->grade . ' ' . $recipient->section),
                    'date' => $dateLabel,
                    'attendance_date' => $dateLabel,
                    'status' => ucfirst((string) $recipient->status_key),
                ]);
                $title = ucfirst($campaign->module) . ' · ' . ucfirst((string) $recipient->status_key);
                $this->inbox->insertForUsers(
                    [(int) $recipient->user_id],
                    (int) $campaign->branch_id,
                    $title,
                    $message,
                    [
                        'source' => 'notification_campaign',
                        'campaign_id' => $campaign->id,
                        'module' => $campaign->module,
                        'status_key' => $recipient->status_key,
                        'grade' => $recipient->grade,
                        'section' => $recipient->section,
                    ],
                    $campaign->created_by,
                    'Info',
                    'Medium',
                    '/notifications'
                );
                $notificationId = DB::table('notifications')
                    ->where('user_id', $recipient->user_id)
                    ->where('created_by', $campaign->created_by)
                    ->orderByDesc('id')
                    ->value('id');
                $recipient->update([
                    'delivery_status' => 'sent',
                    'notification_id' => $notificationId,
                    'error' => null,
                ]);
            } catch (\Throwable $e) {
                $recipient->update([
                    'delivery_status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ]);
            }
        }

        $this->rollup($campaign->fresh());
    }

    public function rollup(NotificationCampaign $campaign): void
    {
        $sent = $campaign->recipients()->where('delivery_status', 'sent')->count();
        $failed = $campaign->recipients()->where('delivery_status', 'failed')->count();
        $pending = $campaign->recipients()->where('delivery_status', 'pending')->count();
        $status = 'sending';
        if ($pending === 0) {
            $status = $failed === 0 ? 'sent' : ($sent === 0 ? 'failed' : 'partial');
        }

        $campaign->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'status' => $status,
        ]);

        foreach ($campaign->targets as $target) {
            $tSent = $target->recipients()->where('delivery_status', 'sent')->count();
            $tFailed = $target->recipients()->where('delivery_status', 'failed')->count();
            $tPending = $target->recipients()->where('delivery_status', 'pending')->count();
            $targetStatus = 'pending';
            if ($tPending === 0 && ($tSent + $tFailed) > 0) {
                $targetStatus = $tFailed === 0 ? 'sent' : ($tSent === 0 ? 'failed' : 'partial');
            } elseif ($tSent > 0 || $tFailed > 0) {
                $targetStatus = 'sending';
            }
            $target->update([
                'sent_count' => $tSent,
                'failed_count' => $tFailed,
                'status' => $targetStatus,
            ]);
        }
    }

    public function markViewed(int $notificationId): void
    {
        NotificationCampaignRecipient::query()
            ->where('notification_id', $notificationId)
            ->whereNull('viewed_at')
            ->update(['viewed_at' => now()]);
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     */
    private function studentsForTargets(int $branchId, array $targets)
    {
        $query = Student::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->with(['user:id,first_name,last_name,phone']);

        $query->where(function ($q) use ($targets) {
            foreach ($targets as $target) {
                $q->orWhere(function ($inner) use ($target) {
                    $inner->where('grade', $target['grade'])->where('section', $target['section']);
                });
            }
        });

        return $query->get();
    }

    /**
     * @param  list<int>  $userIds
     * @return array<int, string>
     */
    private function statusByUser(string $module, int $branchId, string $date, $students, array $userIds): array
    {
        return match ($module) {
            'attendance' => $this->attendanceStatuses($branchId, $date, $userIds),
            'exams' => $this->examStatuses($userIds),
            'fees' => $this->feeStatuses($students, $date),
            'holidays' => array_fill_keys($userIds, 'announcement'),
            'assignments' => $this->assignmentStatuses($branchId, $date, $students),
            default => [],
        };
    }

    private function attendanceStatuses(int $branchId, string $date, array $userIds): array
    {
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
            $map[(int) $row->student_id] = match ($row->status) {
                'Absent' => 'absent',
                'Leave', 'Sick Leave' => 'leave',
                'Present', 'Late', 'Half-Day' => 'present',
                default => null,
            };
        }

        return array_filter($map);
    }

    private function examStatuses(array $userIds): array
    {
        $withMarks = ExamMark::query()->whereIn('student_id', $userIds)->pluck('student_id')->map(fn ($id) => (int) $id)->unique();
        $map = [];
        foreach ($userIds as $userId) {
            $map[$userId] = $withMarks->contains($userId) ? 'result' : 'scheduled';
        }

        return $map;
    }

    private function feeStatuses($students, string $date): array
    {
        $studentIds = $students->pluck('id')->all();
        $dues = FeeDue::query()
            ->whereIn('student_id', $studentIds)
            ->where('balance_amount', '>', 0)
            ->get(['student_id', 'due_date', 'status']);

        $byStudent = [];
        foreach ($dues as $due) {
            $key = (string) $due->student_id;
            $overdue = $due->status === 'Overdue' || ($due->due_date && $due->due_date->toDateString() < $date);
            $byStudent[$key] = $overdue ? 'overdue' : 'due';
        }

        $map = [];
        foreach ($students as $student) {
            $map[(int) $student->user_id] = $byStudent[(string) $student->id] ?? 'paid';
        }

        return $map;
    }

    private function assignmentStatuses(int $branchId, string $date, $students): array
    {
        $dueClasses = Assignment::query()
            ->where('branch_id', $branchId)
            ->where('is_published', true)
            ->whereDate('due_date', '<=', $date)
            ->get(['grade', 'section'])
            ->map(fn ($row) => $row->grade . '|' . $row->section)
            ->unique();

        $map = [];
        foreach ($students as $student) {
            $key = $student->grade . '|' . $student->section;
            $map[(int) $student->user_id] = $dueClasses->contains($key) ? 'due' : 'published';
        }

        return $map;
    }
}
