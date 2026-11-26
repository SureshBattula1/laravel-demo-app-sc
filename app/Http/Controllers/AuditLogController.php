<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\AuditLog;
use App\Services\AuditLogValueNamer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    use PaginatesAndSorts;

    /** Default list window when no date / entity / search filters (keeps counts & scans bounded). */
    private const DEFAULT_LIST_DAYS = 90;

    /** Distinct filter options are derived from this recent window only. */
    private const FILTER_OPTIONS_DAYS = 120;

    /** Columns returned for list rows (excludes heavy user_agent / metadata). */
    private const INDEX_COLUMNS = [
        'id',
        'uuid',
        'company_id',
        'school_id',
        'branch_id',
        'academic_year_id',
        'user_id',
        'user_name',
        'user_email',
        'user_role',
        'impersonator_id',
        'module',
        'action',
        'severity',
        'description',
        'auditable_type',
        'auditable_id',
        'record_label',
        'old_values',
        'new_values',
        'changed_fields',
        'request_method',
        'request_url',
        'route_name',
        'status_code',
        'duration_ms',
        'ip_address',
        'device_info',
        'signature_hash',
        'created_at',
    ];

    protected function authorizeAuditAccess(Request $request): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }
        if (in_array($user->role, ['SuperAdmin', 'BranchAdmin'], true)) {
            return true;
        }

        return method_exists($user, 'hasAnyPermission')
            && $user->hasAnyPermission(['audit_logs.view', 'audit_logs.export']);
    }

    /**
     * Roles grouped the way the audit screen filters people.
     *
     * @return array<string, list<string>>
     */
    protected function actorCategoryRoles(): array
    {
        return [
            'admin' => ['SuperAdmin', 'BranchAdmin', 'Admin', 'CompanyAdmin'],
            'teacher' => ['Teacher'],
            'staff' => ['Staff', 'Accountant', 'Librarian'],
            'students_parents' => ['Student', 'Parent'],
            'driver' => ['Driver'],
        ];
    }

    protected function applyActorCategoryFilter($query, string $category): void
    {
        $groups = $this->actorCategoryRoles();
        if ($category === 'others') {
            $known = array_merge(...array_values($groups));
            $query->where(function ($q) use ($known) {
                $q->whereNull('user_role')->orWhereNotIn('user_role', $known);
            });

            return;
        }

        if (isset($groups[$category])) {
            $query->whereIn('user_role', $groups[$category]);
        }
    }

    /**
     * Apply tenant (company / school / branch) and request filters to an AuditLog query.
     */
    protected function buildFilteredQuery(Request $request)
    {
        $query = AuditLog::query()
            ->with(['branch:id,name,code', 'academicYear:id,name']);

        $user = $request->user();
        $companyId = $this->getCurrentCompanyId($request);
        $schoolId = $this->getCurrentSchoolId($request);
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);

        if ($accessibleBranchIds === 'all') {
            if ($schoolId) {
                $query->where(function ($q) use ($schoolId, $companyId, $user) {
                    $q->where('school_id', $schoolId);
                    if ($companyId) {
                        $q->orWhere(function ($cq) use ($companyId) {
                            $cq->whereNull('school_id')->where('company_id', $companyId);
                        });
                    }
                    // Sign-in rows for this person stay visible even when no campus was known yet.
                    if ($user) {
                        $q->orWhere(function ($uq) use ($user) {
                            $uq->where('module', 'auth')->where('user_id', $user->id);
                        });
                    }
                });
            } elseif ($companyId) {
                $query->where('company_id', $companyId);
            }
        } else {
            if (! empty($accessibleBranchIds)) {
                $query->where(function ($q) use ($accessibleBranchIds, $user) {
                    $q->whereIn('branch_id', $accessibleBranchIds);
                    if ($user) {
                        $q->orWhere('user_id', $user->id);
                    }
                });
            } elseif ($user) {
                $query->where('user_id', $user->id);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('branch_id')) {
            $requestedBranch = (int) $request->branch_id;
            if ($accessibleBranchIds === 'all' || (is_array($accessibleBranchIds) && in_array($requestedBranch, array_map('intval', $accessibleBranchIds), true))) {
                $query->where('branch_id', $requestedBranch);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Only filter by academic_year_id when explicitly passed in query params
        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }

        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->user_id);
        }

        if ($request->filled('user_role')) {
            $query->where('user_role', $request->user_role);
        }

        if ($request->filled('actor_category')) {
            $this->applyActorCategoryFilter($query, (string) $request->actor_category);
        }

        if ($request->filled('auditable_type')) {
            $query->where('auditable_type', $request->auditable_type);
        }

        if ($request->filled('auditable_id')) {
            $query->where('auditable_id', (string) $request->auditable_id);
        }

        if ($request->filled('ip_address')) {
            $query->where('ip_address', 'like', trim((string) $request->ip_address).'%');
        }

        if ($request->filled('status_code')) {
            $query->where('status_code', (int) $request->status_code);
        }

        if ($request->filled('from_date')) {
            $query->where('created_at', '>=', Carbon::parse($request->from_date)->startOfDay());
        }

        if ($request->filled('to_date')) {
            $query->where('created_at', '<=', Carbon::parse($request->to_date)->endOfDay());
        }

        if ($request->filled('search')) {
            $search = trim(strip_tags((string) $request->search));
            if ($search !== '') {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $query->where(function ($q) use ($like) {
                    $q->where('description', 'like', $like)
                        ->orWhere('user_name', 'like', $like)
                        ->orWhere('user_email', 'like', $like)
                        ->orWhere('record_label', 'like', $like)
                        ->orWhere('auditable_id', 'like', $like)
                        ->orWhere('auditable_type', 'like', $like)
                        ->orWhere('ip_address', 'like', $like)
                        ->orWhere('request_url', 'like', $like);
                });
            }
        }

        return $query;
    }

    /**
     * Bound unfiltered list queries to a recent window (full history still available via date/entity/search filters).
     */
    protected function applyDefaultRecencyScope($query, Request $request): void
    {
        if ($request->filled('from_date') || $request->filled('to_date')) {
            return;
        }
        if ($request->filled('auditable_type') && $request->filled('auditable_id')) {
            return;
        }
        if ($request->filled('search')) {
            return;
        }

        $query->where(
            'created_at',
            '>=',
            Carbon::now()->subDays(self::DEFAULT_LIST_DAYS)->startOfDay()
        );
    }

    /**
     * Paginated list of audit logs.
     */
    public function index(Request $request)
    {
        if (! $this->authorizeAuditAccess($request)) {
            return $this->forbiddenResponse('You do not have permission to view audit logs');
        }

        try {
            $query = $this->buildFilteredQuery($request);
            $this->applyDefaultRecencyScope($query, $request);
            $query->select(self::INDEX_COLUMNS);

            $sortableColumns = [
                'id',
                'created_at',
                'module',
                'action',
                'severity',
                'user_name',
                'user_role',
                'status_code',
                'duration_ms',
            ];

            $logs = $this->paginateAndSort($query, $request, $sortableColumns, 'created_at', 'desc');

            // Signature verification is expensive (JSON re-hash per row); detail endpoint verifies on demand.
            $items = collect($logs->items())->map(function (AuditLog $log) {
                return $log->toArray();
            })->all();
            app(AuditLogValueNamer::class)->apply($items);

            return response()->json([
                'success' => true,
                'message' => 'Audit logs retrieved successfully',
                'data' => $items,
                'meta' => [
                    'current_page' => $logs->currentPage(),
                    'per_page' => $logs->perPage(),
                    'total' => $logs->total(),
                    'last_page' => $logs->lastPage(),
                    'from' => $logs->firstItem(),
                    'to' => $logs->lastItem(),
                    'has_more_pages' => $logs->hasMorePages(),
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit logs index error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch audit logs', $e);
        }
    }

    /**
     * KPI summary strip & activity breakdown for the Audit Log dashboard.
     */
    public function stats(Request $request)
    {
        if (! $this->authorizeAuditAccess($request)) {
            return $this->forbiddenResponse('You do not have permission to view audit logs');
        }

        try {
            $baseQuery = $this->buildFilteredQuery($request)->without(['branch', 'academicYear']);
            $this->applyDefaultRecencyScope($baseQuery, $request);

            $todayStart = Carbon::today();
            $totals = (clone $baseQuery)->selectRaw(
                'COUNT(*) as total_events, '
                .'SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as today_events, '
                .'SUM(CASE WHEN severity = "critical" THEN 1 ELSE 0 END) as critical_events, '
                .'SUM(CASE WHEN severity = "warning" THEN 1 ELSE 0 END) as warning_events, '
                .'COUNT(DISTINCT user_id) as unique_actors',
                [$todayStart]
            )->first();

            $byAction = (clone $baseQuery)
                ->select('action', DB::raw('COUNT(*) as count'))
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(8)
                ->get();

            $byModule = (clone $baseQuery)
                ->select('module', DB::raw('COUNT(*) as count'))
                ->groupBy('module')
                ->orderByDesc('count')
                ->limit(8)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'total_events' => (int) ($totals->total_events ?? 0),
                    'today_events' => (int) ($totals->today_events ?? 0),
                    'critical_events' => (int) ($totals->critical_events ?? 0),
                    'warning_events' => (int) ($totals->warning_events ?? 0),
                    'unique_actors' => (int) ($totals->unique_actors ?? 0),
                    'top_module' => $byModule->first()?->module ?? '—',
                    'by_action' => $byAction,
                    'by_module' => $byModule,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Audit logs stats error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch audit log statistics', $e);
        }
    }

    /**
     * Distinct filter options (modules, actions, roles, actors).
     */
    public function filters(Request $request)
    {
        if (! $this->authorizeAuditAccess($request)) {
            return $this->forbiddenResponse('You do not have permission to view audit logs');
        }

        try {
            $baseQuery = $this->buildFilteredQuery($request)->without(['branch', 'academicYear']);
            $windowStart = Carbon::now()->subDays(self::FILTER_OPTIONS_DAYS)->startOfDay();
            $baseQuery->where('created_at', '>=', $windowStart);

            $modules = (clone $baseQuery)->whereNotNull('module')->distinct()->orderBy('module')->pluck('module');
            $actions = (clone $baseQuery)->whereNotNull('action')->distinct()->orderBy('action')->pluck('action');
            $roles = (clone $baseQuery)->whereNotNull('user_role')->distinct()->orderBy('user_role')->pluck('user_role');
            $actors = (clone $baseQuery)
                ->whereNotNull('user_id')
                ->select(
                    'user_id',
                    DB::raw('MAX(user_name) as user_name'),
                    DB::raw('MAX(user_role) as user_role')
                )
                ->groupBy('user_id')
                ->orderBy('user_name')
                ->limit(150)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'modules' => $modules,
                    'actions' => $actions,
                    'roles' => $roles,
                    'actors' => $actors,
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to load filter options', $e);
        }
    }

    /**
     * Single audit log detail + entity timeline ("All actions on this record").
     */
    public function show(Request $request, $id)
    {
        if (! $this->authorizeAuditAccess($request)) {
            return $this->forbiddenResponse('You do not have permission to view audit logs');
        }

        try {
            $log = $this->buildFilteredQuery($request)
                ->where('id', $id)
                ->firstOrFail();

            $entityTimeline = [];
            if ($log->auditable_type && $log->auditable_id) {
                $entityTimeline = $this->buildFilteredQuery($request)
                    ->without(['branch', 'academicYear'])
                    ->where('auditable_type', $log->auditable_type)
                    ->where('auditable_id', $log->auditable_id)
                    ->orderByDesc('created_at')
                    ->limit(25)
                    ->get([
                        'id', 'uuid', 'action', 'severity', 'description',
                        'user_name', 'user_role', 'changed_fields', 'record_label',
                        'auditable_type', 'auditable_id', 'created_at',
                    ]);
            }

            $data = $log->toArray();
            $data['signature_valid'] = $log->verifySignature();
            $timeline = collect($entityTimeline)->map(fn ($entry) => $entry->toArray())->all();
            $rows = [$data];
            $namer = app(AuditLogValueNamer::class);
            $namer->apply($rows);
            $namer->apply($timeline);
            $data = $rows[0];
            $data['entity_timeline'] = $timeline;

            return response()->json([
                'success' => true,
                'data' => $data,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException) {
            return response()->json(['success' => false, 'message' => 'Audit log entry not found'], 404);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to load audit log detail', $e);
        }
    }

    /**
     * Export filtered audit logs as CSV for compliance.
     */
    public function export(Request $request): StreamedResponse|\Illuminate\Http\JsonResponse
    {
        if (! $this->authorizeAuditAccess($request)) {
            return $this->forbiddenResponse('You do not have permission to export audit logs');
        }

        $rows = $this->buildFilteredQuery($request)
            ->orderByDesc('created_at')
            ->limit(5000)
            ->get();

        $filename = 'audit-logs-'.now()->format('Y-m-d-His').'.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Timestamp',
                'Event UUID',
                'Actor Name',
                'Actor Email',
                'Role',
                'Branch',
                'Academic Year',
                'Module',
                'Action',
                'Severity',
                'Description',
                'Record Type',
                'Record ID',
                'Changed Fields',
                'HTTP Method',
                'Endpoint',
                'Status Code',
                'Duration (ms)',
                'IP Address',
                'Device',
                'Signature Verified',
            ]);

            foreach ($rows as $log) {
                fputcsv($out, [
                    $log->created_at?->toDateTimeString(),
                    $log->uuid,
                    $log->user_name ?? 'System',
                    $log->user_email ?? '',
                    $log->user_role ?? '',
                    $log->branch?->name ?? '',
                    $log->academicYear?->name ?? '',
                    $log->module,
                    $log->action,
                    $log->severity,
                    $log->description,
                    $log->auditable_type ?? '',
                    $log->auditable_id ?? '',
                    is_array($log->changed_fields) ? implode(', ', $log->changed_fields) : '',
                    $log->request_method ?? '',
                    $log->request_url ?? '',
                    $log->status_code ?? '',
                    $log->duration_ms ?? '',
                    $log->ip_address ?? '',
                    $log->device_info ?? '',
                    $log->verifySignature() ? 'Verified' : 'Mismatch',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
