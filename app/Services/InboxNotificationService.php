<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\Notification;
use App\Models\UniversalAttachment;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

class InboxNotificationService
{
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
        if ($campaignId <= 0 && !empty($meta['group_key']) && str_starts_with((string) $meta['group_key'], 'custom:')) {
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
    public function syncNotificationAttachments(int $moduleId, array $items): void
    {
        foreach ($items as $item) {
            if (!is_array($item) || empty($item['file_path'])) {
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
                    ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''))
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
