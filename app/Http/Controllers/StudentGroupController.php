<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\StudentGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentGroupController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all student groups with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $query = StudentGroup::with(['branch'])
                ->withCount(['members as member_count' => fn($q) => $q->where('is_active', true)]);

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Branch filter: apply when user has access to that branch
            if ($request->filled('branch_id')) {
                $requestedBranchId = (int) $request->branch_id;
                if ($accessibleBranchIds === 'all' || (is_array($accessibleBranchIds) && in_array($requestedBranchId, $accessibleBranchIds, true))) {
                    $query->where('branch_id', $requestedBranchId);
                }
            }

            // Group code filter (exact or partial match)
            if ($request->filled('code')) {
                $code = strip_tags((string) $request->code);
                $query->where('code', 'like', '%' . $code . '%');
            }

            // Group name filter (partial match)
            if ($request->filled('name')) {
                $name = strip_tags((string) $request->name);
                $query->where('name', 'like', '%' . $name . '%');
            }

            if ($request->has('type') && $request->filled('type')) {
                $query->where('type', $request->type);
            }

            // Filter by academic year: prefer academic_year_id (from query or X-Academic-Year-Id header).
            // Only apply when it's a genuine positive integer id. A stale/undecodable hashid token
            // would otherwise cast to 0 via (int) and silently hide EVERY group (where academic_year_id = 0).
            $academicYearId = $request->query('academic_year_id')
                ?? $request->header('X-Academic-Year-Id');
            if (is_numeric($academicYearId) && (int) $academicYearId > 0) {
                $query->where('academic_year_id', (int) $academicYearId);
            } elseif ($request->filled('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%');
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'code',
                'name',
                'type',
                'academic_year',
                'is_active',
                'created_at',
                'updated_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by name asc)
            $groups = $this->paginateAndSort($query, $request, $sortableColumns, 'name', 'asc');

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Student groups retrieved successfully',
                'data' => $groups->items(),
                'meta' => [
                    'current_page' => $groups->currentPage(),
                    'per_page' => $groups->perPage(),
                    'total' => $groups->total(),
                    'last_page' => $groups->lastPage(),
                    'from' => $groups->firstItem(),
                    'to' => $groups->lastItem(),
                    'has_more_pages' => $groups->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get student groups error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student groups',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new student group
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:student_groups',
                'type' => 'required|in:Academic,Sports,Cultural,Club',
                'academic_year' => 'required_without:academic_year_id|nullable|string|max:20',
                'academic_year_id' => 'required_without:academic_year|nullable|exists:academic_years,id',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Tenant guard: can't create a group in a branch the user can't access.
            if (!$this->canAccessBranch($request, (int) $request->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to the selected branch'
                ], 403);
            }

            DB::beginTransaction();

            $branch = \App\Models\Branch::find($request->branch_id);
            [$academicYearId, $academicYearName] = $this->resolveAcademicYear($request->academic_year, $request->academic_year_id);

            $group = StudentGroup::create([
                'branch_id' => $request->branch_id,
                'school_id' => $branch ? $branch->school_id : null,
                'name' => strip_tags($request->name),
                'code' => strtoupper($request->code),
                'type' => $request->type,
                'academic_year' => $academicYearName ?? $request->academic_year,
                'academic_year_id' => $academicYearId,
                'description' => strip_tags($request->description),
                'is_active' => $request->boolean('is_active', true)
            ]);

            DB::commit();

            Log::info('Student group created', ['group_id' => $group->id]);

            return response()->json([
                'success' => true,
                'message' => 'Student group created successfully',
                'data' => $group->load('branch')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student group',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single student group
     */
    public function show(Request $request, $id)
    {
        try {
            $group = StudentGroup::with(['branch', 'members.student', 'members.studentRecord'])
                ->findOrFail($id);

            // Tenant guard: hide groups outside the user's accessible branches.
            // 404 (not 403) so we don't reveal that the group exists.
            if (!$this->canAccessBranch($request, (int) $group->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student group not found'
                ], 404);
            }

            $group->member_count = $group->members->where('is_active', true)->count();

            $branchId = $group->branch_id;
            $academicYearId = $group->academic_year_id
                ?? $request->header('X-Academic-Year-Id')
                ?? ($group->academic_year ? \App\Models\AcademicYear::where('name', $group->academic_year)->value('id') : null)
                ?? null;

            // Get grade/section from student_enrollments (for group's academic year) or fallback to latest enrollment or students table
            $studentIds = $group->members->pluck('student_id')->filter()->unique()->values()->toArray();
            $enrollments = collect();
            $fallbackEnrollments = collect();
            $hasEnrollmentsTable = \Illuminate\Support\Facades\Schema::hasTable('student_enrollments');

            if (!empty($studentIds) && $hasEnrollmentsTable) {
                if ($academicYearId) {
                    $enrollments = DB::table('student_enrollments')
                        ->where('academic_year_id', $academicYearId)
                        ->whereIn('student_id', $studentIds)
                        ->where('status', 'Active')
                        ->get()
                        ->keyBy('student_id');
                }
                // Fallback: latest enrollment per student (any academic year) via single efficient query
                $missingIds = array_diff($studentIds, $enrollments->keys()->toArray());
                if (!empty($missingIds)) {
                    $sub = DB::table('student_enrollments')
                        ->whereIn('student_id', $missingIds)
                        ->where('status', 'Active')
                        ->select('student_id', DB::raw('MAX(academic_year_id) as max_ay'))
                        ->groupBy('student_id');
                    $fallbackEnrollments = DB::table('student_enrollments as se')
                        ->joinSub($sub, 'latest', 'se.student_id', '=', 'latest.student_id')
                        ->whereColumn('se.academic_year_id', 'latest.max_ay')
                        ->where('se.status', 'Active')
                        ->select('se.*')
                        ->get()
                        ->keyBy('student_id');
                }
            }

            // Collect all grade values for label lookup (normalize to string for grades table match)
            $gradeValues = collect();
            foreach ($group->members as $member) {
                $enr = $enrollments[$member->student_id] ?? $fallbackEnrollments[$member->student_id] ?? null;
                $g = $enr?->grade ?? $member->studentRecord?->grade;
                if ($g !== null && $g !== '') {
                    $gradeValues->push((string) $g);
                }
            }
            $gradeValues = $gradeValues->unique()->values()->toArray();

            // Resolve grade labels: branch-specific first, then global (branch_id null)
            $gradeLabels = [];
            $hasGradesTable = \Illuminate\Support\Facades\Schema::hasTable('grades');
            if (!empty($gradeValues) && $hasGradesTable) {
                $gradesQuery = DB::table('grades')
                    ->whereIn('value', $gradeValues);
                if ($branchId && \Illuminate\Support\Facades\Schema::hasColumn('grades', 'branch_id')) {
                    $gradesQuery->where(function ($q) use ($branchId) {
                        $q->where('branch_id', $branchId)->orWhereNull('branch_id');
                    })->orderByRaw('branch_id IS NULL DESC'); // NULL (global) first so branch-specific rows come last and win in pluck()
                }
                $gradeLabels = $gradesQuery->pluck('label', 'value')->toArray();
            }

            $group->members->transform(function ($member) use ($enrollments, $fallbackEnrollments, $gradeLabels) {
                $enr = $enrollments[$member->student_id] ?? $fallbackEnrollments[$member->student_id] ?? null;
                $studentRecord = $member->studentRecord;
                $grade = $enr?->grade ?? $studentRecord?->grade;
                $section = $enr?->section ?? $studentRecord?->section;
                $gradeStr = $grade !== null && $grade !== '' ? (string) $grade : null;
                $gradeLabel = $gradeStr ? ($gradeLabels[$gradeStr] ?? 'Grade ' . $gradeStr) : null;

                // Add grade/section/grade_label as top-level member attributes for reliable frontend access
                $member->setAttribute('grade', $grade);
                $member->setAttribute('section', $section);
                $member->setAttribute('grade_label', $gradeLabel);

                // Merge into student relation for frontend
                $student = $member->student;
                if ($student) {
                    $student->setAttribute('grade', $grade);
                    $student->setAttribute('section', $section);
                    $student->setAttribute('grade_label', $gradeLabel);
                }

                return $member;
            });

            return response()->json([
                'success' => true,
                'data' => $group
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student group'
            ], 500);
        }
    }

    /**
     * Update student group
     */
    public function update(Request $request, $id)
    {
        try {
            $group = StudentGroup::findOrFail($id);

            // Tenant guard: can't edit a group outside the user's accessible branches.
            if (!$this->canAccessBranch($request, (int) $group->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student group not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'code' => 'sometimes|string|max:50|unique:student_groups,code,' . $id,
                'type' => 'sometimes|in:Academic,Sports,Cultural,Club',
                'academic_year' => 'sometimes|nullable|string|max:20',
                'academic_year_id' => 'sometimes|nullable|exists:academic_years,id',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only(['name', 'code', 'type', 'description', 'is_active']);

            if ($request->has('academic_year') || $request->has('academic_year_id')) {
                [$academicYearId, $academicYearName] = $this->resolveAcademicYear(
                    $request->academic_year,
                    $request->academic_year_id
                );
                $updateData['academic_year_id'] = $academicYearId;
                $updateData['academic_year'] = $academicYearName ?? $updateData['academic_year'] ?? $group->academic_year;
            }

            if (isset($updateData['name'])) $updateData['name'] = strip_tags($updateData['name']);
            if (isset($updateData['code'])) $updateData['code'] = strtoupper($updateData['code']);
            if (isset($updateData['description'])) $updateData['description'] = strip_tags($updateData['description']);

            $group->update($updateData);

            DB::commit();

            Log::info('Student group updated', ['group_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Student group updated successfully',
                'data' => $group->fresh(['branch'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student group'
            ], 500);
        }
    }

    /**
     * Delete student group
     */
    public function destroy(Request $request, $id)
    {
        try {
            $group = StudentGroup::findOrFail($id);

            // Tenant guard: can't delete a group outside the user's accessible branches.
            if (!$this->canAccessBranch($request, (int) $group->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student group not found'
                ], 404);
            }

            DB::beginTransaction();

            $group->delete();

            DB::commit();

            Log::info('Student group deleted', ['group_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Student group deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student group'
            ], 500);
        }
    }

    /**
     * Add student to group
     */
    public function addMember(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id',
                'role' => 'sometimes|in:Member,Leader',
                'joined_date' => 'sometimes|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $group = StudentGroup::findOrFail($id);

            // Tenant guard: can't modify members of a group outside accessible branches.
            if (!$this->canAccessBranch($request, (int) $group->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student group not found'
                ], 404);
            }

            // Ensure student belongs to the group's branch
            $student = \App\Models\Student::find($request->student_id);
            if (!$student || $student->branch_id != $group->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student must belong to the same branch as the group'
                ], 400);
            }

            // Check if already a member
            $exists = DB::table('student_group_members')
                ->where('group_id', $id)
                ->where('student_id', $request->student_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is already a member of this group'
                ], 400);
            }

            DB::table('student_group_members')->insert([
                'group_id' => $id,
                'student_id' => $request->student_id,
                'joined_date' => $request->joined_date ?? now(),
                'role' => $request->role ?? 'Member',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Student added to group', ['group_id' => $id, 'student_id' => $request->student_id]);

            return response()->json([
                'success' => true,
                'message' => 'Student added to group successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Add group member error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add student to group'
            ], 500);
        }
    }

    /**
     * Remove student from group
     */
    public function removeMember(Request $request, $id, $studentId)
    {
        try {
            $group = StudentGroup::findOrFail($id);

            // Tenant guard: can't modify members of a group outside accessible branches.
            if (!$this->canAccessBranch($request, (int) $group->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student group not found'
                ], 404);
            }

            DB::table('student_group_members')
                ->where('group_id', $id)
                ->where('student_id', $studentId)
                ->delete();

            Log::info('Student removed from group', ['group_id' => $id, 'student_id' => $studentId]);

            return response()->json([
                'success' => true,
                'message' => 'Student removed from group successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove student from group'
            ], 500);
        }
    }

    /**
     * Resolve academic_year_id and academic_year name from either input.
     * Returns [academic_year_id, academic_year_name].
     */
    private function resolveAcademicYear(?string $academicYearName, $academicYearId = null): array
    {
        if ($academicYearId !== null && $academicYearId !== '') {
            $id = (int) $academicYearId;
            $ay = \App\Models\AcademicYear::find($id);
            return [$id, $ay?->name];
        }
        if ($academicYearName) {
            $ay = \App\Models\AcademicYear::where('name', $academicYearName)->first();
            return [$ay?->id, $ay?->name ?? $academicYearName];
        }
        return [null, null];
    }
}

