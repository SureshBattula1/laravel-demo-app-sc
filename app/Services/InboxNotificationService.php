<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Notification;
use App\Models\UniversalAttachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class InboxNotificationService
{
    /** @var list<string> */
    public const INBOX_MODULE_FILTERS = [
        'attendance',
        'assignments',
        'exams',
        'fees',
        'holidays',
        'custom',
    ];

    public function applyModuleFilter(Builder $query, string $module): void
    {
        $module = strtolower(trim($module));
        if (! in_array($module, self::INBOX_MODULE_FILTERS, true)) {
            return;
        }

        match ($module) {
            'assignments' => $query->where(function (Builder $q) {
                $q->where('metadata->source', 'assignment')
                    ->orWhere(function (Builder $inner) {
                        $inner->where('metadata->source', 'notification_campaign')
                            ->where('metadata->module', 'assignments');
                    });
            }),
            'attendance' => $query->where(function (Builder $q) {
                $q->whereIn('metadata->source', ['attendance', 'attendance_notify'])
                    ->orWhere(function (Builder $inner) {
                        $inner->where('metadata->source', 'notification_campaign')
                            ->whereIn('metadata->module', ['attendance', 'teacher_attendance']);
                    });
            }),
            'custom' => $query->where(function (Builder $q) {
                $q->where('metadata->source', 'custom')
                    ->orWhere(function (Builder $inner) {
                        $inner->where('metadata->source', 'notification_campaign')
                            ->where('metadata->module', 'custom');
                    });
            }),
            default => $query->where('metadata->source', 'notification_campaign')
                ->where('metadata->module', $module),
        };
    }

    /**
     * @param  list<int>  $userIds
     * @param  array<string, mixed>  $metadata
     */
    public function insertForUsers(
        array $userIds,
        int $branchId,
        string $title,
        string $message,
        array $metadata,
        ?int $createdBy,
        string $type = 'Info',
        string $priority = 'Medium',
        ?string $actionUrl = null
    ): int {
        $userIds = array_values(array_unique(array_filter($userIds)));
        if ($userIds === []) {
            return 0;
        }

        $now = now();
        $rows = [];
        foreach ($userIds as $userId) {
            $rows[] = [
                'branch_id' => $branchId,
                'user_id' => $userId,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'priority' => $priority,
                'status' => 'Sent',
                'read_at' => null,
                'action_url' => $actionUrl,
                'metadata' => json_encode($metadata),
                'sent_at' => $now,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            Notification::withoutTenantScope()->insert($chunk);
        }

        return count($rows);
    }

    /**
     * Bulk inbox rows for campaign send chunks (per-recipient title/message).
     *
     * @param  list<array{user_id:int,title:string,message:string,metadata:array<string,mixed>}>  $items
     * @return array<int, int> recipient_id => notification_id
     */
    public function insertCampaignNotificationBatch(
        array $items,
        int $branchId,
        ?int $createdBy,
        string $sendBatchId,
    ): array {
        if ($items === []) {
            return [];
        }

        $now = now();
        $rows = [];
        foreach ($items as $item) {
            $metadata = array_merge($item['metadata'], [
                'send_batch_id' => $sendBatchId,
            ]);
            $rows[] = [
                'branch_id' => $branchId,
                'user_id' => (int) $item['user_id'],
                'title' => $item['title'],
                'message' => $item['message'],
                'type' => 'Info',
                'priority' => 'Medium',
                'status' => 'Sent',
                'read_at' => null,
                'action_url' => '/notifications',
                'metadata' => json_encode($metadata),
                'sent_at' => $now,
                'created_by' => $createdBy,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        $chunkSize = max(50, (int) config('notification_campaigns.inbox_insert_chunk_size', 200));
        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            Notification::withoutTenantScope()->insert($chunk);
        }

        $recipientIds = array_values(array_filter(array_map(
            fn (array $item) => isset($item['metadata']['recipient_id']) ? (int) $item['metadata']['recipient_id'] : 0,
            $items
        )));

        if ($recipientIds === []) {
            return [];
        }

        $inserted = Notification::withoutTenantScope()
            ->where('created_by', $createdBy)
            ->where('branch_id', $branchId)
            ->where('sent_at', $now)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.send_batch_id')) = ?", [$sendBatchId])
            ->get(['id', 'metadata']);

        $map = [];
        foreach ($inserted as $notification) {
            $meta = is_string($notification->metadata)
                ? json_decode($notification->metadata, true)
                : (array) $notification->metadata;
            $recipientId = (int) ($meta['recipient_id'] ?? 0);
            if ($recipientId > 0) {
                $map[$recipientId] = (int) $notification->id;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array{description:?string, optional_description:?string, attachments: list<array<string, mixed>>}
     */
    public function detailsForMeta(array $meta): array
    {
        $source = $meta['source'] ?? null;
        if ($source === 'custom' || $source === 'attendance_notify') {
            return [
                'description' => $meta['description'] ?? null,
                'optional_description' => $meta['optional_description'] ?? null,
                'attachments' => $source === 'custom' ? $this->attachmentsForMeta($meta) : [],
            ];
        }

        if ($source === 'notification_campaign') {
            $bits = array_filter([
                isset($meta['module']) ? ucfirst((string) $meta['module']) : null,
                isset($meta['grade'], $meta['section'])
                    ? 'Grade '.(string) $meta['grade'].' · Section '.(string) $meta['section']
                    : null,
                isset($meta['status_key'])
                    ? ucfirst(str_replace('_', ' ', (string) $meta['status_key']))
                    : null,
            ]);

            $assignmentId = isset($meta['assignment_id']) ? (int) $meta['assignment_id'] : 0;
            $assignment = $assignmentId > 0
                ? Assignment::withoutTenantScope()->find($assignmentId)
                : null;

            $campaignId = isset($meta['campaign_id']) ? (int) $meta['campaign_id'] : 0;

            return [
                'description' => $assignment?->description,
                'optional_description' => $assignment?->instructions
                    ?: ($bits === [] ? null : implode(' · ', $bits)),
                'attachments' => $campaignId > 0
                    ? $this->presentAttachments('notification_campaign', $campaignId)
                    : $this->attachmentsForMeta($meta),
            ];
        }

        $assignmentId = isset($meta['assignment_id']) ? (int) $meta['assignment_id'] : 0;
        if ($assignmentId <= 0) {
            $bits = array_filter([
                $meta['grade'] ?? null,
                $meta['section'] ?? null,
                $meta['date'] ?? null,
                $meta['status'] ?? null,
            ]);

            return [
                'description' => $bits === [] ? null : implode(' · ', $bits),
                'optional_description' => null,
                'attachments' => [],
            ];
        }

        $assignment = Assignment::withoutTenantScope()->find($assignmentId);

        return [
            'description' => $assignment?->description,
            'optional_description' => $assignment?->instructions,
            'attachments' => $this->attachmentsForMeta($meta),
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return list<array<string, mixed>>
     */
    public function attachmentsForMeta(array $meta): array
    {
        $assignmentId = isset($meta['assignment_id']) ? (int) $meta['assignment_id'] : 0;
        if ($assignmentId > 0) {
            return $this->presentAttachments('assignment', $assignmentId);
        }

        $campaignId = isset($meta['campaign_id']) ? (int) $meta['campaign_id'] : 0;
        if ($campaignId <= 0 && ! empty($meta['group_key']) && str_starts_with((string) $meta['group_key'], 'custom:')) {
            $campaignId = (int) Notification::withoutTenantScope()
                ->where('metadata->group_key', $meta['group_key'])
                ->min('id');
        }
        if ($campaignId <= 0) {
            return [];
        }

        return $this->presentAttachments('notification', $campaignId);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function syncCampaignAttachments(int $campaignId, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['file_path'])) {
                continue;
            }
            $path = ltrim((string) $item['file_path'], '/');
            UniversalAttachment::updateOrCreate(
                [
                    'module' => 'notification_campaign',
                    'module_id' => $campaignId,
                    'file_path' => $path,
                ],
                [
                    'attachment_type' => $item['attachment_type'] ?? 'document',
                    'file_name' => $item['file_name'] ?? basename($path),
                    'original_name' => $item['original_name'] ?? ($item['file_name'] ?? basename($path)),
                    'file_type' => $item['file_type'] ?? null,
                    'file_size' => isset($item['file_size']) ? (int) $item['file_size'] : null,
                    'is_active' => true,
                    'uploaded_by' => auth()->id(),
                ]
            );
        }
    }

    public function syncNotificationAttachments(int $moduleId, array $items): void
    {
        foreach ($items as $item) {
            if (! is_array($item) || empty($item['file_path'])) {
                continue;
            }
            $path = ltrim((string) $item['file_path'], '/');
            UniversalAttachment::updateOrCreate(
                [
                    'module' => 'notification',
                    'module_id' => $moduleId,
                    'file_path' => $path,
                ],
                [
                    'attachment_type' => $item['attachment_type'] ?? 'document',
                    'file_name' => $item['file_name'] ?? basename($path),
                    'original_name' => $item['original_name'] ?? ($item['file_name'] ?? basename($path)),
                    'file_type' => $item['file_type'] ?? null,
                    'file_size' => isset($item['file_size']) ? (int) $item['file_size'] : null,
                    'is_active' => true,
                    'uploaded_by' => auth()->id(),
                ]
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function presentAttachments(string $module, int $moduleId): array
    {
        return UniversalAttachment::query()
            ->where('module', $module)
            ->where('module_id', $moduleId)
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->map(function (UniversalAttachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'original_name' => $attachment->original_name,
                    'file_path' => $attachment->file_path,
                    'file_url' => Storage::disk('public')->url($attachment->file_path),
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{
     *   total:int,
     *   viewed:int,
     *   pending:int,
     *   percent:int,
     *   viewers: list<array<string, mixed>>
     * }
     */
    public function receipts(string $groupKey, int $branchId): array
    {
        $rows = Notification::withoutTenantScope()
            ->where('branch_id', $branchId)
            ->where('metadata->group_key', $groupKey)
            ->orderBy('id')
            ->get(['id', 'user_id', 'read_at', 'metadata']);

        $userIds = $rows->pluck('user_id')->unique()->filter()->values()->all();
        $users = User::query()
            ->whereIn('id', $userIds)
            ->get(['id', 'first_name', 'last_name', 'role', 'email'])
            ->keyBy('id');

        $viewers = [];
        foreach ($rows as $row) {
            $user = $users->get($row->user_id);
            $meta = is_array($row->metadata) ? $row->metadata : [];
            $viewers[] = [
                'user_id' => $row->user_id,
                'name' => $user
                    ? trim(($user->first_name ?? '').' '.($user->last_name ?? ''))
                    : 'Member',
                'role' => $user?->role,
                'audience' => $meta['audience'] ?? null,
                'viewed' => $row->read_at !== null,
                'viewed_at' => optional($row->read_at)?->toDateTimeString(),
            ];
        }

        $total = count($viewers);
        $viewed = collect($viewers)->where('viewed', true)->count();

        return [
            'group_key' => $groupKey,
            'total' => $total,
            'viewed' => $viewed,
            'pending' => max(0, $total - $viewed),
            'percent' => $total > 0 ? (int) round(($viewed / $total) * 100) : 0,
            'viewers' => $viewers,
        ];
    }
}
