<?php

namespace App\Http\Controllers;

use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\SmsTemplate;
use App\Services\NotificationCampaignDispatchService;
use App\Services\NotificationCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationCampaignController extends Controller
{
    public function __construct(
        protected NotificationCampaignService $campaigns,
        protected NotificationCampaignDispatchService $dispatch,
    ) {}

    public function modules()
    {
        return response()->json([
            'success' => true,
            'data' => $this->campaigns->modules(),
            'meta' => $this->campaigns->modulesMeta(),
        ]);
    }

    public function eligibleTargets(Request $request)
    {
        $module = (string) $request->query('module', 'attendance');
        if (! isset($this->campaigns->modules()[$module])) {
            return response()->json(['success' => false, 'message' => 'Unknown module'], 422);
        }

        $branchId = $this->resolveBranchIdFromRequest($request);
        $date = (string) $request->query('date', '');
        if ($branchId === null || $branchId <= 0 || ! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch is required'], 422);
        }
        if ($module === 'exams') {
            // Exam notifications are scoped by exam_id, not calendar date (ignore client date).
            $resolvedDate = null;
        } elseif ($module === 'fees') {
            $resolvedDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                ? $this->campaigns->resolveEventDateForModule($module, $date)
                : null;
        } else {
            if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return response()->json(['success' => false, 'message' => 'Date is required'], 422);
            }
            $resolvedDate = $this->campaigns->resolveEventDateForModule($module, $date);
        }
        $examId = $this->resolveExamIdFromRequest($request);
        $notifyMode = trim((string) $request->query('notify_mode', ''));
        $notifyMode = in_array($notifyMode, ['scheduled', 'result'], true) ? $notifyMode : null;

        $feeNotifyMode = trim((string) $request->query('fee_notify_mode', 'due'));
        $feeNotifyMode = in_array($feeNotifyMode, ['structure', 'due'], true) ? $feeNotifyMode : 'due';
        $feeType = trim((string) $request->query('fee_type', ''));
        $feeType = $feeType !== '' ? $feeType : null;
        $feeStructureId = trim((string) $request->query('fee_structure_id', ''));
        $feeStructureId = $feeStructureId !== '' ? $feeStructureId : null;
        $academicYear = $this->campaigns->academicYearNameForCampaigns();

        if ($module === 'fees' && $feeNotifyMode === 'due') {
            $this->campaigns->warmFeesReminderCache($branchId, $academicYear);
        }

        $delivery = $this->campaigns->deliveryStatusBySection(
            $module,
            $branchId,
            $resolvedDate,
            $examId,
            $notifyMode,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
        );
        $data = $this->campaigns->eligibleTargetsWithDelivery(
            $module,
            $branchId,
            $resolvedDate,
            $examId,
            $notifyMode,
            $feeType,
            $academicYear,
            $feeNotifyMode,
            $feeStructureId,
            $delivery,
        );
        $meta = [
            'event_date' => $resolvedDate,
            'delivery_by_section' => $delivery,
        ];
        if ($module === 'attendance') {
            $meta['attendance_by_section'] = $this->campaigns->attendanceSummaryBySection($module, $branchId, $resolvedDate);
        }
        if ($module === 'exams') {
            $meta['exam_by_section'] = $this->campaigns->examSummaryBySection($module, $branchId, $resolvedDate, $examId);
            if ($notifyMode !== null) {
                $meta['exam_options'] = $this->campaigns->examNotifyOptions($branchId, $resolvedDate, $notifyMode);
            }
            $meta['exam_schedule_dates'] = $this->campaigns->examScheduleDatesForBranch($branchId);
        }
        if ($module === 'fees') {
            $meta['fee_notify_mode'] = $feeNotifyMode;
            if ($feeNotifyMode === 'structure') {
                $meta['fee_structure_options'] = $this->campaigns->feeStructureOptionsForBranch($branchId, $academicYear);
                if ($feeStructureId !== null) {
                    $meta['fee_by_section'] = $this->campaigns->feeStructureSummaryBySection($branchId, $feeStructureId);
                }
            } else {
                $meta['fee_type_options'] = $this->campaigns->feeTypeOptionsForBranch($branchId, $resolvedDate, $academicYear);
                $meta['fee_due_dates'] = $this->campaigns->feeDueDatesForBranch($branchId, $feeType, $academicYear);
                if ($resolvedDate !== null) {
                    $meta['fee_by_section'] = $this->campaigns->feeSummaryBySection(
                        $module,
                        $branchId,
                        $resolvedDate,
                        $feeType,
                        $academicYear,
                    );
                }
            }
        }
        if ($module === 'assignments' && $resolvedDate !== null) {
            $meta['assignment_status_keys'] = $this->campaigns->assignmentStatusKeysForDate($branchId, $resolvedDate);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => $meta,
        ]);
    }

    public function dashboard(Request $request)
    {
        $query = NotificationCampaign::query();
        $this->applyBranchFilter($query, $request, 'branch_id');
        $rows = $query->get(['module', 'status', 'sent_count', 'failed_count', 'recipient_count']);

        $byModule = [];
        foreach (array_keys($this->campaigns->modules()) as $module) {
            $slice = $rows->where('module', $module);
            $byModule[$module] = [
                'campaigns' => $slice->count(),
                'pending' => $slice->whereIn('status', ['pending', 'materializing', 'queued', 'sending'])->count(),
                'sent' => $slice->where('status', 'sent')->count(),
                'failed' => $slice->whereIn('status', ['failed', 'partial'])->count(),
                'recipients' => (int) $slice->sum('recipient_count'),
            ];
        }

        return response()->json(['success' => true, 'data' => $byModule]);
    }

    public function index(Request $request)
    {
        $module = (string) $request->query('module', 'attendance');
        if (! isset($this->campaigns->modules()[$module])) {
            return response()->json(['success' => false, 'message' => 'Unknown module'], 422);
        }

        $query = NotificationCampaign::query()
            ->with(['branch:id,name', 'targets'])
            ->where('module', $module);
        $this->applyBranchFilter($query, $request, 'branch_id');

        $branchId = (int) $request->query('branch_id', 0);
        if ($branchId > 0) {
            $query->where('branch_id', $branchId);
        }

        $gradeFilter = trim((string) $request->query('grade', ''));
        $sectionFilter = trim((string) $request->query('section', ''));
        $statusFilter = strtolower(trim((string) $request->query('status', '')));
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $gradeLabels = DB::table('grades')->pluck('label', 'value');

        $campaigns = $query->orderByDesc('id')->get();
        $flat = [];
        foreach ($campaigns as $campaign) {
            foreach ($campaign->targets as $target) {
                if ($gradeFilter !== '' && (string) $target->grade !== $gradeFilter) {
                    continue;
                }
                if ($sectionFilter !== '' && strcasecmp((string) $target->section, $sectionFilter) !== 0) {
                    continue;
                }
                if ($statusFilter === 'pending') {
                    $targetPending = in_array($target->status, ['pending', 'sending'], true);
                    $campaignActive = in_array($campaign->status, ['materializing', 'queued', 'sending', 'pending'], true);
                    if (! $targetPending && ! $campaignActive) {
                        continue;
                    }
                }
                if (in_array($statusFilter, ['sent', 'send'], true) && $target->status !== 'sent') {
                    continue;
                }

                $className = (string) ($gradeLabels[$target->grade] ?? ('Grade '.$target->grade));
                $row = [
                    'id' => $campaign->id,
                    'target_id' => $target->id,
                    'module' => $campaign->module,
                    'branch' => $campaign->branch?->name,
                    'branch_id' => $campaign->branch_id,
                    'grade' => $target->grade,
                    'section' => $target->section,
                    'class_name' => $className,
                    'class_display' => trim($className.($target->section ? ' · Section '.$target->section : '')),
                    'event_date' => optional($campaign->event_date)->toDateString(),
                    'scheduled_at' => optional($campaign->scheduled_at)?->utc()->toIso8601String(),
                    'student_count' => $target->student_count,
                    'status' => $target->status,
                    'campaign_status' => $campaign->status,
                    'sent_count' => $target->sent_count,
                    'failed_count' => $target->failed_count,
                ];

                if ($search !== '') {
                    $haystack = mb_strtolower(implode(' ', [
                        (string) $row['branch'],
                        $className,
                        (string) $target->grade,
                        (string) $target->section,
                        (string) $row['status'],
                    ]));
                    if (! str_contains($haystack, $search)) {
                        continue;
                    }
                }

                $flat[] = $row;
            }
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $slice = array_slice($flat, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'success' => true,
            'data' => array_values($slice),
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => count($flat),
                'last_page' => (int) max(1, ceil(count($flat) / $perPage)),
            ],
        ]);
    }

    public function show(Request $request, int $id)
    {
        $campaign = NotificationCampaign::with(['branch:id,name', 'targets'])->findOrFail($id);
        if (! $this->canAccessBranch($request, (int) $campaign->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $gradeLabels = DB::table('grades')->pluck('label', 'value');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $campaign->id,
                'module' => $campaign->module,
                'branch' => $campaign->branch?->name,
                'event_date' => optional($campaign->event_date)->toDateString(),
                'scheduled_at' => optional($campaign->scheduled_at)?->utc()->toIso8601String(),
                'status' => $campaign->status,
                'template_map' => $campaign->template_map,
                'target_count' => $campaign->target_count,
                'recipient_count' => $campaign->recipient_count,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'viewed_count' => $campaign->recipients()->whereNotNull('viewed_at')->count(),
                'liked_count' => $campaign->recipients()->whereNotNull('liked_at')->count(),
                'expected_recipient_count' => (int) $campaign->expected_recipient_count,
                'targets' => $campaign->targets->map(function ($target) use ($gradeLabels) {
                    return [
                        'id' => $target->id,
                        'grade' => $target->grade,
                        'section' => $target->section,
                        'class_name' => (string) ($gradeLabels[$target->grade] ?? ('Grade '.$target->grade)),
                        'student_count' => $target->student_count,
                        'status' => $target->status,
                        'sent_count' => $target->sent_count,
                        'failed_count' => $target->failed_count,
                    ];
                })->values(),
            ],
        ]);
    }

    public function progress(Request $request, int $id)
    {
        $campaign = NotificationCampaign::query()->findOrFail($id);
        if (! $this->canAccessBranch($request, (int) $campaign->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->progressSnapshot($campaign),
        ]);
    }

    public function recipients(Request $request, int $id)
    {
        $campaign = NotificationCampaign::query()->findOrFail($id);
        if (! $this->canAccessBranch($request, (int) $campaign->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $page = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(100, (int) $request->query('per_page', 25)));
        $deliveryStatus = trim((string) $request->query('delivery_status', ''));

        $result = $this->campaigns->paginateRecipients(
            $campaign,
            $page,
            $perPage,
            $deliveryStatus !== '' ? $deliveryStatus : null
        );

        $gradeLabels = DB::table('grades')->pluck('label', 'value');
        $data = $result['data']->map(function ($row) use ($gradeLabels) {
            $className = (string) ($gradeLabels[$row->grade] ?? ('Grade '.$row->grade));

            return [
                'id' => $row->id,
                'student_name' => $row->student_name,
                'grade' => $row->grade,
                'class_name' => $className,
                'section' => $row->section,
                'status_key' => $row->status_key,
                'delivery_status' => $row->delivery_status,
                'viewed' => $row->viewed_at !== null,
                'liked' => $row->liked_at !== null,
                'error' => $row->error,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'current_page' => $page,
                'per_page' => $perPage,
                'total' => $result['total'],
                'last_page' => (int) max(1, ceil($result['total'] / $perPage)),
            ],
        ]);
    }

    public function retryFailed(Request $request, int $id)
    {
        $campaign = NotificationCampaign::query()->findOrFail($id);
        if (! $this->canAccessBranch($request, (int) $campaign->branch_id)) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $this->campaigns->retryFailed($campaign);
        $this->dispatch->start($campaign->id);

        return response()->json(['success' => true, 'message' => 'Retry queued']);
    }

    public function classOptions(Request $request)
    {
        $branchId = $this->resolveBranchIdFromRequest($request);
        if ($branchId === null || $branchId <= 0 || ! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch is required'], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'grades' => $this->campaigns->classOptionsForBranch($branchId),
            ],
        ]);
    }

    public function feesDueNotifyMeta(Request $request)
    {
        $branchId = $this->resolveBranchIdFromRequest($request);
        if ($branchId === null || $branchId <= 0 || ! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch is required'], 422);
        }

        $feeType = trim((string) $request->query('fee_type', ''));
        $feeType = $feeType !== '' ? $feeType : null;
        $academicYear = $this->campaigns->academicYearNameForCampaigns();

        return response()->json([
            'success' => true,
            'data' => $this->campaigns->feesDueNotifyMeta($branchId, $feeType, $academicYear),
        ]);
    }

    public function templates(Request $request)
    {
        $branchId = (int) $request->query('branch_id');
        if ($branchId <= 0 || ! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch is required'], 422);
        }

        $templates = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'body', 'audience']);

        return response()->json(['success' => true, 'data' => $templates]);
    }

    public function markedAttendance(Request $request)
    {
        $branchId = (int) $request->query('branch_id');
        $date = (string) $request->query('date', '');
        if ($branchId <= 0 || ! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch is required'], 422);
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['success' => false, 'message' => 'Date is required'], 422);
        }

        $resolvedDate = $this->campaigns->resolveEventDateForModule('attendance', $date);
        $data = $this->campaigns->eligibleTargetsWithDelivery('attendance', $branchId, $resolvedDate);
        $delivery = $this->campaigns->deliveryStatusBySection('attendance', $branchId, $resolvedDate);

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => [
                'event_date' => $resolvedDate,
                'delivery_by_section' => $delivery,
                'attendance_by_section' => $this->campaigns->attendanceSummaryBySection('attendance', $branchId, $resolvedDate),
            ],
        ]);
    }

    public function preview(Request $request)
    {
        $payload = $this->validated($request);
        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        $samples = $this->campaigns->previewSamples(
            $payload['module'],
            $payload['branch_id'],
            $payload['event_date'],
            $payload['targets'],
            $payload['template_map'],
            $payload['exam_id'] ?? null,
            $payload['fee_type'] ?? null,
            $payload['fee_notify_mode'] ?? null,
            $payload['fee_structure_id'] ?? null,
            $payload['academic_year'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $samples]);
    }

    public function store(Request $request)
    {
        $payload = $this->validated($request);
        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        $campaign = $this->campaigns->createCampaign(
            $payload['module'],
            $payload['branch_id'],
            $payload['event_date'],
            $payload['targets'],
            $payload['template_map'],
            (int) $request->user()->id,
            (int) ($payload['expected_recipient_count'] ?? 0),
            $payload['exam_id'] ?? null,
            $payload['fee_type'] ?? null,
            $payload['fee_notify_mode'] ?? null,
            $payload['fee_structure_id'] ?? null,
            $payload['academic_year'] ?? null,
        );

        $this->campaigns->materializeAll($campaign);
        $campaign->refresh();
        if ($campaign->status !== 'failed') {
            $this->dispatch->start($campaign->id);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification scheduled',
            'data' => [
                'id' => $campaign->id,
                'status' => $campaign->status,
                'recipient_count' => (int) $campaign->recipient_count,
            ],
        ], 201);
    }

    public function like(Request $request, int $notificationId)
    {
        $user = $request->user();
        $recipient = NotificationCampaignRecipient::query()
            ->where('notification_id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if (! $recipient) {
            return response()->json(['success' => false, 'message' => 'Notification not found'], 404);
        }

        $recipient->update(['liked_at' => $recipient->liked_at ? null : now()]);

        return response()->json([
            'success' => true,
            'data' => ['liked' => $recipient->liked_at !== null],
        ]);
    }

    private function validated(Request $request)
    {
        $modules = implode(',', array_keys($this->campaigns->modules()));
        $validator = Validator::make($request->all(), [
            'module' => 'required|in:'.$modules,
            'branch_id' => 'required|integer|exists:branches,id',
            'event_date' => 'nullable|date',
            'targets' => 'required|array|min:1',
            'targets.*.grade' => 'required|string|max:32',
            'targets.*.section' => 'required|string|max:32',
            'template_map' => 'required|array|min:1',
            'expected_recipient_count' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $branchId = $this->resolveBranchIdFromRequest($request);
        if ($branchId === null || ! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $allowed = $this->campaigns->statusKeys($request->module);
        $map = [];
        foreach ($request->template_map as $key => $templateId) {
            if (! in_array($key, $allowed, true) || ! $templateId) {
                continue;
            }
            $map[$key] = (int) $templateId;
        }
        if ($map === []) {
            return response()->json(['success' => false, 'message' => 'Select at least one template'], 422);
        }

        $validIds = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereIn('id', array_values($map))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        foreach ($map as $templateId) {
            if (! in_array($templateId, $validIds, true)) {
                return response()->json(['success' => false, 'message' => 'Template does not belong to this branch'], 422);
            }
        }

        $eventDate = $request->event_date ? (string) $request->event_date : null;
        $dateError = $this->campaigns->validateEventDatePolicy((string) $request->module, $eventDate);
        if ($dateError !== null) {
            return response()->json(['success' => false, 'message' => $dateError], 422);
        }

        $examId = $request->input('exam_id');
        $examId = $examId !== null && $examId !== '' ? (int) $examId : null;
        if ((string) $request->module === 'exams') {
            if ($examId === null || $examId <= 0) {
                return response()->json(['success' => false, 'message' => 'Exam is required'], 422);
            }
            $exam = \App\Models\Exam::query()->find($examId);
            if (! $exam || (int) $exam->branch_id !== $branchId) {
                return response()->json(['success' => false, 'message' => 'Invalid exam for this branch'], 422);
            }
        }

        $feeNotifyMode = trim((string) $request->input('fee_notify_mode', 'due'));
        $feeNotifyMode = in_array($feeNotifyMode, ['structure', 'due'], true) ? $feeNotifyMode : 'due';
        $feeType = trim((string) $request->input('fee_type', ''));
        $feeType = $feeType !== '' ? $feeType : null;
        $feeStructureId = trim((string) $request->input('fee_structure_id', ''));
        $feeStructureId = $feeStructureId !== '' ? $feeStructureId : null;

        if ((string) $request->module === 'fees') {
            if ($eventDate === null || $eventDate === '') {
                return response()->json(['success' => false, 'message' => 'Due date is required'], 422);
            }
            $academicYear = $this->campaigns->academicYearNameForCampaigns();
            $feesPlugin = app(\App\NotificationCampaigns\Modules\FeesCampaignModule::class);
            if ($feeNotifyMode === 'structure') {
                if ($feeStructureId === null) {
                    return response()->json(['success' => false, 'message' => 'Fee structure is required'], 422);
                }
                $structure = $feesPlugin->findStructureForBranch($branchId, $feeStructureId);
                if (! $structure) {
                    return response()->json(['success' => false, 'message' => 'Invalid fee structure for this branch'], 422);
                }
            } else {
                if ($feeType === null) {
                    return response()->json(['success' => false, 'message' => 'Fee type is required'], 422);
                }
                if (! $feesPlugin->hasOpenDuesForBranch($branchId, $eventDate, $feeType, $academicYear)) {
                    return response()->json(['success' => false, 'message' => 'No unpaid dues for this fee type and due date'], 422);
                }
            }
        }

        $academicYear = $this->campaigns->academicYearNameForCampaigns();

        return [
            'module' => $request->module,
            'branch_id' => $branchId,
            'academic_year' => $academicYear,
            'event_date' => $request->event_date,
            'targets' => array_map(fn ($row) => [
                'grade' => (string) $row['grade'],
                'section' => (string) $row['section'],
            ], $request->targets),
            'template_map' => $map,
            'expected_recipient_count' => (int) $request->input('expected_recipient_count', 0),
            'exam_id' => (string) $request->module === 'exams' ? $examId : null,
            'fee_type' => (string) $request->module === 'fees' && $feeNotifyMode === 'due' ? $feeType : null,
            'fee_notify_mode' => (string) $request->module === 'fees' ? $feeNotifyMode : null,
            'fee_structure_id' => (string) $request->module === 'fees' && $feeNotifyMode === 'structure' ? $feeStructureId : null,
        ];
    }

    private function resolveBranchIdFromRequest(Request $request): ?int
    {
        $raw = $request->query('branch_id') ?? $request->input('branch_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw) && ! ctype_digit($raw)) {
            $decoded = app(\App\Support\IdHasher::class)->decode($raw);

            return $decoded ?: null;
        }

        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }

    private function resolveExamIdFromRequest(Request $request): ?int
    {
        $raw = $request->query('exam_id') ?? $request->input('exam_id');
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw) && ! ctype_digit($raw)) {
            $decoded = app(\App\Support\IdHasher::class)->decode($raw);

            return $decoded ?: null;
        }
        $id = (int) $raw;

        return $id > 0 ? $id : null;
    }
}
