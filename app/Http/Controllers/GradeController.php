<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Exports\GradesExport;
use App\Services\PdfExportService;
use App\Services\CsvExportService;
use App\Services\ExportService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradeController extends Controller
{
    use PaginatesAndSorts;

    private function resolveBranchIdForValue(Request $request, string $value): ?int
    {
        $branchId = $this->resolveRequestedBranchId($request);
        if ($branchId) {
            return $branchId;
        }

        // SuperAdmin without branch_id: try to find a unique match across accessible branches.
        $user = $request->user();
        if (!$user || $user->role !== 'SuperAdmin') {
            return null;
        }

        $accessible = $this->getAccessibleBranchIds($request);
        if ($accessible === 'all') {
            $schoolId = $this->getCurrentSchoolId($request);
            if (!$schoolId) return null;
            $accessible = DB::table('branches')
                ->where('school_id', $schoolId)
                ->whereNull('deleted_at')
                ->pluck('id')
                ->toArray();
        }

        if (empty($accessible)) {
            return null;
        }

        $matches = DB::table('grades')
            ->select('branch_id')
            ->where('value', $value)
            ->whereIn('branch_id', $accessible)
            ->distinct()
            ->pluck('branch_id')
            ->toArray();

        if (count($matches) === 1) {
            return (int) $matches[0];
        }

        return null;
    }

    private function resolveRequestedBranchId(Request $request): ?int
    {
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);

        $requested = $request->query('branch_id') ?? $request->input('branch_id');
        if ($requested !== null && $requested !== '') {
            $requested = (int) $requested;
        } else {
            $requested = null;
        }

        // SuperAdmin: only filter to one branch when explicitly requested; otherwise show all accessible (all company branches).
        if ($request->user() && $request->user()->role === 'SuperAdmin') {
            if ($requested !== null) {
                $ids = $accessibleBranchIds === 'all' ? [] : $accessibleBranchIds;
                if ($accessibleBranchIds === 'all' || in_array($requested, $ids, true)) {
                    return $requested;
                }
            }
            return null;
        }

        if ($accessibleBranchIds === 'all') {
            return $requested;
        }

        if ($requested && in_array($requested, $accessibleBranchIds, true)) {
            return $requested;
        }

        $primary = (int) ($request->user()->branch_id ?? 0);
        return $primary && in_array($primary, $accessibleBranchIds, true) ? $primary : null;
    }

    /**
     * Get all grades with server-side pagination and sorting
     * OPTIMIZED: Added filtering for active grades in dropdown scenarios
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            $branchId = $this->resolveRequestedBranchId($request);

            // 🚀 OPTIMIZED: Select only needed columns (+ branch name for UI)
            $query = DB::table('grades')
                ->leftJoin('branches', 'branches.id', '=', 'grades.branch_id')
                ->leftJoin('schools', 'schools.id', '=', 'branches.school_id')
                ->select(
                    'grades.id',
                    'grades.school_id',
                    'grades.branch_id',
                    'grades.value',
                    'grades.label',
                    'grades.description',
                    'grades.order',
                    'grades.category',
                    'grades.is_active',
                    'grades.created_at',
                    'grades.updated_at',
                    'branches.name as branch_name'
                );

            if ($branchId) {
                $query->where('grades.branch_id', $branchId);
            } else {
                // SuperAdmin in company context: filter by company via join (fast, avoids huge whereIn lists).
                if ($user && $user->role === 'SuperAdmin' && !empty($user->company_id)) {
                    $query->where('schools.company_id', (int) $user->company_id);
                } elseif ($accessibleBranchIds === 'all') {
                    // SuperAdmin: show all branches within the current school by default.
                    $schoolId = $this->getCurrentSchoolId($request);
                    if ($schoolId) {
                        $branchIds = DB::table('branches')
                            ->where('school_id', $schoolId)
                            ->whereNull('deleted_at')
                            ->pluck('id')
                            ->toArray();
                        if (!empty($branchIds)) {
                            $query->whereIn('grades.branch_id', $branchIds);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                } else {
                    // Other users: show all branches they can access.
                    if (!empty($accessibleBranchIds)) {
                        $query->whereIn('grades.branch_id', $accessibleBranchIds);
                    } else {
                        $query->whereRaw('1 = 0');
                    }
                }
            }
            
            // Filter by active status if requested (common for dropdowns)
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Default sorting: group by branch, then grade order.
            // If UI explicitly requests sort_by, paginateAndSort will apply it.
            if (!$request->has('sort_by') && !$request->has('sort_direction') && !$request->has('sort_order')) {
                $query->orderBy('grades.branch_id', 'asc')->orderBy('grades.order', 'asc');
            }

            // Define sortable columns
            $sortableColumns = ['branch_id', 'value', 'label', 'order', 'category', 'is_active', 'created_at', 'updated_at'];

            // Apply pagination and sorting (default: 25 per page, sorted by branch_id asc)
            $grades = $this->paginateAndSort($query, $request, $sortableColumns, 'branch_id', 'asc');

            // Transform the paginated data
            $transformedData = collect($grades->items())->map(function ($grade) {
                return [
                    'branch_id' => $grade->branch_id ? (int) $grade->branch_id : null,
                    'branch_name' => $grade->branch_name ?? null,
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description ?? null,
                    'order' => $grade->order ?? 0,
                    'category' => $grade->category ?? null,
                    'is_active' => (bool) $grade->is_active,
                    'created_at' => $grade->created_at,
                    'updated_at' => $grade->updated_at
                ];
            });

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Grades retrieved successfully',
                'data' => $transformedData,
                'meta' => [
                    'current_page' => $grades->currentPage(),
                    'per_page' => $grades->perPage(),
                    'total' => $grades->total(),
                    'last_page' => $grades->lastPage(),
                    'from' => $grades->firstItem(),
                    'to' => $grades->lastItem(),
                    'has_more_pages' => $grades->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get grades error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grades',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single grade
     */
    public function show($value)
    {
        try {
            $request = request();
            $branchId = $this->resolveBranchIdForValue($request, (string) $value);
            $grade = DB::table('grades')
                ->where('branch_id', $branchId)
                ->where('value', $value)
                ->first();

            if (!$grade) {
                // If SuperAdmin and multiple branches may have same value, return a clearer message.
                if ($request->user() && $request->user()->role === 'SuperAdmin') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Grade not found. Please specify branch_id.'
                    ], 404);
                }
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description ?? null,
                    'order' => $grade->order ?? 0,
                    'category' => $grade->category ?? null,
                    'is_active' => (bool) $grade->is_active
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new grade
     */
    public function store(Request $request)
    {
        try {
            $branchId = $this->resolveRequestedBranchId($request);
            if (!$branchId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No branch context available'
                ], 422);
            }

            $validator = Validator::make($request->all(), [
                'value' => 'required|string|max:20',
                'label' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'order' => 'nullable|integer|min:0',
                'category' => 'nullable|string|in:Pre-Primary,Primary,Middle,Secondary,Senior-Secondary',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $exists = DB::table('grades')->where('branch_id', $branchId)->where('value', $request->value)->exists();
            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade value already exists for this branch'
                ], 422);
            }

            $gradeId = DB::table('grades')->insertGetId([
                'school_id' => $this->getCurrentSchoolId($request),
                'branch_id' => $branchId,
                'value' => strip_tags($request->value),
                'label' => strip_tags($request->label),
                'description' => $request->description ? strip_tags($request->description) : null,
                'order' => $request->order ?? 99,
                'category' => $request->category ?? null,
                'is_active' => $request->boolean('is_active', true),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $grade = DB::table('grades')->where('id', $gradeId)->first();

            DB::commit();

            Log::info('Grade created', ['grade_value' => $grade->value]);

            return response()->json([
                'success' => true,
                'message' => 'Grade created successfully',
                'data' => [
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description,
                    'order' => $grade->order ?? 0,
                    'category' => $grade->category ?? null,
                    'is_active' => (bool) $grade->is_active
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update grade
     */
    public function update(Request $request, $value)
    {
        try {
            $branchId = $this->resolveBranchIdForValue($request, (string) $value);
            $grade = DB::table('grades')->where('branch_id', $branchId)->where('value', $value)->first();

            if (!$grade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found. Please specify branch_id.'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'label' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string|max:500',
                'order' => 'nullable|integer|min:0',
                'category' => 'nullable|string|in:Pre-Primary,Primary,Middle,Secondary,Senior-Secondary',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = [];
            if ($request->has('label')) {
                $updateData['label'] = strip_tags($request->label);
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description ? strip_tags($request->description) : null;
            }
            if ($request->has('order')) {
                $updateData['order'] = $request->order;
            }
            if ($request->has('category')) {
                $updateData['category'] = $request->category;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }
            $updateData['updated_at'] = now();

            DB::table('grades')
                ->where('branch_id', $branchId)
                ->where('value', $value)
                ->update($updateData);

            $updatedGrade = DB::table('grades')
                ->where('branch_id', $branchId)
                ->where('value', $value)
                ->first();

            DB::commit();

            Log::info('Grade updated', ['grade_value' => $value]);

            return response()->json([
                'success' => true,
                'message' => 'Grade updated successfully',
                'data' => [
                    'value' => $updatedGrade->value,
                    'label' => $updatedGrade->label,
                    'description' => $updatedGrade->description,
                    'order' => $updatedGrade->order ?? 0,
                    'category' => $updatedGrade->category ?? null,
                    'is_active' => (bool) $updatedGrade->is_active
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete grade
     */
    public function destroy($value)
    {
        try {
            DB::beginTransaction();

            $request = request();
            $branchId = $this->resolveBranchIdForValue($request, (string) $value);
            $schoolId = $this->getCurrentSchoolId($request);
            $grade = DB::table('grades')->where('branch_id', $branchId)->where('value', $value)->first();
            
            if (!$grade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found. Please specify branch_id.'
                ], 404);
            }

            // Check if grade has students or classes
            $studentsCount = DB::table('students')->where('grade', $value)->where('branch_id', $branchId)->count();
            $classesCount = DB::table('classes')->where('grade', $value)->where('branch_id', $branchId)->count();

            if ($studentsCount > 0 || $classesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete grade with existing students or classes. Please deactivate it instead.'
                ], 400);
            }

            DB::table('grades')->where('branch_id', $branchId)->where('value', $value)->delete();

            DB::commit();

            Log::info('Grade deleted', ['grade_value' => $value]);

            return response()->json([
                'success' => true,
                'message' => 'Grade deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Export grades data
     * Supports Excel, PDF, and CSV formats
     */
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:excel,pdf,csv',
                'columns' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Get all grades (simple query, no complex filtering needed)
            $branchId = $this->resolveRequestedBranchId($request);
            $grades = DB::table('grades')->where('branch_id', $branchId)->get();

            // Transform data for export
            $exportData = collect($grades)->map(function($grade) {
                return [
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description ?? '',
                    'is_active' => (bool) $grade->is_active,
                    'created_at' => $grade->created_at,
                    'updated_at' => $grade->updated_at,
                ];
            });

            $format = $request->format;
            $columns = $request->columns;

            return match($format) {
                'excel' => $this->exportExcel($exportData, $columns),
                'pdf' => $this->exportPdf($exportData, $columns),
                'csv' => $this->exportCsv($exportData, $columns),
            };

        } catch (\Exception $e) {
            Log::error('Export grades error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export grades',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, ?array $columns)
    {
        $export = new GradesExport($data, $columns);
        $filename = (new ExportService('grades'))->generateFilename('xlsx');
        
        return Excel::download($export, $filename);
    }

    /**
     * Export to PDF
     */
    protected function exportPdf($data, ?array $columns)
    {
        $pdfService = new PdfExportService('grades');
        
        if ($columns) {
            $pdfService->setColumns($columns);
        }
        
        // Grades have few columns, A4 is sufficient
        $pdfService->setPaperSize('a4');
        $pdfService->setOrientation('landscape');
        
        $pdf = $pdfService->generate($data, 'Grades Report');
        $filename = (new ExportService('grades'))->generateFilename('pdf');
        
        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    protected function exportCsv($data, ?array $columns)
    {
        $csvService = new CsvExportService('grades');
        
        if ($columns) {
            $csvService->setColumns($columns);
        }
        
        $filename = (new ExportService('grades'))->generateFilename('csv');
        
        return $csvService->generate($data, $filename);
    }
}

