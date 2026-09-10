<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Notification;
use Illuminate\Support\Facades\Log;

class AssignmentNotificationService
{
    public function __construct(
        protected AssignmentRecipientResolver $resolver
    ) {}

    public function fanOut(Assignment $assignment, string $event = 'created'): int
    {
        $assignment->loadMissing('recipients');

        $studentIds = $assignment->recipients->pluck('student_id')->map(fn ($id) => (int) $id)->all();
        $studentUserIds = $this->resolver->studentUserIds($studentIds);
        $staff = $this->resolver->staffUserIds(
            (int) $assignment->branch_id,
            $assignment->school_id ? (int) $assignment->school_id : null,
            $assignment->created_by ? (int) $assignment->created_by : null
        );

        $due = $assignment->due_date?->format('d M Y');
        $isUpdate = $event === 'updated';
        $title = $isUpdate ? ('Updated: ' . $assignment->title) : $assignment->title;
        $message = $isUpdate
            ? ($due
                ? "Assignment updated for {$assignment->grade}-{$assignment->section}. Due {$due}."
                : "Assignment updated for {$assignment->grade}-{$assignment->section}.")
            : ($due
                ? "New assignment for {$assignment->grade}-{$assignment->section}. Due {$due}."
                : "New assignment for {$assignment->grade}-{$assignment->section}.");

        $inserted = 0;
        $inserted += $this->insertForUsers($assignment, $studentUserIds, 'student', $title, $message, $event);
        $inserted += $this->insertForUsers($assignment, $staff['teachers'], 'teacher', $title, $message, $event);
        $inserted += $this->insertForUsers($assignment, $staff['admins'], 'admin', $title, $message, $event);

        Log::info('Assignment notifications dispatched', [
            'assignment_id' => $assignment->id,
            'inserted' => $inserted,
        ]);

        return $inserted;
    }

    /**
     * @param  list<int>  $userIds
     */
    private function insertForUsers(
        Assignment $assignment,
        array $userIds,
        string $audience,
        string $title,
        string $message,
        string $event
    ): int {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'branch_id' => $assignment->branch_id,
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => 'Info',
                'priority' => $event === 'updated' ? 'High' : 'Medium',
                'status' => 'Sent',
                'read_at' => null,
                'action_url' => '/assignments/' . $assignment->id,
                'metadata' => json_encode([
                    'source' => 'assignment',
                    'assignment_id' => $assignment->id,
                    'audience' => $audience,
                    'event' => $event,
                    'group_key' => 'assignment:' . $assignment->id,
                ]),
                'sent_at' => $now,
                'created_by' => $assignment->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Notification::withoutTenantScope()->insert($chunk);
        }

        return count($rows);
    }
}
