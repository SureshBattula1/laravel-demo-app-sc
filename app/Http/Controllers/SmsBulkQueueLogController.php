<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkSmsChunkJob;
use App\Jobs\ProcessBulkWhatsAppChunkJob;
use App\Models\SmsBulkQueue;
use App\Models\SmsBulkRecipient;
use App\Services\SmsBulkRecipientSeedService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SmsBulkQueueLogController extends Controller
{
    /** @var 'sms'|'whatsapp' */
    protected string $channelFilter = 'sms';

    public function __construct(
        protected SmsBulkRecipientSeedService $recipientSeedService
    ) {}

    /**
     * Scope queues by channel. SMS log = everything that is not explicitly WhatsApp (legacy NULL/empty/typos).
     * WhatsApp log = rows created with channel whatsapp only.
     */
    protected function scopeQueueChannel(\Illuminate\Database\Eloquent\Builder $query, ?string $table = null): void
    {
        $t = $table ?? (new SmsBulkQueue)->getTable();
        if ($this->channelFilter === 'sms') {
            $query->where(function ($w) use ($t) {
                $w->whereNull($t.'.channel')
                    ->orWhere($t.'.channel', '<>', 'whatsapp');
            });
        } else {
            $query->where($t.'.channel', $this->channelFilter);
        }
    }

    protected function dispatchBulkChunkForResend(
        int $branchId,
        SmsBulkQueue $queue,
        string $recipientType,
        array $chunk
    ): void {
        $body = (string) $queue->body_template;
        $qid = (int) $queue->id;
        $prov = (string) $queue->provider;
        $ch = $queue->channel ?? 'sms';
        if ($ch === 'whatsapp') {
            ProcessBulkWhatsAppChunkJob::dispatchChunk($branchId, $prov, $recipientType, $chunk, $body, $qid);
        } else {
            ProcessBulkSmsChunkJob::dispatchChunk($branchId, $prov, $recipientType, $chunk, $body, $qid);
        }
    }

    /**
     * All accessible branches (SuperAdmin / cross-branch) or single branch via ?branch_id=.
     * Must be registered before Route::get('{id}/sms-bulk-logs', ...).
     */
    public function indexAll(Request $request): JsonResponse
    {
        $qb = $request->query('branch_id');
        if ($qb !== null && $qb !== '' && ! $this->canAccessBranch($request, (int) $qb)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return $this->paginateQueueLogs($request, null);
    }

    /** Legacy: single branch in path. */
    public function index(Request $request, int $branchId): JsonResponse
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        return $this->paginateQueueLogs($request, $branchId);
    }

    protected function paginateQueueLogs(Request $request, ?int $pathBranchId): JsonResponse
    {
        $perPage = min(max((int) $request->query('per_page', 15), 1), 100);

        $t = (new SmsBulkQueue)->getTable();
        $rec = (new SmsBulkRecipient)->getTable();
        $sent = SmsBulkRecipient::STATUS_SENT;
        $failed = SmsBulkRecipient::STATUS_FAILED;
        $skipped = SmsBulkRecipient::STATUS_SKIPPED;
        $pending = SmsBulkRecipient::STATUS_PENDING;

        $query = SmsBulkQueue::query()
            ->from($t)
            ->leftJoin('branches as br', 'br.id', '=', $t.'.branch_id')
            ->leftJoin('academic_years as ay', 'ay.id', '=', $t.'.academic_year_id');

        $this->scopeQueueChannel($query, $t);

        if ($pathBranchId !== null) {
            $query->where($t.'.branch_id', $pathBranchId);
        } else {
            $this->applyAccessibleBranchesToSmsBulkQuery($query, $request, $t);
        }

        $this->applyAcademicYearToQueueQuery($query, $request, $t);

        $query->select($t.'.*')
            ->addSelect(DB::raw('br.name as branch_name'))
            ->addSelect(DB::raw('ay.name as academic_year_name'))
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$rec} r WHERE r.sms_bulk_queue_id = {$t}.id AND r.status = ?) as recipients_sent",
                [$sent]
            )
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$rec} r WHERE r.sms_bulk_queue_id = {$t}.id AND r.status = ?) as recipients_failed",
                [$failed]
            )
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$rec} r WHERE r.sms_bulk_queue_id = {$t}.id AND r.status = ?) as recipients_skipped",
                [$skipped]
            )
            ->selectRaw(
                "(SELECT COUNT(*) FROM {$rec} r WHERE r.sms_bulk_queue_id = {$t}.id AND r.status = ?) as recipients_pending",
                [$pending]
            );

        $search = $request->query('search');
        if (is_string($search) && trim($search) !== '') {
            $term = trim($search);
            $like = '%'.$term.'%';
            $query->where(function ($w) use ($t, $like, $term) {
                $w->where($t.'.provider', 'like', $like)
                    ->orWhere($t.'.status', 'like', $like)
                    ->orWhere('br.name', 'like', $like)
                    ->orWhere('ay.name', 'like', $like);
                if (ctype_digit($term)) {
                    $w->orWhere($t.'.id', (int) $term);
                }
            });
        }

        $batchStatus = $request->query('batch_status');
        if (is_string($batchStatus) && $batchStatus !== '') {
            $allowed = ['processing', 'completed', 'queued'];
            if (in_array($batchStatus, $allowed, true)) {
                if ($batchStatus === 'processing') {
                    $query->whereIn($t.'.status', ['processing', 'queued']);
                } else {
                    $query->where($t.'.status', $batchStatus);
                }
            }
        }

        $provider = $request->query('provider');
        if (is_string($provider) && trim($provider) !== '') {
            $query->where($t.'.provider', 'like', '%'.trim($provider).'%');
        }

        $sortBy = (string) $request->query('sort_by', 'id');
        $sortDir = strtolower((string) $request->query('sort_direction', 'desc')) === 'asc' ? 'asc' : 'desc';
        $sortMap = [
            'started_display' => $t.'.created_at',
            'batch_status_label' => $t.'.status',
            'branch_name' => 'br.name',
            'academic_year_name' => 'ay.name',
            'id' => $t.'.id',
            'created_at' => $t.'.created_at',
            'status' => $t.'.status',
            'provider' => $t.'.provider',
        ];
        $col = $sortMap[$sortBy] ?? $t.'.id';
        if (! in_array($col, array_values($sortMap), true)) {
            $col = $t.'.id';
        }

        $paginator = $query
            ->orderBy($col, $sortDir)
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $paginator,
        ]);
    }

    protected function applyAccessibleBranchesToSmsBulkQuery(Builder $query, Request $request, string $t): void
    {
        $qb = $request->query('branch_id');
        if ($qb !== null && $qb !== '') {
            $query->where($t.'.branch_id', (int) $qb);

            return;
        }

        $accessible = $this->getAccessibleBranchIds($request);
        if ($accessible === 'all') {
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->whereIn($t.'.branch_id', function ($q) use ($schoolId) {
                    $q->select('id')
                        ->from('branches')
                        ->where('school_id', $schoolId)
                        ->whereNull('deleted_at');
                });
            }

            return;
        }
        if (is_array($accessible) && count($accessible) > 0) {
            $query->whereIn($t.'.branch_id', $accessible);

            return;
        }
        $query->whereRaw('1 = 0');
    }

    /**
     * Filter queue rows by academic year when the client sends ?academic_year_id= (or middleware set it).
     * Rows with NULL academic_year_id (legacy or created before backfill) are included so logs do not disappear.
     */
    protected function applyAcademicYearToQueueQuery(Builder $query, Request $request, string $t): void
    {
        $ayId = $request->query('academic_year_id');
        if ($ayId === null || $ayId === '') {
            $ayId = $request->attributes->get('academic_year_id');
        }
        if ($ayId === null || $ayId === '') {
            return;
        }
        $ayId = (int) $ayId;
        if ($ayId < 1) {
            return;
        }

        $query->where(function ($w) use ($t, $ayId) {
            $w->where($t.'.academic_year_id', $ayId)
                ->orWhereNull($t.'.academic_year_id');
        });
    }

    public function show(Request $request, int $branchId, int $queueId): JsonResponse
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $queue = SmsBulkQueue::query()
            ->where('branch_id', $branchId);
        $this->scopeQueueChannel($queue);
        $queue = $queue->findOrFail($queueId);

        $this->recipientSeedService->repairMissingRecipientsIfNeeded($queue, $branchId);
        $queue->refresh();

        $perPage = min(max((int) $request->query('per_page', 50), 1), 200);
        $status = $request->query('status');

        $q = SmsBulkRecipient::query()
            ->where('sms_bulk_queue_id', $queueId)
            ->where('branch_id', $branchId)
            ->orderBy('id');

        if (is_string($status) && $status !== '') {
            $allowed = [
                SmsBulkRecipient::STATUS_PENDING,
                SmsBulkRecipient::STATUS_SENT,
                SmsBulkRecipient::STATUS_FAILED,
                SmsBulkRecipient::STATUS_SKIPPED,
            ];
            if (in_array($status, $allowed, true)) {
                $q->where('status', $status);
            }
        }

        $recipients = $q->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'queue' => $queue,
                'recipients' => $recipients,
            ],
        ]);
    }

    public function resend(Request $request, int $branchId, int $queueId): JsonResponse
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $validator = Validator::make($request->all(), [
            'items' => 'sometimes|array|max:500',
            'items.*.recipient_type' => 'required_with:items|in:student,teacher',
            'items.*.recipient_id' => 'required_with:items|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $queue = SmsBulkQueue::query()
            ->where('branch_id', $branchId);
        $this->scopeQueueChannel($queue);
        $queue = $queue->findOrFail($queueId);

        $existingCount = SmsBulkRecipient::query()
            ->where('sms_bulk_queue_id', $queueId)
            ->where('branch_id', $branchId)
            ->count();

        if ($existingCount === 0 && $queue->recipient_count > 0) {
            $repaired = $this->recipientSeedService->repairMissingRecipientsIfNeeded($queue, $branchId);
            if (! $repaired) {
                return response()->json([
                    'success' => false,
                    'message' => 'This batch has no recipient rows stored, and recipient IDs could not be recovered (missing from batch metadata and queue job history). Send a new bulk SMS.',
                ], 422);
            }
            $queue->refresh();
        }

        $resendStatuses = [
            SmsBulkRecipient::STATUS_FAILED,
            SmsBulkRecipient::STATUS_SKIPPED,
            SmsBulkRecipient::STATUS_PENDING,
        ];

        $query = SmsBulkRecipient::query()
            ->where('sms_bulk_queue_id', $queueId)
            ->where('branch_id', $branchId);

        if ($request->filled('items')) {
            $query->where(function ($q) use ($request) {
                foreach ($request->input('items') as $item) {
                    $q->orWhere(function ($q2) use ($item) {
                        $q2->where('recipient_type', $item['recipient_type'])
                            ->where('recipient_id', (int) $item['recipient_id']);
                    });
                }
            });
            $query->whereIn('status', $resendStatuses);
        } else {
            $query->whereIn('status', $resendStatuses);
        }

        $rows = $query->get();

        if ($rows->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No failed, skipped, or pending recipients to resend.',
            ], 422);
        }

        $byType = ['student' => [], 'teacher' => []];
        foreach ($rows as $r) {
            $byType[$r->recipient_type][] = (int) $r->recipient_id;
        }

        $ids = $rows->pluck('id')->all();

        $chunkCount = count(array_chunk($byType['student'], 50)) + count(array_chunk($byType['teacher'], 50));

        DB::transaction(function () use ($queue, $ids, $chunkCount) {
            SmsBulkRecipient::query()
                ->whereIn('id', $ids)
                ->update([
                    'status' => SmsBulkRecipient::STATUS_PENDING,
                    'error_message' => null,
                    'provider_meta' => null,
                    'sent_at' => null,
                ]);

            $queue->increment('job_count', $chunkCount);
            $queue->update(['status' => 'processing']);
        });

        foreach (array_chunk($byType['student'], 50) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $this->dispatchBulkChunkForResend($branchId, $queue, 'student', $chunk);
        }
        foreach (array_chunk($byType['teacher'], 50) as $chunk) {
            if ($chunk === []) {
                continue;
            }
            $this->dispatchBulkChunkForResend($branchId, $queue, 'teacher', $chunk);
        }

        return response()->json([
            'success' => true,
            'message' => 'Resend queued.',
            'data' => [
                'recipient_count' => count($ids),
                'chunks_dispatched' => $chunkCount,
            ],
        ]);
    }
}
