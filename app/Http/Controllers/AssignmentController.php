<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Jobs\DispatchAssignmentNotificationsJob;
use App\Models\Assignment;
use App\Models\Branch;
use App\Models\Student;
use App\Models\Subject;
use App\Models\UniversalAttachment;
use App\Services\AcademicYearContext;
use App\Services\AssignmentRecipientResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AssignmentController extends Controller
{
    use PaginatesAndSorts;

    public function __construct(
        protected AcademicYearContext $academicYearContext,
        protected AssignmentRecipientResolver $recipientResolver
    ) {}

    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = Assignment::query()
                ->with(['subject:id,name,code', 'teacher:id,first_name,last_name'])
                ->withCount(['recipients', 'submissions']);

            $this->applyBranchFilter($query, $request, 'branch_id', 'school_id');

            if ($user->role === 'Student') {
                $studentId = Student::where('user_id', $user->id)->value('id');
                $query->where('is_published', true);
                if ($studentId) {
                    $query->whereHas('recipients', fn ($q) => $q->where('student_id', $studentId));
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($user->role === 'Teacher') {
                $query->where(function ($q) use ($user) {
                    $q->where('teacher_id', $user->id)
                        ->orWhere('created_by', $user->id)
                        ->orWhere('is_published', true);
                });
            }

            if ($request->filled('grade')) {
                $query->where('grade', strip_tags($request->grade));
            }
            if ($request->filled('section')) {
                $query->where('section', strip_tags($request->section));
            }
            if ($request->filled('subject_id')) {
                $query->where('subject_id', (int) $request->subject_id);
            }
            if ($request->has('is_published')) {
                $query->where('is_published', $request->boolean('is_published'));
            }

            $academicYearId = $this->academicYearContext->id(false);
            if ($academicYearId && Schema::hasColumn('assignments', 'academic_year_id')) {
                $query->where(function ($q) use ($academicYearId) {
                    $q->where('academic_year_id', $academicYearId)
                        ->orWhereNull('academic_year_id');
                });
            }

            $paginator = $this->paginateAndSort(
                $query,
                $request,
                ['due_date', 'created_at', 'title', 'grade'],
                'due_date',
                'desc'
            );

            $viewerId = $request->user()?->id;
            $data = collect($paginator->items())->map(fn (Assignment $a) => $this->present($a, false, $viewerId))->values();

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                    'has_more_pages' => $paginator->hasMorePages(),
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch assignments', $e);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $assignment = Assignment::with(['subject:id,name,code', 'teacher:id,first_name,last_name', 'recipients'])
                ->withCount(['recipients', 'submissions'])
                ->findOrFail($id);

            if (!$this->canViewAssignment($request, $assignment)) {
                return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $this->present($assignment, true, $request->user()?->id),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch assignment', $e);
        }
    }

    public function eligibleStudents(Request $request)
    {
        try {
            if (!$this->canCreateAssignments($request->user())) {
                return $this->forbiddenResponse('You cannot list assignment students');
            }

            $validator = Validator::make($request->all(), [
                'grade' => 'required|string',
                'section' => 'required|string',
                'branch_id' => 'nullable|exists:branches,id',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $branchId = $this->resolveWritableBranchId($request, $request->branch_id);
            if (!$branchId) {
                return response()->json(['success' => false, 'message' => 'branch_id is required'], 422);
            }
            if (!$this->canManageBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            $students = $this->recipientResolver->eligibleStudents(
                $branchId,
                strip_tags($request->grade),
                strip_tags($request->section),
                $this->academicYearContext->id(false)
            );

            return response()->json(['success' => true, 'data' => $students]);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch eligible students', $e);
        }
    }

    public function previewRecipients(Request $request)
    {
        try {
            if (!$this->canCreateAssignments($request->user())) {
                return $this->forbiddenResponse('You cannot preview assignment recipients');
            }

            $validator = Validator::make($request->all(), [
                'grade' => 'required|string',
                'section' => 'required|string',
                'audience_mode' => 'required|in:all,custom',
                'student_ids' => 'nullable|array',
                'student_ids.*' => 'integer',
                'branch_id' => 'nullable|exists:branches,id',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $branchId = $this->resolveWritableBranchId($request, $request->branch_id);
            if (!$branchId || !$this->canManageBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            $studentIds = $this->recipientResolver->resolveStudentIds(
                $branchId,
                strip_tags($request->grade),
                strip_tags($request->section),
                $this->academicYearContext->id(false),
                $request->audience_mode,
                $request->student_ids
            );
            $schoolId = $this->schoolIdForBranch($branchId);
            $staff = $this->recipientResolver->staffUserIds($branchId, $schoolId, $request->user()->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'students' => count($studentIds),
                    'teachers' => count($staff['teachers']),
                    'admins' => count($staff['admins']),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to preview recipients', $e);
        }
    }

    public function store(Request $request)
    {
        try {
            if (!$this->canCreateAssignments($request->user())) {
                return $this->forbiddenResponse('You cannot create assignments');
            }

            $validator = Validator::make($request->all(), [
                'branch_id' => 'nullable|exists:branches,id',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:50',
                'subject_id' => 'required|exists:subjects,id',
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'instructions' => 'nullable|string',
                'due_date' => 'required|date',
                'max_marks' => 'nullable|numeric|min:0',
                'assignment_type' => 'nullable|in:Homework,Project,Quiz,Test,Other',
                'audience_mode' => 'required|in:all,custom',
                'student_ids' => 'nullable|array',
                'student_ids.*' => 'integer|exists:students,id',
                'is_published' => 'nullable|boolean',
                'attachments' => 'nullable|array',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $branchId = $this->resolveWritableBranchId($request, $request->branch_id);
            if (!$branchId) {
                return response()->json(['success' => false, 'message' => 'branch_id is required'], 422);
            }
            if (!$this->canManageBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            $subject = Subject::find($request->subject_id);
            if (!$subject || (int) $subject->branch_id !== (int) $branchId) {
                return response()->json([
                    'success' => false,
                    'errors' => ['subject_id' => ['Subject does not belong to this branch.']],
                ], 422);
            }

            $academicYearId = $this->academicYearContext->id(false);
            $studentIds = $this->recipientResolver->resolveStudentIds(
                $branchId,
                strip_tags($request->grade),
                strip_tags($request->section),
                $academicYearId,
                $request->audience_mode,
                $request->student_ids
            );

            if ($studentIds === []) {
                return response()->json([
                    'success' => false,
                    'message' => 'No students found for this class and section.',
                ], 422);
            }

            $isPublished = $request->has('is_published') ? $request->boolean('is_published') : true;
            $user = $request->user();

            $assignment = DB::transaction(function () use ($request, $branchId, $academicYearId, $studentIds, $isPublished, $user) {
                $payload = [
                    'branch_id' => $branchId,
                    'grade' => strip_tags($request->grade),
                    'section' => strip_tags($request->section),
                    'subject_id' => (int) $request->subject_id,
                    'teacher_id' => $user->id,
                    'title' => strip_tags($request->title),
                    'description' => $request->description ? strip_tags($request->description) : null,
                    'instructions' => $request->instructions ? strip_tags($request->instructions) : null,
                    'due_date' => $request->due_date,
                    'max_marks' => $request->max_marks ?? 0,
                    'assignment_type' => $request->assignment_type ?? 'Homework',
                    'audience_mode' => $request->audience_mode,
                    'attachments' => $request->attachments ?? [],
                    'is_published' => $isPublished,
                    'published_at' => $isPublished ? now() : null,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ];
                if (Schema::hasColumn('assignments', 'school_id')) {
                    $payload['school_id'] = $this->schoolIdForBranch($branchId);
                }
                if (Schema::hasColumn('assignments', 'academic_year_id')) {
                    $payload['academic_year_id'] = $academicYearId;
                }

                $assignment = Assignment::create($payload);
                $assignment->recipients()->createMany(
                    collect($studentIds)->map(fn ($id) => ['student_id' => $id])->all()
                );
                $this->syncUniversalAttachments($assignment, $request->attachments, true);

                return $assignment->fresh();
            });

            $assignment->load(['subject:id,name,code', 'teacher:id,first_name,last_name'])
                ->loadCount(['recipients', 'submissions']);

            if ($assignment->is_published) {
                DispatchAssignmentNotificationsJob::dispatchFor($assignment->id, 'created');
            }

            return response()->json([
                'success' => true,
                'message' => 'Assignment created successfully',
                'data' => $this->present($assignment, true, $request->user()?->id),
            ], 201);
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'errors' => $e->errors()], 422);
        } catch (\Throwable $e) {
            Log::error('Create assignment error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to create assignment', $e);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $assignment = Assignment::findOrFail($id);
            if (!$this->canManageAssignment($request, $assignment)) {
                return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'description' => 'nullable|string',
                'instructions' => 'nullable|string',
                'due_date' => 'sometimes|date',
                'max_marks' => 'nullable|numeric|min:0',
                'assignment_type' => 'nullable|in:Homework,Project,Quiz,Test,Other',
                'is_published' => 'nullable|boolean',
                'attachments' => 'nullable|array',
                'notify' => 'nullable|boolean',
            ]);
            if ($validator->fails()) {
                return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
            }

            $wasPublished = (bool) $assignment->is_published;
            $data = $request->only(['title', 'description', 'instructions', 'due_date', 'max_marks', 'assignment_type', 'attachments']);
            if ($request->has('is_published')) {
                $data['is_published'] = $request->boolean('is_published');
                if ($data['is_published'] && !$assignment->published_at) {
                    $data['published_at'] = now();
                }
            }
            $data['updated_by'] = $request->user()->id;
            if (isset($data['title'])) {
                $data['title'] = strip_tags($data['title']);
            }
            if (isset($data['description'])) {
                $data['description'] = $data['description'] ? strip_tags($data['description']) : null;
            }
            if (isset($data['instructions'])) {
                $data['instructions'] = $data['instructions'] ? strip_tags($data['instructions']) : null;
            }

            $assignment->update($data);
            if ($request->has('attachments')) {
                $this->syncUniversalAttachments($assignment, $request->attachments, true);
            }
            $assignment->load(['subject:id,name,code', 'teacher:id,first_name,last_name'])
                ->loadCount(['recipients', 'submissions']);

            $notifyRequested = $request->has('notify') ? $request->boolean('notify') : true;
            $becamePublished = !$wasPublished && $assignment->is_published;
            $shouldNotify = ($notifyRequested || $becamePublished) && $assignment->is_published;
            if ($shouldNotify) {
                DispatchAssignmentNotificationsJob::dispatchFor(
                    $assignment->id,
                    $becamePublished ? 'created' : 'updated'
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully',
                'data' => $this->present($assignment, true, $request->user()?->id),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update assignment', $e);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $assignment = Assignment::findOrFail($id);
            if (!$this->canManageAssignment($request, $assignment)) {
                return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
            }

            $assignment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Assignment deleted successfully',
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to delete assignment', $e);
        }
    }

    private function present(Assignment $assignment, bool $detailed = false, $viewerId = null): array
    {
        $due = $assignment->due_date;
        $status = !$assignment->is_published
            ? 'Draft'
            : (($due && $due->isPast()) ? 'Due' : 'Published');

        $payload = [
            'id' => $assignment->id,
            'title' => $assignment->title,
            'description' => $assignment->description,
            'class_name' => $assignment->grade,
            'grade' => $assignment->grade,
            'section' => $assignment->section ?? '',
            'subject' => $assignment->subject?->name ?? '',
            'subject_id' => $assignment->subject_id,
            'due_date' => optional($assignment->due_date)->toDateString(),
            'submission_count' => (int) ($assignment->submissions_count ?? 0),
            'recipient_count' => (int) ($assignment->recipients_count ?? $assignment->recipients->count()),
            'status' => $status,
            'audience_mode' => $assignment->audience_mode ?? 'all',
            'is_published' => (bool) $assignment->is_published,
            'max_marks' => $assignment->max_marks,
            'assignment_type' => $assignment->assignment_type,
            'teacher_id' => $assignment->teacher_id,
            'created_by' => $assignment->created_by,
            'can_edit' => $viewerId !== null && (int) $assignment->created_by === (int) $viewerId,
        ];

        if ($detailed) {
            $payload['instructions'] = $assignment->instructions;
            $payload['attachments'] = $this->presentAttachments($assignment);
            $payload['published_at'] = optional($assignment->published_at)?->toIso8601String();
            $payload['student_ids'] = $assignment->relationLoaded('recipients')
                ? $assignment->recipients->pluck('student_id')->map(fn ($id) => (int) $id)->values()->all()
                : [];
        }

        return $payload;
    }

    private function syncUniversalAttachments(Assignment $assignment, $items, bool $replace = false): void
    {
        $items = is_array($items) ? $items : [];
        $keepPaths = [];

        foreach ($items as $item) {
            if (!is_array($item) || empty($item['file_path'])) {
                continue;
            }
            $path = ltrim((string) $item['file_path'], '/');
            $keepPaths[] = $path;
            UniversalAttachment::updateOrCreate(
                [
                    'module' => 'assignment',
                    'module_id' => $assignment->id,
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

        if ($replace) {
            $query = UniversalAttachment::where('module', 'assignment')
                ->where('module_id', $assignment->id);
            if ($keepPaths !== []) {
                $query->whereNotIn('file_path', $keepPaths);
            }
            $query->get()->each->delete();
        }

        $assignment->forceFill([
            'attachments' => $this->presentAttachments($assignment),
        ])->save();
    }

    private function presentAttachments(Assignment $assignment): array
    {
        $query = UniversalAttachment::where('module', 'assignment')
            ->where('module_id', $assignment->id);
        $usedUniversal = (clone $query)->withTrashed()->exists();
        $rows = $query->where('is_active', true)->orderBy('id')->get();

        if ($usedUniversal) {
            return $rows->map(function (UniversalAttachment $attachment) {
                return [
                    'id' => $attachment->id,
                    'file_name' => $attachment->file_name,
                    'original_name' => $attachment->original_name,
                    'file_path' => $attachment->file_path,
                    'file_url' => Storage::disk('public')->url($attachment->file_path),
                    'file_type' => $attachment->file_type,
                    'file_size' => $attachment->file_size,
                    'attachment_type' => $attachment->attachment_type,
                ];
            })->values()->all();
        }

        return is_array($assignment->attachments) ? $assignment->attachments : [];
    }

    private function canCreateAssignments($user): bool
    {
        return $user && in_array($user->role, ['Teacher', 'BranchAdmin', 'SuperAdmin', 'Staff'], true);
    }

    private function canViewAssignment(Request $request, Assignment $assignment): bool
    {
        $user = $request->user();
        if (!$user || !$this->canAccessBranch($request, (int) $assignment->branch_id)) {
            return false;
        }
        if (in_array($user->role, ['SuperAdmin', 'BranchAdmin', 'Staff'], true)) {
            return true;
        }
        if ($user->role === 'Teacher') {
            return (int) $assignment->teacher_id === (int) $user->id
                || (int) $assignment->created_by === (int) $user->id
                || $assignment->is_published;
        }
        if ($user->role === 'Student') {
            if (!$assignment->is_published) {
                return false;
            }
            $studentId = Student::where('user_id', $user->id)->value('id');
            return $studentId && $assignment->recipients()->where('student_id', $studentId)->exists();
        }

        return false;
    }

    private function canManageAssignment(Request $request, Assignment $assignment): bool
    {
        $user = $request->user();
        if (!$user || !$this->canManageBranch($request, (int) $assignment->branch_id)) {
            return false;
        }
        return (int) $assignment->created_by === (int) $user->id;
    }

    private function resolveWritableBranchId(Request $request, $requested): ?int
    {
        if ($requested) {
            return (int) $requested;
        }
        $default = $this->getDefaultBranchId($request);
        if ($default) {
            return (int) $default;
        }
        $user = $request->user();

        return $user?->branch_id ? (int) $user->branch_id : null;
    }

    private function schoolIdForBranch(int $branchId): ?int
    {
        $schoolId = Branch::whereKey($branchId)->value('school_id');

        return $schoolId ? (int) $schoolId : null;
    }
}
