<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Branch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enterprise-grade, zero-touch audit logger with Pre/Post Entity Snapshot & Coalesced Diffing.
 *
 * Guarantees:
 * 1) Any update/delete on a primary route (/api/students/{id}, /api/teachers/{id}, /api/users/{id},
 *    /api/leaves/{id}, /api/grades/{value}, /api/roles/{id}, /api/attendance/{id}, etc.) captures a
 *    flat BEFORE snapshot and compares it against the AFTER snapshot, even when controllers use
 *    DB::table()->update() or touch multiple related tables (e.g. students + users).
 * 2) Multi-model side effects within a single HTTP request are coalesced into ONE primary audit entry
 *    anchored to the route's module, preventing side-effect models (like FeeDue) from hijacking
 *    a Student update log.
 */
class SystemAuditLogger
{
    /** @var array<int, array<string, mixed>> */
    protected array $bufferedModelEvents = [];

    /** @var array<string, mixed>|null */
    protected ?array $preRequestTarget = null;

    protected bool $isRecording = false;

    protected ?bool $tableExists = null;

    protected const IGNORED_MODELS = [
        AuditLog::class,
        \App\Models\FeeAuditLog::class,
        \App\Models\UserPreference::class,
        \App\Models\Notification::class,
        \App\Models\NotificationCampaignRecipient::class,
        \App\Models\SmsBulkRecipient::class,
        \Laravel\Sanctum\PersonalAccessToken::class,
    ];

    protected const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'current_password',
        'remember_token',
        'token',
        'access_token',
        'impersonation_token',
        'secret',
        'api_key',
        'auth_token',
        'credentials',
        'otp',
    ];

    protected const IGNORED_DIFF_KEYS = [
        'id',
        'user_id',
        'user_type_id',
        'updated_at',
        'created_at',
        'deleted_at',
        'remember_token',
        'last_login',
        'profile_completion',
    ];

    public function hasAuditTable(): bool
    {
        if ($this->tableExists === null) {
            try {
                $this->tableExists = Schema::hasTable('audit_logs');
            } catch (\Throwable) {
                $this->tableExists = false;
            }
        }

        return $this->tableExists;
    }

    /**
     * Capture the state of the target record BEFORE the controller executes.
     */
    public function capturePreRequestSnapshot(Request $request): void
    {
        $this->preRequestTarget = null;
        if (! $this->hasAuditTable()) {
            return;
        }

        $method = strtoupper($request->method());
        if (! in_array($method, ['PUT', 'PATCH', 'DELETE', 'POST'], true)) {
            return;
        }

        $target = $this->resolveRouteTarget($request);
        if (! $target || empty($target['id'])) {
            return;
        }

        $snapshot = $this->loadEntitySnapshot($target['module'], $target['id'], $request);
        if ($snapshot !== null) {
            $this->preRequestTarget = array_merge($target, [
                'snapshot' => $snapshot['data'],
                'record_label' => $snapshot['label'],
                'branch_id' => $snapshot['branch_id'],
                'school_id' => $snapshot['school_id'],
                'academic_year_id' => $snapshot['academic_year_id'],
            ]);
        }
    }

    /**
     * Buffer an Eloquent model lifecycle event (created, updated, deleted, restored).
     */
    public function bufferModelEvent(string $event, Model $model): void
    {
        if ($this->isRecording || ! $this->hasAuditTable()) {
            return;
        }

        $modelClass = get_class($model);
        foreach (self::IGNORED_MODELS as $ignored) {
            if (is_a($modelClass, $ignored, true)) {
                return;
            }
        }

        $oldValues = null;
        $newValues = null;
        $changedFields = null;

        if ($event === 'created') {
            $newValues = $this->sanitizeArray($model->getAttributes());
            foreach (self::IGNORED_DIFF_KEYS as $skipKey) {
                unset($newValues[$skipKey]);
            }
            $changedFields = array_keys($newValues);
        } elseif ($event === 'updated') {
            $changes = $this->sanitizeArray($model->getChanges());
            foreach (self::IGNORED_DIFF_KEYS as $skipKey) {
                unset($changes[$skipKey]);
            }
            if (empty($changes)) {
                return;
            }
            $originals = [];
            foreach (array_keys($changes) as $key) {
                $originals[$key] = $model->getOriginal($key);
            }
            $oldValues = $this->sanitizeArray($originals);
            $newValues = $changes;
            $changedFields = array_keys($changes);
        } elseif ($event === 'deleted') {
            $oldValues = $this->sanitizeArray($model->getOriginal() ?: $model->getAttributes());
            foreach (self::IGNORED_DIFF_KEYS as $skipKey) {
                unset($oldValues[$skipKey]);
            }
            $changedFields = array_keys($oldValues);
        } elseif ($event === 'restored') {
            $newValues = $this->sanitizeArray($model->getAttributes());
            foreach (self::IGNORED_DIFF_KEYS as $skipKey) {
                unset($newValues[$skipKey]);
            }
            $changedFields = array_keys($newValues);
        }

        $shortModel = class_basename($modelClass);
        $module = $this->resolveModuleFromModel($shortModel);
        $recordLabel = $this->resolveRecordLabel($model);
        $recordId = (string) $model->getKey();

        $this->bufferedModelEvents[] = [
            'action' => $event,
            'module' => $module,
            'severity' => $this->resolveSeverity($event, $module),
            'auditable_type' => $shortModel,
            'auditable_id' => $recordId,
            'record_label' => $recordLabel,
            'description' => $this->buildModelDescription($event, $shortModel, $recordId, $recordLabel, $changedFields),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'changed_fields' => $changedFields,
            'branch_id' => $model->getAttribute('branch_id') ? (int) $model->getAttribute('branch_id') : null,
            'school_id' => $model->getAttribute('school_id') ? (int) $model->getAttribute('school_id') : null,
            'academic_year_id' => $model->getAttribute('academic_year_id') ? (int) $model->getAttribute('academic_year_id') : null,
        ];
    }

    /**
     * Flush buffered Eloquent events and/or write request-level coalesced audit log after HTTP response.
     */
    public function finalizeHttpRequest(Request $request, Response $response, float $durationMs): void
    {
        if ($this->isRecording || ! $this->hasAuditTable()) {
            return;
        }

        $method = strtoupper($request->method());
        $path = ltrim($request->path(), '/');

        if (
            str_starts_with($path, 'api/audit-logs') ||
            str_starts_with($path, 'api/health') ||
            str_starts_with($path, 'api/notifications/stream') ||
            str_starts_with($path, 'api/communications/notifications/unread-count')
        ) {
            $this->bufferedModelEvents = [];
            $this->preRequestTarget = null;

            return;
        }

        $statusCode = $response->getStatusCode();
        $isExportOrDownload = $method === 'GET' && (
            str_contains($path, '/export') ||
            str_contains($path, '/receipt') ||
            str_ends_with($path, '.pdf') ||
            str_contains($path, '/template/')
        );
        $isMutation = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if (! $isMutation && ! $isExportOrDownload && empty($this->bufferedModelEvents)) {
            $this->preRequestTarget = null;

            return;
        }

        // Skip read-only preview/lookup POST calls when nothing changed
        if ($method === 'POST' && empty($this->bufferedModelEvents) && (
            str_contains($path, '/preview') ||
            str_contains($path, '/lookup')
        )) {
            $this->preRequestTarget = null;

            return;
        }

        // Ignore failed validation (422) or 4xx/5xx on non-login mutations so failed form submits don't create false update logs
        $isLoginEndpoint = str_ends_with($path, '/login') || $path === 'api/login';
        if (! $isLoginEndpoint && $statusCode >= 400) {
            $this->bufferedModelEvents = [];
            $this->preRequestTarget = null;

            return;
        }

        $context = $this->buildRequestContext($request, $statusCode, $durationMs);

        $this->isRecording = true;
        try {
            // 1. If we captured a pre-request snapshot for this route target, compute the exact before/after diff
            if ($this->preRequestTarget !== null) {
                $snapshotEntry = $this->buildSnapshotDiffEntry($request, $method);
                if ($snapshotEntry !== null) {
                    $this->persistEntry(array_merge($context, $snapshotEntry, [
                        'branch_id' => $snapshotEntry['branch_id'] ?? $context['branch_id'],
                        'school_id' => $snapshotEntry['school_id'] ?? $context['school_id'],
                        'academic_year_id' => $snapshotEntry['academic_year_id'] ?? $context['academic_year_id'],
                    ]));

                    return;
                }
            }

            // 2. If this was a POST creation on a known entity table (e.g. Student::store using DB::table), check response ID
            if ($method === 'POST') {
                $createdEntry = $this->buildPostCreatedSnapshotEntry($request, $response);
                if ($createdEntry !== null) {
                    $this->persistEntry(array_merge($context, $createdEntry, [
                        'branch_id' => $createdEntry['branch_id'] ?? $context['branch_id'],
                        'school_id' => $createdEntry['school_id'] ?? $context['school_id'],
                        'academic_year_id' => $createdEntry['academic_year_id'] ?? $context['academic_year_id'],
                    ]));

                    return;
                }
            }

            // 3. Otherwise coalesce buffered Eloquent model events into one primary entry anchored to the route module
            if (! empty($this->bufferedModelEvents)) {
                $coalesced = $this->coalesceBufferedEvents($request, $path, $method);
                foreach ($coalesced as $item) {
                    $this->persistEntry(array_merge($context, $item, [
                        'branch_id' => $item['branch_id'] ?? $context['branch_id'],
                        'school_id' => $item['school_id'] ?? $context['school_id'],
                        'academic_year_id' => $item['academic_year_id'] ?? $context['academic_year_id'],
                    ]));
                }
            } else {
                // 4. Direct DB::table() mutation, Auth login/logout, Bulk action, or Export
                $inferred = $this->inferRequestAction($request, $path, $method, $statusCode, $isExportOrDownload);
                if ($inferred !== null) {
                    $this->persistEntry(array_merge($context, $inferred));
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Audit log capture skipped', ['error' => $e->getMessage()]);
        } finally {
            $this->bufferedModelEvents = [];
            $this->preRequestTarget = null;
            $this->isRecording = false;
        }
    }

    /**
     * Compare the pre-request snapshot with the post-request database state.
     */
    protected function buildSnapshotDiffEntry(Request $request, string $method): ?array
    {
        $target = $this->preRequestTarget;
        if (! $target) {
            return null;
        }

        $module = $target['module'];
        $id = $target['id'];
        $entityType = $target['auditable_type'];
        $before = $target['snapshot'] ?? [];
        $isRestore = str_ends_with(ltrim($request->path(), '/'), '/restore');

        if ($method === 'DELETE') {
            $cleanOld = $this->sanitizeArray($before);
            foreach (self::IGNORED_DIFF_KEYS as $skipKey) {
                unset($cleanOld[$skipKey]);
            }

            return [
                'module' => $module,
                'action' => 'deleted',
                'severity' => 'critical',
                'auditable_type' => $entityType,
                'auditable_id' => (string) $id,
                'record_label' => $target['record_label'],
                'description' => $this->buildModelDescription('deleted', $entityType, (string) $id, $target['record_label'], null),
                'old_values' => $cleanOld,
                'new_values' => null,
                'changed_fields' => array_keys($cleanOld),
                'branch_id' => $target['branch_id'],
                'school_id' => $target['school_id'],
                'academic_year_id' => $target['academic_year_id'],
            ];
        }

        $afterSnap = $this->loadEntitySnapshot($module, $id, $request);
        $after = $afterSnap['data'] ?? [];
        $label = $afterSnap['label'] ?? $target['record_label'];

        $oldDiff = [];
        $newDiff = [];
        $allKeys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($allKeys as $key) {
            if (in_array($key, self::IGNORED_DIFF_KEYS, true)) {
                continue;
            }
            $oldVal = $before[$key] ?? null;
            $newVal = $after[$key] ?? null;

            if (! $this->valuesAreEquivalent($oldVal, $newVal)) {
                $oldDiff[$key] = $oldVal;
                $newDiff[$key] = $newVal;
            }
        }

        $oldDiff = $this->sanitizeArray($oldDiff);
        $newDiff = $this->sanitizeArray($newDiff);
        $changedFields = array_keys($newDiff);

        $action = $isRestore ? 'restored' : 'updated';

        // If no DB columns differed in the snapshot, check if buffered Eloquent events on the same module had diffs
        if (empty($changedFields) && ! $isRestore) {
            foreach ($this->bufferedModelEvents as $buffered) {
                if (! empty($buffered['changed_fields']) && $buffered['module'] === $module) {
                    foreach ($buffered['changed_fields'] as $f) {
                        $oldDiff[$f] = $buffered['old_values'][$f] ?? null;
                        $newDiff[$f] = $buffered['new_values'][$f] ?? null;
                    }
                }
            }
            $changedFields = array_keys($newDiff);
        }

        if (empty($changedFields) && ! $isRestore) {
            return null;
        }

        return [
            'module' => $module,
            'action' => $action,
            'severity' => $this->resolveSeverity($action, $module),
            'auditable_type' => $entityType,
            'auditable_id' => (string) $id,
            'record_label' => $label,
            'description' => $this->buildModelDescription($action, $entityType, (string) $id, $label, $changedFields),
            'old_values' => ! empty($oldDiff) ? $oldDiff : null,
            'new_values' => ! empty($newDiff) ? $newDiff : ($isRestore ? $this->sanitizeArray($after) : null),
            'changed_fields' => $changedFields,
            'branch_id' => $afterSnap['branch_id'] ?? $target['branch_id'],
            'school_id' => $afterSnap['school_id'] ?? $target['school_id'],
            'academic_year_id' => $afterSnap['academic_year_id'] ?? $target['academic_year_id'],
        ];
    }

    /**
     * When a new Student, Teacher, User, Leave, Grade, or Role is created via POST, capture its full created fields.
     */
    protected function buildPostCreatedSnapshotEntry(Request $request, Response $response): ?array
    {
        $path = ltrim($request->path(), '/');
        $cleanPath = preg_replace('#^api/(company-portal/)?#', '', $path);
        $segments = array_values(array_filter(explode('/', (string) $cleanPath)));
        if (count($segments) !== 1) {
            return null;
        }

        $module = $this->normalizeModuleSlug($segments[0]);
        if (! in_array($module, ['students', 'teachers', 'users', 'leaves', 'grades', 'roles'], true)) {
            return null;
        }

        $content = $response->getContent();
        $decoded = is_string($content) ? json_decode($content, true) : null;
        $data = $decoded['data'] ?? null;

        $createdId = is_array($data)
            ? ($data['id'] ?? $data['student_id'] ?? $data['teacher_id'] ?? $data['value'] ?? null)
            : null;

        // Decode hashid if needed
        if ($createdId !== null && ! is_numeric($createdId) && $module !== 'grades') {
            $decodedId = app(\App\Support\IdHasher::class)->decode((string) $createdId);
            if ($decodedId !== null) {
                $createdId = $decodedId;
            }
        }

        if ($createdId === null) {
            return null;
        }

        $snap = $this->loadEntitySnapshot($module, $createdId, $request);
        if (! $snap) {
            return null;
        }

        $newValues = $this->sanitizeArray($snap['data']);
        foreach (self::IGNORED_DIFF_KEYS as $skipKey) {
            unset($newValues[$skipKey]);
        }
        // Filter out null/empty fields on creation so the Created diff only shows populated fields
        $newValues = array_filter($newValues, fn ($v) => $v !== null && $v !== '' && $v !== []);

        $entityType = $this->entityTypeForModule($module);

        return [
            'module' => $module,
            'action' => 'created',
            'severity' => $this->resolveSeverity('created', $module),
            'auditable_type' => $entityType,
            'auditable_id' => (string) $createdId,
            'record_label' => $snap['label'],
            'description' => $this->buildModelDescription('created', $entityType, (string) $createdId, $snap['label'], array_keys($newValues)),
            'old_values' => null,
            'new_values' => $newValues,
            'changed_fields' => array_keys($newValues),
            'branch_id' => $snap['branch_id'],
            'school_id' => $snap['school_id'],
            'academic_year_id' => $snap['academic_year_id'],
        ];
    }

    /**
     * Coalesce multiple buffered Eloquent events from a single HTTP request so side-effect models
     * (like FeeDue or User during a Student/Teacher action) merge into the primary route module.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function coalesceBufferedEvents(Request $request, string $path, string $method): array
    {
        if (count($this->bufferedModelEvents) <= 1) {
            return $this->bufferedModelEvents;
        }

        $cleanPath = preg_replace('#^api/(company-portal/)?#', '', $path);
        $segments = array_values(array_filter(explode('/', (string) $cleanPath)));
        $routeModule = $this->normalizeModuleSlug($segments[0] ?? 'system');

        // Find primary events matching the route's module (or merge User + Student/Teacher)
        $primaryEvents = [];
        $secondaryEvents = [];

        foreach ($this->bufferedModelEvents as $ev) {
            if ($ev['module'] === $routeModule) {
                $primaryEvents[] = $ev;
            } else {
                $secondaryEvents[] = $ev;
            }
        }

        if (empty($primaryEvents)) {
            return [$this->bufferedModelEvents[0]];
        }

        $main = $primaryEvents[0];
        $mergedOld = $main['old_values'] ?? [];
        $mergedNew = $main['new_values'] ?? [];

        foreach (array_slice($primaryEvents, 1) as $extra) {
            $mergedOld = array_merge($mergedOld, $extra['old_values'] ?? []);
            $mergedNew = array_merge($mergedNew, $extra['new_values'] ?? []);
        }

        // If route is students or teachers, also merge User field changes (first_name, last_name, email, phone)
        if (in_array($routeModule, ['students', 'teachers'], true)) {
            foreach ($secondaryEvents as $sec) {
                if ($sec['module'] === 'users') {
                    $mergedOld = array_merge($mergedOld, $sec['old_values'] ?? []);
                    $mergedNew = array_merge($mergedNew, $sec['new_values'] ?? []);
                }
            }
        }

        $changedFields = array_values(array_unique(array_merge(array_keys($mergedOld), array_keys($mergedNew))));
        $main['old_values'] = ! empty($mergedOld) ? $mergedOld : null;
        $main['new_values'] = ! empty($mergedNew) ? $mergedNew : null;
        $main['changed_fields'] = ! empty($changedFields) ? $changedFields : null;
        $main['description'] = $this->buildModelDescription(
            $main['action'],
            $main['auditable_type'] ?? ucfirst(Str::singular($routeModule)),
            (string) ($main['auditable_id'] ?? ''),
            $main['record_label'] ?? null,
            $changedFields
        );

        return [$main];
    }

    /**
     * Identify the primary module and record ID from the request route.
     *
     * @return array{module: string, auditable_type: string, id: string|int}|null
     */
    protected function resolveRouteTarget(Request $request): ?array
    {
        $path = ltrim($request->path(), '/');
        $cleanPath = preg_replace('#^api/(company-portal/)?#', '', $path);
        $segments = array_values(array_filter(explode('/', (string) $cleanPath)));

        if (count($segments) < 2) {
            return null;
        }

        $module = $this->normalizeModuleSlug($segments[0]);
        $routeParams = $request->route()?->parameters() ?? [];
        $id = $routeParams['id'] ?? $routeParams['value'] ?? $segments[1] ?? null;

        if ($id === null || in_array((string) $id, ['bulk', 'export', 'promote', 'preview-promotion', 'revert-promotion'], true)) {
            return null;
        }

        return [
            'module' => $module,
            'auditable_type' => $this->entityTypeForModule($module),
            'id' => $id,
        ];
    }

    protected function entityTypeForModule(string $module): string
    {
        return match ($module) {
            'students' => 'Student',
            'teachers' => 'Teacher',
            'users' => 'User',
            'leaves' => 'Leave',
            'grades' => 'Grade',
            'sections' => 'Section',
            'roles' => 'Role',
            'attendance' => 'StudentAttendance',
            'fees' => 'Fee',
            'exams' => 'Exam',
            'admissions' => 'AdmissionApplication',
            'branches' => 'Branch',
            'academic_years' => 'AcademicYear',
            default => ucwords(str_replace('_', ' ', Str::singular($module))),
        };
    }

    /**
     * Load a flat associative array snapshot of an entity (joining `users` for Student/Teacher).
     *
     * @return array{data: array<string, mixed>, label: ?string, branch_id: ?int, school_id: ?int, academic_year_id: ?int}|null
     */
    protected function loadEntitySnapshot(string $module, string|int $id, Request $request): ?array
    {
        try {
            if ($module === 'students') {
                $student = DB::table('students')->where('id', $id)->first();
                if (! $student) {
                    return null;
                }
                $user = $student->user_id
                    ? DB::table('users')->where('id', $student->user_id)->first(['first_name', 'last_name', 'email', 'phone', 'is_active'])
                    : null;
                $data = array_merge((array) $student, $user ? (array) $user : []);
                $label = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
                if ($label === '') {
                    $label = $data['admission_number'] ?? ('Student #'.$id);
                }

                return [
                    'data' => $data,
                    'label' => $label,
                    'branch_id' => isset($student->branch_id) ? (int) $student->branch_id : null,
                    'school_id' => isset($student->school_id) ? (int) $student->school_id : null,
                    'academic_year_id' => isset($student->academic_year_id) ? (int) $student->academic_year_id : null,
                ];
            }

            if ($module === 'teachers') {
                $teacher = DB::table('teachers')->where('id', $id)->first();
                if (! $teacher) {
                    return null;
                }
                $user = $teacher->user_id
                    ? DB::table('users')->where('id', $teacher->user_id)->first(['first_name', 'last_name', 'email', 'phone', 'role', 'is_active'])
                    : null;
                $data = array_merge((array) $teacher, $user ? (array) $user : []);
                $label = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));
                if ($label === '') {
                    $label = $data['employee_id'] ?? ('Teacher #'.$id);
                }

                return [
                    'data' => $data,
                    'label' => $label,
                    'branch_id' => isset($teacher->branch_id) ? (int) $teacher->branch_id : null,
                    'school_id' => isset($teacher->school_id) ? (int) $teacher->school_id : null,
                    'academic_year_id' => null,
                ];
            }

            if ($module === 'users') {
                $user = DB::table('users')->where('id', $id)->first();
                if (! $user) {
                    return null;
                }
                $data = (array) $user;
                $label = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? '')) ?: ($data['email'] ?? ('User #'.$id));

                return [
                    'data' => $data,
                    'label' => $label,
                    'branch_id' => isset($user->branch_id) ? (int) $user->branch_id : null,
                    'school_id' => null,
                    'academic_year_id' => null,
                ];
            }

            if ($module === 'leaves') {
                $type = $request->input('type', $request->query('type', 'student'));
                $table = $type === 'teacher' ? 'teacher_leaves' : 'student_leaves';
                $row = DB::table($table)->where('id', $id)->first();
                if (! $row) {
                    return null;
                }
                $data = (array) $row;

                return [
                    'data' => $data,
                    'label' => ($data['leave_type'] ?? 'Leave').' #'.$id,
                    'branch_id' => isset($row->branch_id) ? (int) $row->branch_id : null,
                    'school_id' => null,
                    'academic_year_id' => isset($row->academic_year_id) ? (int) $row->academic_year_id : null,
                ];
            }

            if ($module === 'attendance' && is_numeric($id)) {
                $row = DB::table('student_attendance')->where('id', (int) $id)->first();
                if (! $row) {
                    return null;
                }
                $data = (array) $row;

                return [
                    'data' => $data,
                    'label' => 'Attendance #'.$id.' ('.($data['date'] ?? '').')',
                    'branch_id' => isset($row->branch_id) ? (int) $row->branch_id : null,
                    'school_id' => isset($row->school_id) ? (int) $row->school_id : null,
                    'academic_year_id' => isset($row->academic_year_id) ? (int) $row->academic_year_id : null,
                ];
            }

            if ($module === 'roles' && is_numeric($id)) {
                $row = DB::table('roles')->where('id', (int) $id)->first();
                if (! $row) {
                    return null;
                }
                $data = (array) $row;

                return [
                    'data' => $data,
                    'label' => $data['name'] ?? ('Role #'.$id),
                    'branch_id' => null,
                    'school_id' => null,
                    'academic_year_id' => null,
                ];
            }

            if ($module === 'grades') {
                $branchId = $request->input('branch_id') ?? $request->query('branch_id') ?? $request->user()?->branch_id;
                $q = DB::table('grades')->where('value', (string) $id);
                if ($branchId && is_numeric($branchId)) {
                    $q->where('branch_id', (int) $branchId);
                }
                $row = $q->first();
                if (! $row) {
                    return null;
                }
                $data = (array) $row;

                return [
                    'data' => $data,
                    'label' => $data['label'] ?? ('Grade '.$id),
                    'branch_id' => isset($row->branch_id) ? (int) $row->branch_id : null,
                    'school_id' => isset($row->school_id) ? (int) $row->school_id : null,
                    'academic_year_id' => null,
                ];
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }

    protected function valuesAreEquivalent(mixed $a, mixed $b): bool
    {
        if ($a === $b) {
            return true;
        }
        if (($a === null || $a === '') && ($b === null || $b === '')) {
            return true;
        }
        if (is_bool($a) || is_bool($b)) {
            return (bool) $a === (bool) $b;
        }
        if (is_numeric($a) && is_numeric($b)) {
            return (float) $a === (float) $b;
        }
        // Normalize YYYY-MM-DD dates vs full timestamps
        if (is_string($a) && is_string($b) && strlen($a) >= 10 && strlen($b) >= 10) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}/', $a) && preg_match('/^\d{4}-\d{2}-\d{2}/', $b)) {
                if (substr($a, 0, 10) === substr($b, 0, 10) && (strlen($a) === 10 || strlen($b) === 10 || str_contains($a, '00:00:00') || str_contains($b, '00:00:00'))) {
                    return true;
                }
            }
        }

        return (string) $a === (string) $b;
    }

    /**
     * Explicitly record a custom domain audit event.
     */
    public function record(array $attributes, ?Request $request = null): ?AuditLog
    {
        if (! $this->hasAuditTable()) {
            return null;
        }

        $req = $request ?? request();
        $context = $req ? $this->buildRequestContext($req, 200, null) : [];

        $this->isRecording = true;
        try {
            return $this->persistEntry(array_merge($context, $attributes));
        } catch (\Throwable $e) {
            Log::warning('Custom audit log failed', ['error' => $e->getMessage()]);

            return null;
        } finally {
            $this->isRecording = false;
        }
    }

    protected function persistEntry(array $data): AuditLog
    {
        if (! empty($data['branch_id']) && empty($data['school_id'])) {
            $data['school_id'] = Branch::where('id', (int) $data['branch_id'])->value('school_id');
        }

        return AuditLog::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $data['company_id'] ?? null,
            'school_id' => $data['school_id'] ?? null,
            'branch_id' => $data['branch_id'] ?? null,
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'user_name' => $data['user_name'] ?? null,
            'user_email' => $data['user_email'] ?? null,
            'user_role' => $data['user_role'] ?? null,
            'impersonator_id' => $data['impersonator_id'] ?? null,
            'module' => $data['module'] ?? 'system',
            'action' => $data['action'] ?? 'executed',
            'severity' => $data['severity'] ?? 'info',
            'description' => Str::limit((string) ($data['description'] ?? 'System action executed'), 495),
            'auditable_type' => $data['auditable_type'] ?? null,
            'auditable_id' => isset($data['auditable_id']) ? (string) $data['auditable_id'] : null,
            'record_label' => isset($data['record_label']) ? Str::limit((string) $data['record_label'], 250) : null,
            'old_values' => $data['old_values'] ?? null,
            'new_values' => $data['new_values'] ?? null,
            'changed_fields' => $data['changed_fields'] ?? null,
            'metadata' => $data['metadata'] ?? null,
            'request_method' => $data['request_method'] ?? null,
            'request_url' => isset($data['request_url']) ? Str::limit((string) $data['request_url'], 495) : null,
            'route_name' => $data['route_name'] ?? null,
            'status_code' => $data['status_code'] ?? null,
            'duration_ms' => $data['duration_ms'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'user_agent' => $data['user_agent'] ?? null,
            'device_info' => $data['device_info'] ?? null,
            'created_at' => now(),
        ]);
    }

    protected function buildRequestContext(Request $request, int $statusCode, ?float $durationMs): array
    {
        $user = $request->user();
        $userName = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : null;
        if ($userName === '' && $user) {
            $userName = $user->name ?? $user->email ?? ('User #'.$user->id);
        }

        $branchId = $request->input('branch_id')
            ?? $request->query('branch_id')
            ?? $user?->branch_id;
        $branchId = is_numeric($branchId) ? (int) $branchId : null;

        $schoolId = null;
        try {
            $schoolId = app(TenantContext::class)->currentSchoolId();
        } catch (\Throwable) {
        }

        $companyId = $user?->company_id ? (int) $user->company_id : null;
        try {
            $companyId = $companyId ?? app(TenantContext::class)->currentCompanyId();
        } catch (\Throwable) {
        }

        $academicYearId = $request->attributes->get('academic_year_id')
            ?? (is_numeric($request->input('academic_year_id')) ? (int) $request->input('academic_year_id') : null);

        $userAgent = (string) ($request->userAgent() ?? '');

        return [
            'company_id' => $companyId,
            'school_id' => $schoolId,
            'branch_id' => $branchId,
            'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
            'user_id' => $user?->id,
            'user_name' => $userName,
            'user_email' => $user?->email,
            'user_role' => $user?->role,
            'impersonator_id' => $request->attributes->get('impersonator_id'),
            'request_method' => strtoupper($request->method()),
            'request_url' => '/'.ltrim($request->path(), '/'),
            'route_name' => $request->route()?->getName(),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'ip_address' => $request->ip(),
            'user_agent' => $userAgent !== '' ? $userAgent : null,
            'device_info' => $this->parseDeviceInfo($userAgent),
            'metadata' => [
                'query' => $this->sanitizeArray($request->query()),
                'route_params' => $this->sanitizeArray($request->route()?->parameters() ?? []),
            ],
        ];
    }

    protected function inferRequestAction(Request $request, string $path, string $method, int $statusCode, bool $isExportOrDownload): ?array
    {
        $cleanPath = preg_replace('#^api/(company-portal/)?#', '', $path);
        $segments = array_values(array_filter(explode('/', (string) $cleanPath)));
        $first = $segments[0] ?? 'system';
        $module = $this->normalizeModuleSlug($first);

        $payload = $this->sanitizeArray($request->except(['_token', '_method', 'file', 'files']));

        if ($first === 'login') {
            $failed = $statusCode >= 400;
            $loginIdentifier = (string) ($request->input('email') ?? $request->input('login') ?? 'unknown');

            return [
                'module' => 'auth',
                'action' => $failed ? 'login_failed' : 'login',
                'severity' => $failed ? 'warning' : 'info',
                'record_label' => $loginIdentifier,
                'description' => $failed
                    ? "Failed login attempt for {$loginIdentifier}"
                    : "User logged in ({$loginIdentifier})",
                'new_values' => ['login' => $loginIdentifier, 'status_code' => $statusCode],
            ];
        }

        if ($first === 'logout') {
            return [
                'module' => 'auth',
                'action' => 'logout',
                'severity' => 'info',
                'description' => 'User logged out of the session',
            ];
        }

        if ($isExportOrDownload) {
            return [
                'module' => $module,
                'action' => 'exported',
                'severity' => 'info',
                'description' => 'Exported or downloaded report from '.ucfirst(str_replace('_', ' ', $module)),
                'new_values' => $this->sanitizeArray($request->query()),
            ];
        }

        $action = match ($method) {
            'POST' => 'created',
            'PUT', 'PATCH' => 'updated',
            'DELETE' => 'deleted',
            default => 'executed',
        };

        if (str_contains($cleanPath, 'promote')) {
            $module = 'promotions';
            $action = str_contains($cleanPath, 'revert') ? 'reverted' : 'promoted';
        } elseif (str_contains($cleanPath, 'revert')) {
            $action = 'reverted';
        } elseif (str_contains($cleanPath, 'approve')) {
            $action = 'approved';
        } elseif (str_contains($cleanPath, 'reject')) {
            $action = 'rejected';
        } elseif (str_contains($cleanPath, 'bulk')) {
            $action = 'bulk_action';
        } elseif (str_contains($cleanPath, 'impersonat')) {
            $module = 'impersonation';
            $action = 'impersonated';
        }

        $routeParams = $request->route()?->parameters() ?? [];
        $recordId = $routeParams['id'] ?? $routeParams['batchId'] ?? $routeParams['value'] ?? $routeParams['userId'] ?? null;

        $humanModule = ucwords(str_replace('_', ' ', Str::singular($module)));
        $description = match ($action) {
            'created' => "Created {$humanModule} record".($recordId ? " #{$recordId}" : ''),
            'updated' => "Updated {$humanModule} record".($recordId ? " #{$recordId}" : ''),
            'deleted' => "Deleted {$humanModule} record".($recordId ? " #{$recordId}" : ''),
            'promoted' => 'Executed student promotion batch',
            'reverted' => "Reverted {$humanModule} operation".($recordId ? " ({$recordId})" : ''),
            'approved' => "Approved {$humanModule}".($recordId ? " #{$recordId}" : ''),
            'rejected' => "Rejected {$humanModule}".($recordId ? " #{$recordId}" : ''),
            'bulk_action' => "Executed bulk operation in {$humanModule}",
            'impersonated' => 'Executed school impersonation session action',
            default => "Executed {$action} in {$humanModule}",
        };

        return [
            'module' => $module,
            'action' => $action,
            'severity' => $this->resolveSeverity($action, $module),
            'auditable_type' => $humanModule,
            'auditable_id' => $recordId ? (string) $recordId : null,
            'record_label' => $payload['name'] ?? $payload['title'] ?? $payload['code'] ?? $payload['admission_number'] ?? null,
            'description' => $description,
            'new_values' => ! empty($payload) ? $payload : null,
            'changed_fields' => ! empty($payload) ? array_keys($payload) : null,
        ];
    }

    protected function resolveModuleFromModel(string $shortModel): string
    {
        return match ($shortModel) {
            'Student', 'StudentEnrollment' => 'students',
            'Teacher', 'TeacherAttachment' => 'teachers',
            'PromotionBatch', 'ClassUpgrade' => 'promotions',
            'Attendance' => 'attendance',
            'StudentLeave', 'TeacherLeave' => 'leaves',
            'FeeStructure', 'FeePayment', 'FeeType', 'FeeDue' => 'fees',
            'AccountCategory', 'Transaction', 'Invoice', 'InvoiceItem', 'Budget', 'SalaryPayment' => 'accounts',
            'Exam', 'ExamTerm', 'ExamSchedule', 'ExamMark', 'ExamResult' => 'exams',
            'AdmissionApplication' => 'admissions',
            'Assignment', 'AssignmentSubmission', 'AssignmentRecipient' => 'assignments',
            'Book', 'BookIssue', 'LibraryBookCopy', 'LibraryShelf', 'LibraryFine', 'LibraryReservation', 'LibraryProcurement', 'LibraryStockVerification' => 'library',
            'Vehicle', 'TransportDriver', 'TransportRoute', 'TransportStop', 'StudentTransport', 'TransportTrip', 'TripBoardingLog', 'TransportExpense', 'TransportFuelEntry', 'TransportMaintenanceLog' => 'transport',
            'Branch', 'BranchSetting', 'BranchSmsGatewayConfig' => 'branches',
            'BranchTransfer' => 'branch_transfers',
            'Grade' => 'grades',
            'Section', 'ClassModel' => 'sections',
            'Subject', 'SectionSubject' => 'subjects',
            'Department' => 'departments',
            'Timetable' => 'timetable',
            'Holiday' => 'holidays',
            'Event' => 'events',
            'StudentGroup', 'StudentGroupMember' => 'groups',
            'ImportHistory', 'StudentImport', 'TeacherImport' => 'imports',
            'NotificationCampaign', 'SmsTemplate', 'SmsBulkQueue', 'Announcement', 'Circular' => 'communications',
            'AcademicYear' => 'academic_years',
            'Role' => 'roles',
            'Permission', 'Module' => 'permissions',
            'User' => 'users',
            'Company', 'School', 'ImpersonationSession' => 'company_portal',
            default => Str::snake(Str::pluralStudly($shortModel)),
        };
    }

    protected function normalizeModuleSlug(string $segment): string
    {
        return match ($segment) {
            'fee-types', 'fee-structures', 'fee-payments', 'fee-dues', 'fee-reports' => 'fees',
            'exam-terms', 'exam-schedules', 'exam-marks', 'exam-results' => 'exams',
            'student-groups' => 'groups',
            'section-subjects' => 'subjects',
            'transport-drivers', 'transport-routes', 'transport-stops', 'transport-assignments', 'transport-trips', 'transport-expenses', 'transport-fuel', 'transport-maintenance', 'transport-fees', 'student-transport', 'vehicles', 'driver' => 'transport',
            'book-issues', 'books' => 'library',
            'academic-years' => 'academic_years',
            'branch-transfers' => 'branch_transfers',
            'notification-campaigns', 'send-whatsapp', 'send-sms', 'send-both' => 'communications',
            'transactions', 'invoices' => 'accounts',
            default => str_replace('-', '_', $segment),
        };
    }

    protected function resolveRecordLabel(Model $model): ?string
    {
        foreach (['name', 'title', 'full_name', 'admission_number', 'employee_id', 'code', 'invoice_number', 'receipt_number', 'application_number', 'batch_id', 'email'] as $attr) {
            $val = $model->getAttribute($attr);
            if (is_string($val) && trim($val) !== '') {
                return trim($val);
            }
        }

        if ($model->getAttribute('first_name') || $model->getAttribute('last_name')) {
            return trim(($model->getAttribute('first_name') ?? '').' '.($model->getAttribute('last_name') ?? ''));
        }

        return null;
    }

    protected function buildModelDescription(string $event, string $shortModel, string $recordId, ?string $label, ?array $changedFields): string
    {
        $humanType = ucwords(Str::snake($shortModel, ' '));
        $target = $label ? "{$humanType} \"{$label}\" (#{$recordId})" : ($recordId !== '' ? "{$humanType} #{$recordId}" : $humanType);

        if ($event === 'updated' && ! empty($changedFields)) {
            $preview = implode(', ', array_slice($changedFields, 0, 5));
            $more = count($changedFields) > 5 ? ' +'.(count($changedFields) - 5).' more' : '';

            return "Updated {$target} — modified {$preview}{$more}";
        }

        return match ($event) {
            'created' => "Created {$target}",
            'deleted' => "Deleted {$target}",
            'restored' => "Restored {$target}",
            default => ucfirst($event)." {$target}",
        };
    }

    protected function resolveSeverity(string $action, string $module): string
    {
        if (in_array($action, ['deleted', 'reverted', 'impersonated'], true)) {
            return 'critical';
        }
        if (in_array($module, ['roles', 'permissions'], true) && in_array($action, ['created', 'updated', 'deleted'], true)) {
            return 'critical';
        }
        if (in_array($action, ['updated', 'login_failed', 'rejected', 'bulk_action', 'promoted'], true)) {
            return 'warning';
        }

        return 'info';
    }

    protected function sanitizeArray(array $input): array
    {
        $clean = [];
        foreach ($input as $key => $value) {
            $lowerKey = strtolower((string) $key);
            $isSensitive = false;
            foreach (self::SENSITIVE_KEYS as $sensitive) {
                if ($lowerKey === $sensitive || str_contains($lowerKey, 'password') || str_contains($lowerKey, 'secret') || str_contains($lowerKey, 'token')) {
                    $isSensitive = true;
                    break;
                }
            }

            if ($isSensitive) {
                $clean[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $clean[$key] = $this->sanitizeArray($value);
            } elseif (is_scalar($value) || $value === null) {
                $clean[$key] = is_string($value) && strlen($value) > 1000
                    ? substr($value, 0, 1000).'...[truncated]'
                    : $value;
            }
        }

        return $clean;
    }

    protected function parseDeviceInfo(string $ua): ?string
    {
        if ($ua === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') && ! str_contains($ua, 'Chrome/') => 'Safari',
            str_contains($ua, 'okhttp') || str_contains($ua, 'Ktor') => 'Mobile App',
            default => 'Browser',
        };

        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Mac OS') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };

        return "{$browser} on {$os}";
    }
}
