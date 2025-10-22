<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Section;
use App\Exports\SectionsExport;
use App\Services\PdfExportService;
use App\Services\CsvExportService;
use App\Services\ExportService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SectionController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all sections with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $query = Section::with(['branch', 'classTeacher', 'class']);

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters (only allow if SuperAdmin/cross-branch user)
            if ($request->has('branch_id') && $accessibleBranchIds === 'all') {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('room_number', 'like', '%' . $search . '%');
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'code',
                'name',
                'branch_id',
                'grade_level',
                'capacity',
                'current_strength',
                'room_number',
                'is_active',
                'created_at',
                'updated_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by name asc)
            $sections = $this->paginateAndSort($query, $request, $sortableColumns, 'name', 'asc');

            // OPTIMIZATION: Get student counts for all sections in ONE query to avoid N+1
            $sectionIds = $sections->pluck('id')->toArray();
            $studentCounts = DB::table('students')
                ->select(
                    'branch_id',
                    'grade',
                    'section',
                    DB::raw('COUNT(*) as count')
                )
                ->where('student_status', 'Active')
                ->whereIn('branch_id', $sections->pluck('branch_id')->unique())
                ->groupBy('branch_id', 'grade', 'section')
                ->get()
                ->mapWithKeys(function($item) {
                    $key = $item->branch_id . '_' . $item->grade . '_' . $item->section;
                    return [$key => $item->count];
                })
                ->toArray();

            // Enhance each section with grade details and actual student count
            $sections->getCollection()->transform(function ($section) use ($studentCounts) {
                // Append grade_details accessor data
                $section->append('grade_details');
                // Override current_strength with pre-fetched count (no N+1!)
                $key = $section->branch_id . '_' . $section->grade_level . '_' . $section->name;
                $section->current_strength = $studentCounts[$key] ?? 0;
                $section->actual_strength = $studentCounts[$key] ?? 0;
                return $section;
            });

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Sections retrieved successfully',
                'data' => $sections->items(),
                'meta' => [
                    'current_page' => $sections->currentPage(),
                    'per_page' => $sections->perPage(),
                    'total' => $sections->total(),
                    'last_page' => $sections->lastPage(),
                    'from' => $sections->firstItem(),
                    'to' => $sections->lastItem(),
                    'has_more_pages' => $sections->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get sections error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sections',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new section
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:50',
                'code' => 'required|string|max:50|unique:sections',
                'grade_level' => 'nullable|string|max:20',
                'capacity' => 'required|integer|min:1|max:100',
                'room_number' => 'nullable|string|max:50',
                'class_teacher_id' => 'nullable|exists:users,id',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Check for duplicate
            $exists = Section::where('branch_id', $request->branch_id)
                ->where('name', $request->name)
                ->where('grade_level', $request->grade_level)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A section with this name already exists for this branch and grade'
                ], 400);
            }

            $section = Section::create([
                'branch_id' => $request->branch_id,
                'name' => strtoupper(strip_tags($request->name)),
                'code' => strtoupper(strip_tags($request->code)),
                'grade_level' => $request->grade_level ? strip_tags($request->grade_level) : null,
                'capacity' => $request->capacity,
                'current_strength' => 0,
                'room_number' => $request->room_number ? strip_tags($request->room_number) : null,
                'class_teacher_id' => $request->class_teacher_id,
                'description' => $request->description ? strip_tags($request->description) : null,
                'is_active' => $request->boolean('is_active', true)
            ]);

            DB::commit();

            Log::info('Section created', ['section_id' => $section->id]);

            return response()->json([
                'success' => true,
                'message' => 'Section created successfully',
                'data' => $section->load(['branch', 'classTeacher'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create section error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create section',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single section
     */
    public function show($id)
    {
        try {
            $section = Section::with(['branch', 'classTeacher', 'class'])
                ->findOrFail($id);

            // OPTIMIZATION: Get actual student count without accessor (avoid extra query)
            $actualCount = DB::table('students')
                ->where('branch_id', $section->branch_id)
                ->where('grade', $section->grade_level)
                ->where('section', $section->name)
                ->where('student_status', 'Active')
                ->count();

            // Append grade details and update current_strength with actual count
            $section->append('grade_details');
            $section->current_strength = $actualCount;
            $section->actual_strength = $actualCount;

            return response()->json([
                'success' => true,
                'data' => $section
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get section error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update section
     */
    public function update(Request $request, $id)
    {
        try {
            $section = Section::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:50',
                'code' => 'sometimes|string|max:50|unique:sections,code,' . $id,
                'grade_level' => 'nullable|string|max:20',
                'capacity' => 'sometimes|integer|min:1|max:100',
                'room_number' => 'nullable|string|max:50',
                'class_teacher_id' => 'nullable|exists:users,id',
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

            $updateData = $request->only([
                'name', 'code', 'grade_level', 'capacity', 'room_number', 
                'class_teacher_id', 'description', 'is_active'
            ]);

            // Sanitize strings
            foreach (['name', 'code', 'grade_level', 'room_number', 'description'] as $field) {
                if (isset($updateData[$field])) {
                    $updateData[$field] = strip_tags($updateData[$field]);
                    if (in_array($field, ['name', 'code'])) {
                        $updateData[$field] = strtoupper($updateData[$field]);
                    }
                }
            }

            $section->update($updateData);

            DB::commit();

            Log::info('Section updated', ['section_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully',
                'data' => $section->fresh(['branch', 'classTeacher'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update section error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update section'
            ], 500);
        }
    }

    /**
     * Delete section
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $section = Section::findOrFail($id);
            
            // Check if section has students
            if ($section->current_strength > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete section with enrolled students'
                ], 400);
            }

            $section->delete();

            DB::commit();

            Log::info('Section deleted', ['section_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Section deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete section error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete section'
            ], 500);
        }
    }

    /**
     * Toggle section status
     */
    public function toggleStatus($id)
    {
        try {
            $section = Section::findOrFail($id);
            $section->is_active = !$section->is_active;
            $section->save();

            return response()->json([
                'success' => true,
                'message' => 'Section status updated successfully',
                'data' => $section
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update section status'
            ], 500);
        }
    }

    /**
     * Export sections data
     * Supports Excel, PDF, and CSV formats with filtering
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

            // Build query with same filters as index method
            $query = $this->buildSectionQuery($request);

            // Get all matching records
            $sections = $query->get();

            // Transform data for export
            $exportData = collect($sections)->map(function($section) {
                $classTeacherName = '';
                if ($section->classTeacher) {
                    $classTeacherName = $section->classTeacher->first_name . ' ' . $section->classTeacher->last_name;
                }

                return [
                    'id' => $section->id,
                    'code' => $section->code,
                    'name' => $section->name,
                    'grade_label' => $section->grade_label,
                    'branch_name' => $section->branch->name ?? '',
                    'class_teacher_name' => $classTeacherName,
                    'capacity' => $section->capacity,
                    'current_strength' => $section->current_strength,
                    'actual_strength' => $section->actual_strength,
                    'room_number' => $section->room_number,
                    'description' => $section->description ?? '',
                    'is_active' => $section->is_active,
                    'created_at' => $section->created_at,
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
            Log::error('Export sections error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export sections',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Build section query with filters (reusable for index and export)
     */
    protected function buildSectionQuery(Request $request)
    {
        $query = Section::with(['branch', 'classTeacher', 'class']);

        // Apply branch filtering
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $query->whereIn('branch_id', $accessibleBranchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Filter by branch
        if ($request->has('branch_id') && $request->branch_id !== '' && $accessibleBranchIds === 'all') {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by grade level
        if ($request->has('grade_level') && $request->grade_level !== '') {
            $query->where('grade_level', $request->grade_level);
        }

        // Filter by active status
        if ($request->has('is_active') && $request->is_active !== '') {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Global search
        if ($request->has('search') && $request->search !== '') {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('code', 'like', '%' . $searchTerm . '%')
                  ->orWhere('room_number', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query;
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, ?array $columns)
    {
        $export = new SectionsExport($data, $columns);
        $filename = (new ExportService('sections'))->generateFilename('xlsx');
        
        return Excel::download($export, $filename);
    }

    /**
     * Export to PDF
     */
    protected function exportPdf($data, ?array $columns)
    {
        $pdfService = new PdfExportService('sections');
        
        if ($columns) {
            $pdfService->setColumns($columns);
        }
        
        // Use A3 paper for sections to accommodate more columns
        $pdfService->setPaperSize('a3');
        $pdfService->setOrientation('landscape');
        
        $pdf = $pdfService->generate($data, 'Sections Report');
        $filename = (new ExportService('sections'))->generateFilename('pdf');
        
        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    protected function exportCsv($data, ?array $columns)
    {
        $csvService = new CsvExportService('sections');
        
        if ($columns) {
            $csvService->setColumns($columns);
        }
        
        $filename = (new ExportService('sections'))->generateFilename('csv');
        
        return $csvService->generate($data, $filename);
    }
}

