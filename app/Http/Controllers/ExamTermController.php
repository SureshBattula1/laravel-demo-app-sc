<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\ExamTerm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ExamTermController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            // 🚀 OPTIMIZED: Select only needed columns
            $query = ExamTerm::select([
                'id', 'name', 'code', 'branch_id', 'academic_year', 'academic_year_id',
                'start_date', 'end_date', 'weightage', 'description', 'is_active', 'created_at',
            ])->with(['branch:id,name,code']);

            // Branch filtering
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                $query->whereIn('branch_id', $accessibleBranchIds ?: [0]);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->filled('academic_year') || $request->filled('academic_year_id')) {
                [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
                $this->applyAcademicYearScope($query, $academicYearId, $academicYearName);
            } elseif ($request->attributes->get('academic_year_id')) {
                [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
                $this->applyAcademicYearScope($query, $academicYearId, $academicYearName);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Search filter - search across name, code, academic_year, and branch name
            if ($request->has('search') && ! empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', $search.'%')
                        ->orWhere('code', 'like', $search.'%')
                        ->orWhere('academic_year', 'like', $search.'%')
                        ->orWhereHas('branch', function ($q) use ($search) {
                            $q->where('name', 'like', $search.'%')
                                ->orWhere('code', 'like', $search.'%');
                        });
                });
            }

            $terms = $this->paginateAndSort($query, $request, [
                'id', 'name', 'code', 'start_date', 'end_date', 'weightage', 'is_active', 'created_at',
            ], 'start_date', 'desc');

            return response()->json([
                'success' => true,
                'data' => $terms->items(),
                'meta' => [
                    'current_page' => $terms->currentPage(),
                    'per_page' => $terms->perPage(),
                    'total' => $terms->total(),
                    'last_page' => $terms->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get exam terms error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to fetch exam terms'], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            // Code is unique per branch (multi-tenant), not globally.
            'code' => [
                'required', 'string', 'max:50',
                Rule::unique('exam_terms', 'code')->where(fn ($q) => $q->where('branch_id', $request->branch_id)),
            ],
            'branch_id' => 'required|exists:branches,id',
            'academic_year' => 'required_without:academic_year_id|nullable|string|max:20',
            'academic_year_id' => 'nullable|exists:academic_years,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'weightage' => 'nullable|integer|min:0|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Tenant guard.
        if (! $this->canManageBranch($request, (int) $request->branch_id)) {
            return response()->json(['success' => false, 'message' => 'You do not have access to this branch'], 403);
        }

        DB::beginTransaction();
        try {
            $branch = \App\Models\Branch::find($request->branch_id);
            [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
            $termData = $request->all();
            $termData['school_id'] = $branch ? $branch->school_id : null;
            $termData['academic_year_id'] = $academicYearId;
            $termData['academic_year'] = $academicYearName ?? $request->academic_year;
            $term = ExamTerm::create($termData);
            DB::commit();

            return response()->json(['success' => true, 'data' => $term->load('branch'), 'message' => 'Exam term created'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create exam term error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to create exam term'], 500);
        }
    }

    public function show(Request $request, $id)
    {
        try {
            $term = ExamTerm::with(['branch', 'exams'])->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $term->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Exam term not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $term]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Exam term not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $term = ExamTerm::findOrFail($id);
            if (! $this->canManageBranch($request, (int) $term->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Exam term not found'], 404);
            }
            // Don't allow moving a term to another branch via mass-assignment.
            $updateData = $request->except(['branch_id', 'school_id', 'id']);
            if ($request->filled('academic_year') || $request->filled('academic_year_id')) {
                [$academicYearId, $academicYearName] = $this->resolveAcademicYearFromRequest($request);
                if ($academicYearId) {
                    $updateData['academic_year_id'] = $academicYearId;
                }
                if ($academicYearName) {
                    $updateData['academic_year'] = $academicYearName;
                }
            }
            $term->update($updateData);

            return response()->json(['success' => true, 'data' => $term->fresh(['branch']), 'message' => 'Exam term updated']);
        } catch (\Exception $e) {
            Log::error('Update exam term error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to update exam term'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $term = ExamTerm::findOrFail($id);
            if (! $this->canManageBranch($request, (int) $term->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Exam term not found'], 404);
            }
            if ($term->exams()->count() > 0) {
                return response()->json(['success' => false, 'message' => 'Cannot delete term with existing exams'], 400);
            }
            $term->delete();

            return response()->json(['success' => true, 'message' => 'Exam term deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete exam term'], 500);
        }
    }
}
