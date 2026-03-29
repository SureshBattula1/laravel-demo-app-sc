<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBulkSmsChunkJob;
use App\Jobs\ProcessBulkWhatsAppChunkJob;
use App\Models\SmsBulkQueue;
use App\Models\SmsTemplate;
use App\Models\Student;
use App\Models\StudentGroup;
use App\Models\StudentGroupMember;
use App\Models\Teacher;
use App\Services\BranchSmsActiveProviderResolver;
use App\Services\AcademicYearContext;
use App\Services\SmsBulkRecipientSeedService;
use App\Services\SmsTemplateTagContextFactory;
use App\Services\SmsTemplateTagRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class SmsBulkSendController extends Controller
{
    /** @var 'sms'|'whatsapp' */
    protected string $bulkChannel = 'sms';

    public function __construct(
        protected SmsBulkRecipientSeedService $recipientSeedService
    ) {}

    protected function resolveBulkProvider(BranchSmsActiveProviderResolver $resolver, int $branchId): ?string
    {
        return $resolver->resolve($branchId);
    }

    protected function bulkSuccessMessage(): string
    {
        return 'Bulk SMS queued.';
    }

    protected function providerMissingMessage(): string
    {
        return 'No active SMS provider for this branch. Configure and activate a provider under Account Management → SMS settings.';
    }

    /**
     * @param  list<int>  $chunk
     */
    protected function dispatchBulkChunkJob(
        int $branchId,
        string $provider,
        string $recipientType,
        array $chunk,
        string $bodyTemplate,
        ?int $queueId
    ): void {
        if ($this->bulkChannel === 'whatsapp') {
            ProcessBulkWhatsAppChunkJob::dispatchChunk($branchId, $provider, $recipientType, $chunk, $bodyTemplate, $queueId);
        } else {
            ProcessBulkSmsChunkJob::dispatchChunk($branchId, $provider, $recipientType, $chunk, $bodyTemplate, $queueId);
        }
    }

    /**
     * Teachers + grade/section tree for SMS audience UI.
     */
    public function recipientOptions(Request $request, int $branchId)
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $teachers = Teacher::query()
            ->where('branch_id', $branchId)
            ->where('teacher_status', 'Active')
            ->with(['user:id,first_name,last_name'])
            ->orderBy('id')
            ->get()
            ->map(function (Teacher $t) {
                $u = $t->user;

                return [
                    'id' => $t->id,
                    'name' => $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '',
                    'employee_id' => (string) ($t->employee_id ?? ''),
                ];
            })
            ->values();

        $totalTeachers = Teacher::query()
            ->where('branch_id', $branchId)
            ->where('teacher_status', 'Active')
            ->count();

        $totalStudents = Student::query()
            ->where('branch_id', $branchId)
            ->count();

        $rows = Student::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as c')
            ->groupBy('grade', 'section')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        $byGrade = [];
        foreach ($rows as $row) {
            $g = (string) $row->grade;
            $secRaw = $row->section;
            $sec = $secRaw === null || $secRaw === '' ? null : (string) $secRaw;
            $c = (int) $row->c;

            if (! isset($byGrade[$g])) {
                $byGrade[$g] = [
                    'grade' => $g,
                    'label' => 'Grade '.$g,
                    'student_count' => 0,
                    'sections' => [],
                ];
            }
            $byGrade[$g]['student_count'] += $c;
            if ($sec !== null) {
                $byGrade[$g]['sections'][] = [
                    'section' => $sec,
                    'student_count' => $c,
                ];
            }
        }

        foreach ($byGrade as $k => $grade) {
            usort($byGrade[$k]['sections'], fn ($a, $b) => strcmp($a['section'], $b['section']));
        }

        return response()->json([
            'success' => true,
            'data' => [
                'teachers' => $teachers,
                'grades' => array_values($byGrade),
                'meta' => [
                    'total_students' => $totalStudents,
                    'total_teachers' => $totalTeachers,
                ],
            ],
        ]);
    }

    /**
     * Typeahead list for choosing individual students by name or admission number.
     */
    public function searchStudents(Request $request, int $branchId)
    {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $raw = trim((string) $request->query('q', ''));
        if ($raw === '' || mb_strlen($raw) > 120) {
            return response()->json([
                'success' => true,
                'data' => ['students' => []],
            ]);
        }

        $escaped = addcslashes($raw, '%_\\');
        $like = '%'.$escaped.'%';

        $students = Student::query()
            ->where('branch_id', $branchId)
            ->withoutTrashed()
            ->with(['user:id,first_name,last_name'])
            ->where(function ($q) use ($like) {
                $q->where('admission_number', 'like', $like)
                    ->orWhere('registration_number', 'like', $like)
                    ->orWhereHas('user', function ($uq) use ($like) {
                        $uq->where('first_name', 'like', $like)
                            ->orWhere('last_name', 'like', $like);
                    });
            })
            ->orderBy('id')
            ->limit(25)
            ->get()
            ->map(function (Student $s) {
                $u = $s->user;
                $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '';
                if ($name === '') {
                    $name = $s->admission_number ? 'Adm '.$s->admission_number : 'Student #'.$s->id;
                }

                return [
                    'id' => $s->id,
                    'name' => $name,
                    'grade' => $s->grade !== null && $s->grade !== '' ? (string) $s->grade : '',
                    'section' => $s->section !== null && $s->section !== '' ? (string) $s->section : null,
                    'admission_number' => (string) ($s->admission_number ?? ''),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => [
                'students' => $students,
            ],
        ]);
    }

    public function store(
        Request $request,
        int $branchId,
        BranchSmsActiveProviderResolver $providerResolver
    ) {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        if ($request->filled('audience')) {
            return $this->storeWithAudience($request, $branchId, $providerResolver);
        }

        return $this->storeLegacy($request, $branchId, $providerResolver);
    }

    /**
     * Preview message body + one sample rendered SMS (same rules as send, no provider required).
     */
    public function preview(
        Request $request,
        int $branchId
    ): JsonResponse {
        if (! $this->canAccessBranch($request, $branchId)) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $built = $this->buildAudienceBulkPayload($request, $branchId);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        $renderer = app(SmsTemplateTagRenderer::class);
        $contextFactory = app(SmsTemplateTagContextFactory::class);
        [$sampleRendered, $sampleLabel] = $this->renderSampleBulkMessage(
            $branchId,
            $built['bodyTemplate'],
            $built['studentIds'],
            $built['teacherIds'],
            $renderer,
            $contextFactory
        );

        $recipientCount = count($built['studentIds']) + count($built['teacherIds']);

        return response()->json([
            'success' => true,
            'data' => [
                'body_template' => $built['bodyTemplate'],
                'sample_rendered' => $sampleRendered,
                'sample_label' => $sampleLabel,
                'recipient_count' => $recipientCount,
                'template_id' => $built['templateId'],
            ],
        ]);
    }

    /**
     * @return array{studentIds: list<int>, teacherIds: list<int>, bodyTemplate: string, templateId: int|null, audience: string}|JsonResponse
     */
    protected function buildAudienceBulkPayload(Request $request, int $branchId): array|JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'audience' => 'required|string|in:global,teachers,students',
            'template_id' => 'nullable|integer|exists:sms_templates,id',
            'body' => 'required_without:template_id|string|max:2000',
            'teacher_mode' => 'nullable|string|in:all,selected',
            'teacher_ids' => 'nullable|array',
            'teacher_ids.*' => 'integer',
            'student_mode' => 'nullable|string|in:all,filtered,selected',
            'student_ids' => 'nullable|array',
            'student_ids.*' => 'integer',
            'student_filters' => 'nullable|array',
            'student_filters.*.grade' => 'required_with:student_filters|string|max:255',
            'student_filters.*.section' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $audience = $request->input('audience');
        $bodyTemplate = $request->input('body', '');

        if ($request->filled('template_id')) {
            $template = SmsTemplate::query()
                ->where('branch_id', $branchId)
                ->whereKey((int) $request->input('template_id'))
                ->firstOrFail();
            if (! $this->templateMatchesAudience($template, $audience)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template audience does not match recipient type.',
                ], 422);
            }
            $bodyTemplate = $template->body;
        }

        if (trim($bodyTemplate) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Message body is required.',
            ], 422);
        }

        if ($audience === 'teachers') {
            $tm = $request->input('teacher_mode', 'all');
            if ($tm === 'selected') {
                $tids = array_filter(array_map('intval', $request->input('teacher_ids', [])));
                if ($tids === []) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Select at least one teacher.',
                    ], 422);
                }
            }
        }

        if ($audience === 'students' && $request->input('student_mode') === 'filtered') {
            $filters = $request->input('student_filters', []);
            if (! is_array($filters) || $filters === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Add at least one grade or section.',
                ], 422);
            }
        }

        if ($audience === 'students' && $request->input('student_mode') === 'selected') {
            $sids = array_filter(array_map('intval', $request->input('student_ids', [])));
            if ($sids === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'Select at least one student.',
                ], 422);
            }
        }

        $studentIds = [];
        $teacherIds = [];

        if ($audience === 'global') {
            $studentIds = Student::query()->where('branch_id', $branchId)->pluck('id')->map(fn ($id) => (int) $id)->all();
            $teacherIds = Teacher::query()
                ->where('branch_id', $branchId)
                ->where('teacher_status', 'Active')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        } elseif ($audience === 'teachers') {
            $mode = $request->input('teacher_mode', 'all');
            if ($mode === 'all') {
                $teacherIds = Teacher::query()
                    ->where('branch_id', $branchId)
                    ->where('teacher_status', 'Active')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            } else {
                $ids = array_map('intval', $request->input('teacher_ids', []));
                $teacherIds = Teacher::query()
                    ->where('branch_id', $branchId)
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            }
        } else {
            $mode = $request->input('student_mode', 'all');
            if ($mode === 'all') {
                $studentIds = Student::query()->where('branch_id', $branchId)->pluck('id')->map(fn ($id) => (int) $id)->all();
            } elseif ($mode === 'selected') {
                $ids = array_map('intval', $request->input('student_ids', []));
                $studentIds = Student::query()
                    ->where('branch_id', $branchId)
                    ->withoutTrashed()
                    ->whereIn('id', $ids)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->all();
            } else {
                $filters = $request->input('student_filters', []);
                if ($filters === [] || ! is_array($filters)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Add at least one grade or section filter.',
                    ], 422);
                }
                $studentIds = $this->resolveStudentIdsFromFilters($branchId, $filters);
            }
        }

        $studentIds = array_values(array_unique(array_filter($studentIds)));
        $teacherIds = array_values(array_unique(array_filter($teacherIds)));

        $recipientCount = count($studentIds) + count($teacherIds);
        if ($recipientCount === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No recipients selected.',
            ], 422);
        }

        $templateId = $request->filled('template_id') ? (int) $request->input('template_id') : null;

        return [
            'studentIds' => $studentIds,
            'teacherIds' => $teacherIds,
            'bodyTemplate' => $bodyTemplate,
            'templateId' => $templateId,
            'audience' => $audience,
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @param  list<int>  $teacherIds
     * @return array{0: string, 1: string|null}
     */
    protected function renderSampleBulkMessage(
        int $branchId,
        string $bodyTemplate,
        array $studentIds,
        array $teacherIds,
        SmsTemplateTagRenderer $renderer,
        SmsTemplateTagContextFactory $contextFactory
    ): array {
        if ($studentIds !== []) {
            $student = Student::query()
                ->where('branch_id', $branchId)
                ->whereKey($studentIds[0])
                ->with('user')
                ->first();
            if ($student !== null) {
                $ctx = $contextFactory->buildForStudent($student);
                $u = $student->user;
                $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '';

                return [
                    $renderer->render($bodyTemplate, $ctx),
                    $name !== '' ? 'Sample student: '.$name : 'Sample student',
                ];
            }
        }
        if ($teacherIds !== []) {
            $teacher = Teacher::query()
                ->where('branch_id', $branchId)
                ->whereKey($teacherIds[0])
                ->with('user')
                ->first();
            if ($teacher !== null) {
                $ctx = $contextFactory->buildForTeacher($teacher);
                $u = $teacher->user;
                $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '';

                return [
                    $renderer->render($bodyTemplate, $ctx),
                    $name !== '' ? 'Sample teacher: '.$name : 'Sample teacher',
                ];
            }
        }

        return ['', null];
    }

    protected function persistSmsBulkQueueRow(
        Request $request,
        int $branchId,
        ?string $audience,
        ?int $templateId,
        string $bodyTemplate,
        string $sampleRendered,
        ?string $sampleLabel,
        int $recipientCount,
        int $jobCount,
        string $provider,
        ?array $meta = null
    ): SmsBulkQueue {
        return SmsBulkQueue::query()->create([
            'branch_id' => $branchId,
            'academic_year_id' => app(AcademicYearContext::class)->id(false),
            'channel' => $this->bulkChannel,
            'user_id' => $request->user()?->id,
            'template_id' => $templateId,
            'audience' => $audience,
            'body_template' => $bodyTemplate,
            'sample_rendered' => $sampleRendered !== '' ? $sampleRendered : null,
            'sample_label' => $sampleLabel,
            'recipient_count' => $recipientCount,
            'job_count' => $jobCount,
            'provider' => $provider,
            'status' => 'processing',
            'meta' => $meta,
        ]);
    }

    protected function storeWithAudience(
        Request $request,
        int $branchId,
        BranchSmsActiveProviderResolver $providerResolver
    ) {
        $built = $this->buildAudienceBulkPayload($request, $branchId);
        if ($built instanceof JsonResponse) {
            return $built;
        }

        $studentIds = $built['studentIds'];
        $teacherIds = $built['teacherIds'];
        $bodyTemplate = $built['bodyTemplate'];
        $templateId = $built['templateId'];
        $audience = $built['audience'];

        $provider = $this->resolveBulkProvider($providerResolver, $branchId);
        if ($provider === null) {
            return response()->json([
                'success' => false,
                'message' => $this->providerMissingMessage(),
            ], 422);
        }

        $renderer = app(SmsTemplateTagRenderer::class);
        $contextFactory = app(SmsTemplateTagContextFactory::class);
        [$sampleRendered, $sampleLabel] = $this->renderSampleBulkMessage(
            $branchId,
            $bodyTemplate,
            $studentIds,
            $teacherIds,
            $renderer,
            $contextFactory
        );

        $recipientCount = count($studentIds) + count($teacherIds);
        $jobCount = count(array_chunk($studentIds, 50)) + count(array_chunk($teacherIds, 50));

        $queue = DB::transaction(function () use (
            $request,
            $branchId,
            $audience,
            $templateId,
            $bodyTemplate,
            $sampleRendered,
            $sampleLabel,
            $recipientCount,
            $jobCount,
            $provider,
            $studentIds,
            $teacherIds
        ) {
            $queue = $this->persistSmsBulkQueueRow(
                $request,
                $branchId,
                $audience,
                $templateId,
                $bodyTemplate,
                $sampleRendered,
                $sampleLabel,
                $recipientCount,
                $jobCount,
                $provider,
                [
                    'teacher_mode' => $request->input('teacher_mode'),
                    'student_mode' => $request->input('student_mode'),
                    'student_ids' => $studentIds,
                    'teacher_ids' => $teacherIds,
                ]
            );
            $this->recipientSeedService->seedRecipients($queue->id, $branchId, $studentIds, $teacherIds);

            return $queue;
        });

        foreach (array_chunk($studentIds, 50) as $chunk) {
            $this->dispatchBulkChunkJob($branchId, $provider, 'student', $chunk, $bodyTemplate, $queue->id);
        }
        foreach (array_chunk($teacherIds, 50) as $chunk) {
            $this->dispatchBulkChunkJob($branchId, $provider, 'teacher', $chunk, $bodyTemplate, $queue->id);
        }

        return response()->json([
            'success' => true,
            'message' => $this->bulkSuccessMessage(),
            'data' => [
                'recipient_count' => $recipientCount,
                'job_count' => $jobCount,
                'provider' => $provider,
                'queue_id' => $queue->id,
            ],
        ], 202);
    }

    /**
     * @param  list<array{grade: string, section?: string|null}>  $filters
     * @return list<int>
     */
    protected function resolveStudentIdsFromFilters(int $branchId, array $filters): array
    {
        $ids = [];
        foreach ($filters as $f) {
            $grade = trim((string) ($f['grade'] ?? ''));
            if ($grade === '') {
                continue;
            }
            $section = $f['section'] ?? null;
            $q = Student::query()->where('branch_id', $branchId)->where('grade', $grade);
            if ($section !== null && $section !== '') {
                $q->where('section', $section);
            }
            $ids = array_merge($ids, $q->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return array_values(array_unique($ids));
    }

    protected function templateMatchesAudience(SmsTemplate $template, string $audience): bool
    {
        if ($audience === 'global') {
            return $template->audience === SmsTemplate::AUDIENCE_BOTH;
        }
        if ($audience === 'teachers') {
            return $template->matchesRecipientType('teacher');
        }

        return $template->matchesRecipientType('student');
    }

    protected function storeLegacy(
        Request $request,
        int $branchId,
        BranchSmsActiveProviderResolver $providerResolver
    ) {
        $validator = Validator::make($request->all(), [
            'recipient_type' => 'required|string|in:students,teachers',
            'template_id' => 'nullable|integer|exists:sms_templates,id',
            'body' => 'required_without:template_id|string|max:2000',
            'recipient_ids' => 'nullable|array',
            'recipient_ids.*' => 'integer',
            'group_id' => 'nullable|integer|exists:student_groups,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        if ($request->filled('group_id') && $request->input('recipient_type') === 'teachers') {
            return response()->json([
                'success' => false,
                'message' => 'Student groups can only be used when recipient type is students.',
            ], 422);
        }

        $recipientType = $request->input('recipient_type') === 'students' ? 'student' : 'teacher';

        $bodyTemplate = $request->input('body', '');
        if ($request->filled('template_id')) {
            $template = SmsTemplate::query()
                ->where('branch_id', $branchId)
                ->whereKey((int) $request->input('template_id'))
                ->firstOrFail();
            if (! $template->matchesRecipientType($recipientType)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Template audience does not match recipient type.',
                ], 422);
            }
            $bodyTemplate = $template->body;
        }

        if (trim($bodyTemplate) === '') {
            return response()->json([
                'success' => false,
                'message' => 'Message body is required.',
            ], 422);
        }

        $ids = $this->resolveRecipientIds($request, $branchId, $recipientType);
        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return response()->json([
                'success' => false,
                'message' => 'No recipients selected.',
            ], 422);
        }

        $provider = $this->resolveBulkProvider($providerResolver, $branchId);
        if ($provider === null) {
            return response()->json([
                'success' => false,
                'message' => $this->providerMissingMessage(),
            ], 422);
        }

        $chunks = array_chunk($ids, 50);

        $renderer = app(SmsTemplateTagRenderer::class);
        $contextFactory = app(SmsTemplateTagContextFactory::class);
        [$sampleRendered, $sampleLabel] = $this->renderSampleForLegacy(
            $branchId,
            $bodyTemplate,
            $recipientType,
            $ids,
            $renderer,
            $contextFactory
        );

        $templateId = $request->filled('template_id') ? (int) $request->input('template_id') : null;

        $queue = DB::transaction(function () use (
            $request,
            $branchId,
            $templateId,
            $bodyTemplate,
            $sampleRendered,
            $sampleLabel,
            $ids,
            $chunks,
            $provider,
            $recipientType
        ) {
            $queue = $this->persistSmsBulkQueueRow(
                $request,
                $branchId,
                null,
                $templateId,
                $bodyTemplate,
                $sampleRendered,
                $sampleLabel,
                count($ids),
                count($chunks),
                $provider,
                [
                    'legacy' => true,
                    'recipient_type' => $request->input('recipient_type'),
                    'legacy_recipient_kind' => $recipientType,
                    'legacy_recipient_ids' => $ids,
                ]
            );
            $this->recipientSeedService->seedRecipientsLegacy($queue->id, $branchId, $recipientType, $ids);

            return $queue;
        });

        foreach ($chunks as $chunk) {
            $this->dispatchBulkChunkJob($branchId, $provider, $recipientType, $chunk, $bodyTemplate, $queue->id);
        }

        return response()->json([
            'success' => true,
            'message' => $this->bulkSuccessMessage(),
            'data' => [
                'recipient_count' => count($ids),
                'job_count' => count($chunks),
                'provider' => $provider,
                'queue_id' => $queue->id,
            ],
        ], 202);
    }

    /**
     * @param  list<int>  $ids
     * @return array{0: string, 1: string|null}
     */
    protected function renderSampleForLegacy(
        int $branchId,
        string $bodyTemplate,
        string $recipientType,
        array $ids,
        SmsTemplateTagRenderer $renderer,
        SmsTemplateTagContextFactory $contextFactory
    ): array {
        if ($ids === []) {
            return ['', null];
        }
        $firstId = $ids[0];
        if ($recipientType === 'student') {
            $student = Student::query()
                ->where('branch_id', $branchId)
                ->whereKey($firstId)
                ->with('user')
                ->first();
            if ($student === null) {
                return ['', null];
            }
            $ctx = $contextFactory->buildForStudent($student);
            $u = $student->user;
            $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '';

            return [
                $renderer->render($bodyTemplate, $ctx),
                $name !== '' ? 'Sample student: '.$name : 'Sample student',
            ];
        }

        $teacher = Teacher::query()
            ->where('branch_id', $branchId)
            ->whereKey($firstId)
            ->with('user')
            ->first();
        if ($teacher === null) {
            return ['', null];
        }
        $ctx = $contextFactory->buildForTeacher($teacher);
        $u = $teacher->user;
        $name = $u ? trim(($u->first_name ?? '').' '.($u->last_name ?? '')) : '';

        return [
            $renderer->render($bodyTemplate, $ctx),
            $name !== '' ? 'Sample teacher: '.$name : 'Sample teacher',
        ];
    }

    /**
     * @return list<int>
     */
    protected function resolveRecipientIds(Request $request, int $branchId, string $recipientType): array
    {
        $ids = array_map('intval', $request->input('recipient_ids', []));

        if ($request->filled('group_id') && $recipientType === 'student') {
            $group = StudentGroup::query()
                ->whereKey((int) $request->input('group_id'))
                ->where('branch_id', $branchId)
                ->firstOrFail();

            $memberIds = StudentGroupMember::query()
                ->where('group_id', $group->id)
                ->where('is_active', true)
                ->pluck('student_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $ids = array_merge($ids, $memberIds);
        }

        if ($recipientType === 'student') {
            $valid = Student::query()
                ->where('branch_id', $branchId)
                ->whereIn('id', $ids)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            return array_values($valid);
        }

        $valid = Teacher::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values($valid);
    }
}
