<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\SectionSubject;
use App\Models\Section;
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
            $academicYear = $request->get('academic_year', date('Y') . '-' . (date('Y') + 1));
            
            $section = Section::with([
                'sectionSubjects' => function($query) use ($academicYear) {
                    $query->where('academic_year', $academicYear)
                          ->where('is_active', true);
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
            $query = SectionSubject::with(['section', 'subject', 'teacher', 'branch']);

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
                    });
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'section_id',
                'subject_id',
                'teacher_id',
                'academic_year',
                'is_active',
                'created_at'
            ];

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
                'academic_year' => 'required|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check if already assigned
            $exists = SectionSubject::where('section_id', $request->section_id)
                ->where('subject_id', $request->subject_id)
                ->where('academic_year', $request->academic_year)
                ->first();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subject already assigned to this section'
                ], 400);
            }

            $assignment = SectionSubject::create([
                'section_id' => $request->section_id,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'branch_id' => $request->branch_id,
                'academic_year' => $request->academic_year,
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
                'academic_year' => 'required|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $assigned = [];
            $skipped = [];

            // OPTIMIZED: Get all existing assignments once
            $existingSubjectIds = SectionSubject::where('section_id', $request->section_id)
                ->where('academic_year', $request->academic_year)
                ->pluck('subject_id')
                ->toArray();

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
                    'academic_year' => $request->academic_year,
                    'is_active' => true
                ]);

                $assigned[] = $assignment->load(['section', 'subject', 'teacher']);
                
                // Add to existing list to prevent duplicates
                $existingSubjectIds[] = $subjectData['subject_id'];
            }

            DB::commit();

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
                'academic_year' => 'required|string|max:20',
                'copy_teachers' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get source section's subjects
            $sourceSubjects = SectionSubject::where('section_id', $request->from_section_id)
                ->where('academic_year', $request->academic_year)
                ->where('is_active', true)
                ->get();

            if ($sourceSubjects->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Source section has no subjects assigned'
                ], 400);
            }

            $copyTeachers = $request->copy_teachers ?? false;
            $totalCopied = 0;

            foreach ($request->to_section_ids as $toSectionId) {
                $targetSection = Section::find($toSectionId);
                
                foreach ($sourceSubjects as $sourceSubject) {
                    // Check if already exists
                    $exists = SectionSubject::where('section_id', $toSectionId)
                        ->where('subject_id', $sourceSubject->subject_id)
                        ->where('academic_year', $request->academic_year)
                        ->exists();

                    if (!$exists) {
                        SectionSubject::create([
                            'section_id' => $toSectionId,
                            'subject_id' => $sourceSubject->subject_id,
                            'teacher_id' => $copyTeachers ? $sourceSubject->teacher_id : null,
                            'branch_id' => $targetSection->branch_id,
                            'academic_year' => $request->academic_year,
                            'is_active' => true
                        ]);
                        $totalCopied++;
                    }
                }
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
    public function removeSubject($id)
    {
        try {
            DB::beginTransaction();

            $assignment = SectionSubject::findOrFail($id);
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
}

