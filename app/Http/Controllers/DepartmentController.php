<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Department;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DepartmentController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Display a listing of departments with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $query = Department::with(['branch', 'headOfDepartment']);

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Branch filter (only allow if SuperAdmin/cross-branch user)
            if ($request->has('branch_id') && $accessibleBranchIds === 'all') {
                $query->where('branch_id', $request->branch_id);
            }

            // Status filter
            if ($request->has('is_active')) {
                $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
            }

            // Secure search
            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('head', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'name',
                'head',
                'branch_id',
                'is_active',
                'students_count',
                'teachers_count',
                'established_date',
                'created_at',
                'updated_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by name asc)
            $departments = $this->paginateAndSort($query, $request, $sortableColumns, 'name', 'asc');

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Departments retrieved successfully',
                'data' => $departments->items(),
                'meta' => [
                    'current_page' => $departments->currentPage(),
                    'per_page' => $departments->perPage(),
                    'total' => $departments->total(),
                    'last_page' => $departments->lastPage(),
                    'from' => $departments->firstItem(),
                    'to' => $departments->lastItem(),
                    'has_more_pages' => $departments->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get departments error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch departments',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new department with transaction and validation
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-&]+$/',
                'head' => 'required|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'head_id' => 'nullable|exists:users,id',
                'established_date' => 'required|date|before_or_equal:today',
                'branch_id' => 'required|exists:branches,id',
                'description' => 'nullable|string|max:1000',
                'students_count' => 'nullable|integer|min:0|max:10000',
                'teachers_count' => 'nullable|integer|min:0|max:1000',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Sanitize inputs
            $sanitizedData = [
                'name' => strip_tags($request->name),
                'head' => strip_tags($request->head),
                'head_id' => $request->head_id,
                'description' => strip_tags($request->description),
                'established_date' => $request->established_date,
                'branch_id' => $request->branch_id,
                'students_count' => $request->students_count ?? 0,
                'teachers_count' => $request->teachers_count ?? 0,
                'is_active' => $request->is_active ?? true
            ];

            $department = Department::create($sanitizedData);

            DB::commit();

            Log::info('Department created', ['department_id' => $department->id]);

            return response()->json([
                'success' => true,
                'message' => 'Department created successfully',
                'data' => $department->load(['branch', 'headOfDepartment'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create department error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create department',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Display specific department
     */
    public function show($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid department ID'
                ], 400);
            }

            $department = Department::with(['branch', 'headOfDepartment', 'subjects'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $department
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get department error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch department',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update department with transaction
     */
    public function update(Request $request, $id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid department ID'
                ], 400);
            }

            $department = Department::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s\-&]+$/',
                'head' => 'sometimes|string|max:255|regex:/^[a-zA-Z\s]+$/',
                'head_id' => 'nullable|exists:users,id',
                'established_date' => 'sometimes|date|before_or_equal:today',
                'description' => 'nullable|string|max:1000',
                'students_count' => 'nullable|integer|min:0|max:10000',
                'teachers_count' => 'nullable|integer|min:0|max:1000',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Sanitize inputs
            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = strip_tags($request->name);
            }
            if ($request->has('head')) {
                $updateData['head'] = strip_tags($request->head);
            }
            if ($request->has('description')) {
                $updateData['description'] = strip_tags($request->description);
            }
            
            $updateData = array_merge($updateData, $request->only([
                'head_id', 'established_date', 'students_count', 
                'teachers_count', 'is_active'
            ]));

            $department->update($updateData);

            DB::commit();

            Log::info('Department updated', ['department_id' => $department->id]);

            return response()->json([
                'success' => true,
                'message' => 'Department updated successfully',
                'data' => $department->load(['branch', 'headOfDepartment'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update department error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete department (soft delete)
     */
    public function destroy($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid department ID'
                ], 400);
            }

            DB::beginTransaction();

            $department = Department::findOrFail($id);
            
            // Check if department has subjects
            if ($department->subjects()->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete department with existing subjects'
                ], 400);
            }

            $department->delete();

            DB::commit();

            Log::info('Department deleted', ['department_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Department deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete department error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete department',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Toggle department active status
     */
    public function toggleStatus($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid department ID'
                ], 400);
            }

            DB::beginTransaction();

            $department = Department::findOrFail($id);
            $department->is_active = !$department->is_active;
            $department->save();

            DB::commit();

            Log::info('Department status toggled', [
                'department_id' => $id,
                'new_status' => $department->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Department status updated',
                'data' => ['is_active' => $department->is_active]
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Department not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Toggle department status error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update department status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
