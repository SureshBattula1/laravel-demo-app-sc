<?php

namespace App\Services;

use App\Jobs\ProcessBulkSmsChunkJob;
use App\Models\SmsBulkQueue;
use App\Models\SmsBulkRecipient;
use App\Models\Student;
use App\Models\Teacher;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use ReflectionException;
use ReflectionProperty;
use Throwable;

class SmsBulkRecipientSeedService
{
    /**
     * When the queue row says there are recipients but sms_bulk_recipients is empty, try to rebuild rows.
     * Order: meta (student_ids / teacher_ids / legacy) → serialized ProcessBulkSmsChunkJob payloads in jobs tables.
     */
    public function repairMissingRecipientsIfNeeded(SmsBulkQueue $queue, int $branchId): bool
    {
        if ($queue->recipient_count <= 0) {
            return false;
        }

        $existing = SmsBulkRecipient::query()
            ->where('sms_bulk_queue_id', $queue->id)
            ->where('branch_id', $branchId)
            ->count();
        if ($existing > 0) {
            return false;
        }

        if ($this->repairFromQueueMeta($queue, $branchId)) {
            return true;
        }

        return $this->repairFromQueuedJobPayloads($queue, $branchId);
    }

    /**
     * Re-insert sms_bulk_recipients from queue meta when rows are missing (e.g. failed seed or old sends).
     */
    public function repairFromQueueMeta(SmsBulkQueue $queue, int $branchId): bool
    {
        $meta = $queue->meta ?? [];

        if (isset($meta['student_ids']) || isset($meta['teacher_ids'])) {
            $studentIds = array_values(array_unique(array_filter(array_map('intval', $meta['student_ids'] ?? []))));
            $teacherIds = array_values(array_unique(array_filter(array_map('intval', $meta['teacher_ids'] ?? []))));
            if ($studentIds === [] && $teacherIds === []) {
                return false;
            }
            $this->seedRecipients($queue->id, $branchId, $studentIds, $teacherIds);

            return true;
        }

        if (($meta['legacy'] ?? false) && isset($meta['legacy_recipient_ids'])) {
            $ids = array_values(array_unique(array_filter(array_map('intval', $meta['legacy_recipient_ids'] ?? []))));
            if ($ids === []) {
                return false;
            }
            $kind = $meta['legacy_recipient_kind'] ?? 'student';
            if (! in_array($kind, ['student', 'teacher'], true)) {
                $kind = 'student';
            }
            $this->seedRecipientsLegacy($queue->id, $branchId, $kind, $ids);

            return true;
        }

        return false;
    }

    /**
     * Recover recipient id lists from database queue payloads (database / failed_jobs drivers).
     */
    public function repairFromQueuedJobPayloads(SmsBulkQueue $queue, int $branchId): bool
    {
        $studentIds = [];
        $teacherIds = [];

        $needle = 'ProcessBulkSmsChunkJob';

        foreach (['jobs', 'failed_jobs'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            DB::table($table)
                ->select('payload')
                ->where('payload', 'like', '%'.$needle.'%')
                ->orderBy('id')
                ->chunk(200, function ($rows) use ($queue, $branchId, &$studentIds, &$teacherIds) {
                    foreach ($rows as $row) {
                        $payload = json_decode($row->payload, true);
                        if (! is_array($payload) || empty($payload['data']['command'])) {
                            continue;
                        }
                        $job = $this->unserializeChunkJob((string) $payload['data']['command']);
                        if (! $job instanceof ProcessBulkSmsChunkJob) {
                            continue;
                        }
                        // Unserialized payloads may omit promoted properties → typed props stay uninitialized.
                        $jobQueueId = $this->readChunkJobField($job, 'queueId');
                        $jobBranchId = $this->readChunkJobField($job, 'branchId');
                        $jobRecipientType = $this->readChunkJobField($job, 'recipientType');
                        $jobRecipientIds = $this->readChunkJobField($job, 'recipientIds');
                        if (
                            $jobQueueId === null
                            || $jobBranchId === null
                            || ! is_string($jobRecipientType)
                            || ! is_array($jobRecipientIds)
                        ) {
                            continue;
                        }
                        if ($jobQueueId !== $queue->id || $jobBranchId !== $branchId) {
                            continue;
                        }
                        if ($jobRecipientType === 'teacher') {
                            foreach ($jobRecipientIds as $id) {
                                $teacherIds[] = (int) $id;
                            }
                        } else {
                            foreach ($jobRecipientIds as $id) {
                                $studentIds[] = (int) $id;
                            }
                        }
                    }
                });
        }

        $studentIds = array_values(array_unique(array_filter($studentIds)));
        $teacherIds = array_values(array_unique(array_filter($teacherIds)));

        if ($studentIds === [] && $teacherIds === []) {
            return false;
        }

        $meta = $queue->meta ?? [];
        $meta['student_ids'] = $studentIds;
        $meta['teacher_ids'] = $teacherIds;
        $queue->meta = $meta;
        $queue->save();

        $this->seedRecipients($queue->id, $branchId, $studentIds, $teacherIds);

        return true;
    }

    private function unserializeChunkJob(string $raw): ?ProcessBulkSmsChunkJob
    {
        $obj = @unserialize($raw, ['allowed_classes' => [ProcessBulkSmsChunkJob::class]]);
        if ($obj instanceof ProcessBulkSmsChunkJob) {
            return $obj;
        }

        try {
            $decrypted = Crypt::decryptString($raw);
            $obj = @unserialize($decrypted, ['allowed_classes' => [ProcessBulkSmsChunkJob::class]]);
            if ($obj instanceof ProcessBulkSmsChunkJob) {
                return $obj;
            }
        } catch (Throwable) {
            // not encrypted with app key
        }

        return null;
    }

    /**
     * Read a job property without triggering "must not be accessed before initialization" on typed props.
     */
    private function readChunkJobField(ProcessBulkSmsChunkJob $job, string $name): mixed
    {
        try {
            $rp = new ReflectionProperty($job, $name);
            if (! $rp->isInitialized($job)) {
                return null;
            }

            return $rp->getValue($job);
        } catch (ReflectionException) {
            return null;
        }
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $teacherIds
     */
    public function seedRecipients(int $queueId, int $branchId, array $studentIds, array $teacherIds): void
    {
        $now = now();
        $rows = [];

        if ($studentIds !== []) {
            $students = Student::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $studentIds)
                ->with('user')
                ->get()
                ->keyBy('id');

            foreach ($studentIds as $sid) {
                $s = $students->get($sid);
                $name = $s?->user ? trim(($s->user->first_name ?? '').' '.($s->user->last_name ?? '')) : null;
                $rows[] = [
                    'sms_bulk_queue_id' => $queueId,
                    'branch_id' => $branchId,
                    'recipient_type' => 'student',
                    'recipient_id' => $sid,
                    'recipient_name' => $name !== '' && $name !== null ? $name : null,
                    'status' => SmsBulkRecipient::STATUS_PENDING,
                    'error_message' => null,
                    'provider_meta' => null,
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($teacherIds !== []) {
            $teachers = Teacher::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $teacherIds)
                ->with('user')
                ->get()
                ->keyBy('id');

            foreach ($teacherIds as $tid) {
                $t = $teachers->get($tid);
                $name = $t?->user ? trim(($t->user->first_name ?? '').' '.($t->user->last_name ?? '')) : null;
                $rows[] = [
                    'sms_bulk_queue_id' => $queueId,
                    'branch_id' => $branchId,
                    'recipient_type' => 'teacher',
                    'recipient_id' => $tid,
                    'recipient_name' => $name !== '' && $name !== null ? $name : null,
                    'status' => SmsBulkRecipient::STATUS_PENDING,
                    'error_message' => null,
                    'provider_meta' => null,
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('sms_bulk_recipients')->insert($chunk);
        }
    }

    /**
     * @param  list<int>  $ids
     */
    public function seedRecipientsLegacy(int $queueId, int $branchId, string $recipientType, array $ids): void
    {
        $now = now();
        $rows = [];

        if ($recipientType === 'student') {
            $models = Student::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $ids)
                ->with('user')
                ->get()
                ->keyBy('id');
            foreach ($ids as $id) {
                $m = $models->get($id);
                $name = $m?->user ? trim(($m->user->first_name ?? '').' '.($m->user->last_name ?? '')) : null;
                $rows[] = [
                    'sms_bulk_queue_id' => $queueId,
                    'branch_id' => $branchId,
                    'recipient_type' => 'student',
                    'recipient_id' => $id,
                    'recipient_name' => $name !== '' && $name !== null ? $name : null,
                    'status' => SmsBulkRecipient::STATUS_PENDING,
                    'error_message' => null,
                    'provider_meta' => null,
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        } else {
            $models = Teacher::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $ids)
                ->with('user')
                ->get()
                ->keyBy('id');
            foreach ($ids as $id) {
                $m = $models->get($id);
                $name = $m?->user ? trim(($m->user->first_name ?? '').' '.($m->user->last_name ?? '')) : null;
                $rows[] = [
                    'sms_bulk_queue_id' => $queueId,
                    'branch_id' => $branchId,
                    'recipient_type' => 'teacher',
                    'recipient_id' => $id,
                    'recipient_name' => $name !== '' && $name !== null ? $name : null,
                    'status' => SmsBulkRecipient::STATUS_PENDING,
                    'error_message' => null,
                    'provider_meta' => null,
                    'sent_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('sms_bulk_recipients')->insert($chunk);
        }
    }
}
