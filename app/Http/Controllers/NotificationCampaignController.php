<?php

namespace App\Http\Controllers;

use App\Jobs\SendNotificationCampaignJob;
use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\SmsTemplate;
use App\Services\NotificationCampaignService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class NotificationCampaignController extends Controller
{
    public function __construct(protected NotificationCampaignService $campaigns) {}

    public function modules()
    {
        return response()->json([
            'success' => true,
            'data' => $this->campaigns->modules(),
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
                'pending' => $slice->whereIn('status', ['pending', 'sending'])->count(),
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
        if (!isset($this->campaigns->modules()[$module])) {
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
                if ($statusFilter === 'pending' && !in_array($target->status, ['pending', 'sending'], true)) {
                    continue;
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
                    'scheduled_at' => optional($campaign->scheduled_at)->toDateTimeString(),
                    'student_count' => $target->student_count,
                    'status' => $target->status,
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
                    if (!str_contains($haystack, $search)) {
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
        $campaign = NotificationCampaign::with(['branch:id,name', 'targets', 'recipients'])->findOrFail($id);
        if (!$this->canAccessBranch($request, (int) $campaign->branch_id)) {
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
                'scheduled_at' => optional($campaign->scheduled_at)->toDateTimeString(),
                'status' => $campaign->status,
                'template_map' => $campaign->template_map,
                'target_count' => $campaign->target_count,
                'recipient_count' => $campaign->recipient_count,
                'sent_count' => $campaign->sent_count,
                'failed_count' => $campaign->failed_count,
                'viewed_count' => $campaign->recipients->whereNotNull('viewed_at')->count(),
                'liked_count' => $campaign->recipients->whereNotNull('liked_at')->count(),
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
                'recipients' => $campaign->recipients->map(function ($row) use ($gradeLabels) {
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
                })->values(),
            ],
        ]);
    }

    public function templates(Request $request)
    {
        $branchId = (int) $request->query('branch_id');
        if ($branchId <= 0 || !$this->canAccessBranch($request, $branchId)) {
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
        if ($branchId <= 0 || !$this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Branch is required'], 422);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return response()->json(['success' => false, 'message' => 'Date is required'], 422);
        }

        $labels = DB::table('grades')->pluck('label', 'value');
        $rows = DB::table('student_attendance')
            ->where('branch_id', $branchId)
            ->whereDate('date', $date)
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade_level, section, COUNT(*) as marked_count')
            ->groupBy('grade_level', 'section')
            ->orderBy('grade_level')
            ->orderBy('section')
            ->get();

        $data = $rows->map(function ($row) use ($labels) {
            $grade = (string) $row->grade_level;

            return [
                'grade' => $grade,
                'section' => (string) $row->section,
                'class_name' => (string) ($labels[$grade] ?? ('Grade '.$grade)),
                'student_count' => (int) $row->marked_count,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
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
            $payload['template_map']
        );

        return response()->json(['success' => true, 'data' => $samples]);
    }

    public function store(Request $request)
    {
        $payload = $this->validated($request);
        if ($payload instanceof \Illuminate\Http\JsonResponse) {
            return $payload;
        }

        try {
            $campaign = $this->campaigns->createCampaign(
                $payload['module'],
                $payload['branch_id'],
                $payload['event_date'],
                $payload['targets'],
                $payload['template_map'],
                (int) $request->user()->id
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }

        SendNotificationCampaignJob::dispatchFor($campaign->id);

        return response()->json([
            'success' => true,
            'message' => 'Notification scheduled',
            'data' => ['id' => $campaign->id],
        ], 201);
    }

    public function like(Request $request, int $notificationId)
    {
        $user = $request->user();
        $recipient = NotificationCampaignRecipient::query()
            ->where('notification_id', $notificationId)
            ->where('user_id', $user->id)
            ->first();

        if (!$recipient) {
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
            'module' => 'required|in:' . $modules,
            'branch_id' => 'required|integer|exists:branches,id',
            'event_date' => 'nullable|date',
            'targets' => 'required|array|min:1',
            'targets.*.grade' => 'required|string|max:32',
            'targets.*.section' => 'required|string|max:32',
            'template_map' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $branchId = (int) $request->branch_id;
        if (!$this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $allowed = $this->campaigns->statusKeys($request->module);
        $map = [];
        foreach ($request->template_map as $key => $templateId) {
            if (!in_array($key, $allowed, true) || !$templateId) {
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
            if (!in_array($templateId, $validIds, true)) {
                return response()->json(['success' => false, 'message' => 'Template does not belong to this branch'], 422);
            }
        }

        return [
            'module' => $request->module,
            'branch_id' => $branchId,
            'event_date' => $request->event_date,
            'targets' => array_map(fn ($row) => [
                'grade' => (string) $row['grade'],
                'section' => (string) $row['section'],
            ], $request->targets),
            'template_map' => $map,
        ];
    }
}
