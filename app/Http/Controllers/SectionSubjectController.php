<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\SectionSubject;
use App\Models\Section;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SectionSubjectController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all subjects for a section
     */
    public function getSectionSubjects(Request $request, $sectionId)
    {
        try {
            [$academicYearId, $academicYearName] = $this->resolveAcademicYear($request);

            // Tenant guard: only read a section the user can access.
            $sectionBranchId = Section::whereKey($sectionId)->value('branch_id');
            if ($sectionBranchId === null || !$this->canAccessBranch($request, (int) $sectionBranchId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section not found'
                ], 404);
            }

            $section = Section::with([
                'sectionSubjects' => function($query) use ($academicYearId, $academicYearName) {
                    if ($academicYearId) {
                        $query->where('academic_year_id', $academicYearId);
                    } else {
                        $query->where('academic_year', $academicYearName);
                    }
                    $query->where('is_active', true);
                },
                'sectionSubjects.subject',
                'sectionSubjects.teacher'
            ])->findOrFail($sectionId);

            return response()->json([
                'success' => true,
                'data' => [
                    'section' => $section,
                    'subjects' => $section->sectionSubjects
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get section subjects error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section subjects'
            ], 500);
        }
    }

    /**
     * Get all section-subject assignments with pagination
     */
    public function index(Request $request)
    {
        try {
            // List endpoint: keep payload small + eager-load only what the UI needs
            $query = SectionSubject::query()
                ->select([
                    'id',
                    'section_id',
                    'subject_id',
                    'teacher_id',
                    'branch_id',
                    'academic_year_id',
                    'academic_year',
                    'is_active',
                    'created_at',
                    'updated_at',
                ])
                ->with([
                    'section:id,branch_id,name,code,grade_level',
                    'subject:id,code,name,type,grade_level,branch_id,is_active',
                    'teacher:id,first_name,last_name,email',
                    'branch:id,name,code',
                ]);

            // 🔥 APPLY BRANCH FILTERING
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('section_id')) {
                $query->where('section_id', $request->section_id);
            }

            if ($request->has('subject_id')) {
                $query->where('subject_id', $request->subject_id);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->filled('academic_year_id')) {
                $query->where('academic_year_id', $request->academic_year_id);
            }

            // Default academic year scoping (from middleware/header) when client doesn't provide any year.
            if (!$request->filled('academic_year') && !$request->filled('academic_year_id')) {
                $academicYearIdFromContext = $request->attributes->get('academic_year_id');
                if ($academicYearIdFromContext) {
                    $query->where('academic_year_id', (int) $academicYearIdFromContext);
                }
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // OPTIMIZED Search - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->whereHas('section', function($sq) use ($search) {
                        $sq->where('name', 'like', "{$search}%")
                           ->orWhere('code', 'like', "{$search}%");
                    })
                    ->orWhereHas('subject', function($sq) use ($search) {
                        $sq->where('name', 'like', "{$search}%")
                           ->orWhere('code', 'like', "{$search}%");
                    })
                    ->orWhereHas('teacher', function($tq) use ($search) {
                        $tq->where('first_name', 'like', "{$search}%")
                           ->orWhere('last_name', 'like', "{$search}%")
                           ->orWhere('email', 'like', "{$search}%")
                           ->orWhereRaw('CONCAT(first_name, " ", last_name) LIKE ?', ["{$search}%"]);
                    });
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'section_id',
                'subject_id',
                'teacher_id',
                'branch_id',
                'academic_year_id',
                'academic_year',
                'is_active',
                'created_at'
            ];

            // Default sort: branch-wise first, then newest
            if (!$request->filled('sort_by')) {
                $query->orderBy('branch_id', 'asc')
                    ->orderBy('created_at', 'desc');
            }
            $assignments = $this->paginateAndSort($query, $request, $sortableColumns, 'created_at', 'desc');

            return response()->json([
                'success' => true,
                'message' => 'Section subjects retrieved successfully',
                'data' => $assignments->items(),
                'meta' => [
                    'current_page' => $assignments->currentPage(),
                    'per_page' => $assignments->perPage(),
                    'total' => $assignments->total(),
                    'last_page' => $assignments->lastPage(),
                    'from' => $assignments->firstItem(),
                    'to' => $assignments->lastItem(),
                    'has_more_pages' => $assignments->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get section subjects error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section subjects',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Assign single subject to section
     */
    public function assignSubject(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'nullable|exists:users,id',
                'branch_id' => 'required|exists:branches,id',
                'academic_year_id' => 'required_without:academic_year|nullable|exists:academic_years,id',
                'academic_year' => 'required_without:academic_year_id|nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tenant guard: user must manage the branch, and the section must belong to it.
            if (!$this->canManageBranch($request, (int) $request->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this branch'
                ], 403);
            }
            if ((int) Section::whereKey($request->section_id)->value('branch_id') !== (int) $request->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section does not belong to the selected branch'
                ], 422);
            }

            DB::beginTransaction();

            [$academicYearId, $academicYearName] = $this->resolveAcademicYear($request);

            // Check if already assigned
            $exists = SectionSubject::where('section_id', $request->section_id)
                ->where('subject_id', $request->subject_id)
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->when(!$academicYearId, fn($q) => $q->where('academic_year', $academicYearName))
                ->first();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject already assigned to this section'
                ], 400);
            }

            $branch = \App\Models\Branch::find($request->branch_id);
            $assignment = SectionSubject::create([
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'branch_id' => $request->branch_id,
                'school_id' => $branch ? $branch->school_id : null,
                'academic_year_id' => $academicYearId,
                'academic_year' => $academicYearName,
                'is_active' => true
            ]);

            DB::commit();

            Log::info('Subject assigned to section', [
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Subject assigned successfully',
                'data' => $assignment->load(['section', 'subject', 'teacher'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Assign subject error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign subject',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Assign multiple subjects to section (BULK)
     */
    public function assignMultipleSubjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'section_id' => 'required|exists:sections,id',
                'subjects' => 'required|array|min:1',
                'subjects.*.subject_id' => 'required|exists:subjects,id',
                'subjects.*.teacher_id' => 'nullable|exists:users,id',
                'branch_id' => 'required|exists:branches,id',
                'academic_year_id' => 'required_without:academic_year|nullable|exists:academic_years,id',
                'academic_year' => 'required_without:academic_year_id|nullable|string|max:50',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tenant guard: user must manage the branch, and the section must belong to it.
            if (!$this->canManageBranch($request, (int) $request->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this branch'
                ], 403);
            }
            if ((int) Section::whereKey($request->section_id)->value('branch_id') !== (int) $request->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Section does not belong to the selected branch'
                ], 422);
            }

            DB::beginTransaction();

            [$academicYearId, $academicYearName] = $this->resolveAcademicYear($request);

            $createdIds = [];
            $skipped = [];

            // OPTIMIZED: Get all existing assignments once
            $existingSubjectIds = SectionSubject::where('section_id', $request->section_id)
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->when(!$academicYearId, fn($q) => $q->where('academic_year', $academicYearName))
                ->pluck('subject_id')
                ->toArray();

            $branch = \App\Models\Branch::find($request->branch_id);
            $schoolId = $branch ? $branch->school_id : null;

            foreach ($request->subjects as $subjectData) {
                // Check if already exists (no query)
                if (in_array($subjectData['subject_id'], $existingSubjectIds)) {
                    $skipped[] = $subjectData['subject_id'];
                    continue;
                }

                $assignment = SectionSubject::create([
                    'section_id' => $request->section_id,
                    'subject_id' => $subjectData['subject_id'],
                    'teacher_id' => $subjectData['teacher_id'] ?? null,
                    'branch_id' => $request->branch_id,
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'academic_year' => $academicYearName,
                    'is_active' => true
                ]);

                $createdIds[] = $assignment->id;

                // Add to existing list to prevent duplicates
                $existingSubjectIds[] = $subjectData['subject_id'];
            }

            DB::commit();

            // Eager-load all created rows in ONE query (avoids N+1 in the loop above).
            $assigned = SectionSubject::with(['section', 'subject', 'teacher'])
                ->whereIn('id', $createdIds)
                ->get();

            Log::info('Bulk subjects assigned', [
                'section_id' => $request->section_id,
                'assigned_count' => count($assigned)
            ]);

            return response()->json([
                'success' => true,
                'message' => count($assigned) . ' subject(s) assigned successfully',
                'data' => [
                    'assigned' => $assigned,
                    'skipped_count' => count($skipped)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk assign subjects error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to assign subjects',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Copy subjects from one section to another
     */
    public function copySubjects(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_section_id' => 'required|exists:sections,id',
                'to_section_ids' => 'required|array|min:1',
                'to_section_ids.*' => 'required|exists:sections,id',
                'academic_year_id' => 'required_without:academic_year|nullable|exists:academic_years,id',
                'academic_year' => 'required_without:academic_year_id|nullable|string|max:50',
                'copy_teachers' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tenant guard: can the user read the source section?
            $fromBranchId = Section::whereKey($request->from_section_id)->value('branch_id');
            if ($fromBranchId === null || !$this->canAccessBranch($request, (int) $fromBranchId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source section not found'
                ], 404);
            }

            DB::beginTransaction();

            [$academicYearId, $academicYearName] = $this->resolveAcademicYear($request);

            // Get source section's subjects
            $sourceSubjects = SectionSubject::where('section_id', $request->from_section_id)
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->when(!$academicYearId, fn($q) => $q->where('academic_year', $academicYearName))
                ->where('is_active', true)
                ->get();

            if ($sourceSubjects->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Source section has no subjects assigned'
                ], 400);
            }

            $copyTeachers = $request->copy_teachers ?? false;
            $totalCopied = 0;

            // Load all target sections at once and pre-fetch existing assignments to avoid
            // an exists() query per (section x subject) pair.
            $targetSections = Section::whereIn('id', $request->to_section_ids)->get()->keyBy('id');

            $existingPairs = SectionSubject::whereIn('section_id', $request->to_section_ids)
                ->when($academicYearId, fn($q) => $q->where('academic_year_id', $academicYearId))
                ->when(!$academicYearId, fn($q) => $q->where('academic_year', $academicYearName))
                ->get(['section_id', 'subject_id'])
                ->map(fn($r) => $r->section_id . ':' . $r->subject_id)
                ->flip();

            $now = now();
            $rows = [];

            foreach ($request->to_section_ids as $toSectionId) {
                $targetSection = $targetSections->get($toSectionId);
                if (!$targetSection) {
                    continue;
                }
                // Tenant guard per target section.
                if (!$this->canManageBranch($request, (int) $targetSection->branch_id)) {
                    continue;
                }

                foreach ($sourceSubjects as $sourceSubject) {
                    $key = $toSectionId . ':' . $sourceSubject->subject_id;
                    if ($existingPairs->has($key)) {
                        continue;
                    }

                    $rows[] = [
                        'section_id' => $toSectionId,
                        'subject_id' => $sourceSubject->subject_id,
                        'teacher_id' => $copyTeachers ? $sourceSubject->teacher_id : null,
                        'branch_id' => $targetSection->branch_id,
                        'school_id' => $targetSection->school_id,
                        'academic_year_id' => $academicYearId,
                        'academic_year' => $academicYearName,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $existingPairs->put($key, true); // guard against dup target sections in the request
                    $totalCopied++;
                }
            }

            if (!empty($rows)) {
                SectionSubject::insert($rows);
            }

            DB::commit();

            Log::info('Subjects copied between sections', [
                'from_section' => $request->from_section_id,
                'to_sections' => count($request->to_section_ids),
                'total_copied' => $totalCopied
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully copied {$totalCopied} subject assignment(s)",
                'data' => [
                    'total_copied' => $totalCopied,
                    'source_section_id' => $request->from_section_id,
                    'target_sections' => count($request->to_section_ids)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Copy subjects error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to copy subjects',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Remove subject from section
     */
    public function removeSubject(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $assignment = SectionSubject::findOrFail($id);

            // Tenant guard: only remove assignments in branches the user can manage.
            if (!$this->canManageBranch($request, (int) $assignment->branch_id)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Assignment not found'
                ], 404);
            }

            $assignment->delete();

            DB::commit();

            Log::info('Subject removed from section', ['assignment_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Subject removed from section successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Remove subject error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove subject',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update subject assignment (change teacher)
     */
    public function updateAssignment(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'teacher_id' => 'nullable|exists:users,id',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $assignment = SectionSubject::findOrFail($id);

            // Tenant guard: only update assignments in branches the user can manage.
            if (!$this->canManageBranch($request, (int) $assignment->branch_id)) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Assignment not found'
                ], 404);
            }

            $assignment->update($request->only(['teacher_id', 'is_active']));

            DB::commit();

            Log::info('Assignment updated', ['assignment_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully',
                'data' => $assignment->load(['section', 'subject', 'teacher'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update assignment error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update assignment',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Resolve [academic_year_id, academic_year_name] from request inputs, falling back
     * to the academic-year context injected by middleware, then to the current year.
     *
     * Crucially this always derives the id from the name (and vice-versa) so writes never
     * persist a null academic_year_id — null rows are invisible to the default, context-scoped
     * list endpoint, which made assignments silently "disappear" from the UI.
     */
    private function resolveAcademicYear(Request $request): array
    {
        $id = $request->input('academic_year_id') ?? $request->attributes->get('academic_year_id');
        $name = $request->input('academic_year');

        if ($id) {
            $name = AcademicYear::whereKey($id)->value('name') ?? $name;
        } elseif ($name) {
            $id = AcademicYear::where('name', $name)->value('id');
        }

        if (!$id && !$name) {
            $current = AcademicYear::current()->first();
            if ($current) {
                $id = $current->id;
                $name = $current->name;
            } else {
                $name = date('Y') . '-' . (date('Y') + 1);
            }
        }

        return [$id, $name];
    }
}

