<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BranchController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all branches with filters and server-side pagination/sorting
     */
    public function index(Request $request)
    {
        try {
            $query = Branch::with(['parentBranch', 'childBranches']);

            // Filter by active status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Filter by branch type
            if ($request->has('branch_type')) {
                $query->where('branch_type', $request->branch_type);
            }

            // Filter by region
            if ($request->has('region')) {
                $query->where('region', $request->region);
            }

            // Filter by city
            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            // Filter by parent branch
            if ($request->has('parent_id')) {
                if ($request->parent_id === 'null' || $request->parent_id === '0') {
                    $query->whereNull('parent_branch_id');
                } else {
                    $query->where('parent_branch_id', $request->parent_id);
                }
            }

            // Search functionality
            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('city', 'like', '%' . $search . '%')
                      ->orWhere('region', 'like', '%' . $search . '%');
                });
            }

            // Hierarchical view (nested structure) - returns all without pagination
            if ($request->boolean('hierarchical')) {
                $branches = $query->whereNull('parent_branch_id')
                    ->with('allDescendants')
                    ->orderBy('name')
                    ->get();
                
                // Add computed fields for hierarchical view
                $branches->each(function($branch) {
                    $branch->capacity_utilization = $branch->getCapacityUtilization();
                    $branch->has_children = $branch->hasChildren();
                });
                
                return response()->json([
                    'success' => true,
                    'data' => $branches,
                    'count' => $branches->count()
                ]);
            }

            // Define sortable columns for security
            $sortableColumns = [
                'id',
                'name',
                'code',
                'branch_type',
                'city',
                'state',
                'region',
                'status',
                'is_active',
                'total_capacity',
                'current_enrollment',
                'established_date',
                'created_at',
                'updated_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by name asc)
            $branches = $this->paginateAndSort($query, $request, $sortableColumns, 'name', 'asc');

            // Add computed fields to paginated results
            $branchesData = collect($branches->items())->map(function($branch) {
                $branch->capacity_utilization = $branch->getCapacityUtilization();
                $branch->has_children = $branch->hasChildren();
                return $branch;
            })->toArray();

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Branches retrieved successfully',
                'data' => $branchesData,
                'meta' => [
                    'current_page' => $branches->currentPage(),
                    'per_page' => $branches->perPage(),
                    'total' => $branches->total(),
                    'last_page' => $branches->lastPage(),
                    'from' => $branches->firstItem(),
                    'to' => $branches->lastItem(),
                    'has_more_pages' => $branches->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get branches error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                // Basic Information
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:branches',
                'branch_type' => 'required|in:HeadOffice,RegionalOffice,School,Campus,SubBranch',
                'parent_branch_id' => 'nullable|exists:branches,id',
                
                // Location
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'country' => 'required|string|max:100',
                'region' => 'nullable|string|max:100',
                'pincode' => 'required|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'timezone' => 'nullable|string|max:50',
                
                // Contact Information
                'phone' => 'required|string|max:20|unique:branches',
                'email' => 'required|email|max:255|unique:branches',
                'website' => 'nullable|url|max:255',
                'fax' => 'nullable|string|max:20',
                'emergency_contact' => 'nullable|string|max:20',
                
                // Principal Information
                'principal_name' => 'nullable|string|max:255',
                'principal_contact' => 'nullable|string|max:20',
                'principal_email' => 'nullable|email|max:255',
                
                // Dates
                'established_date' => 'nullable|date|before_or_equal:today',
                'opening_date' => 'nullable|date',
                'closing_date' => 'nullable|date|after:opening_date',
                
                // Academic Information
                'board' => 'nullable|string|max:100',
                'affiliation_number' => 'nullable|string|max:100',
                'accreditations' => 'nullable|array',
                'grades_offered' => 'nullable|array',
                'academic_year_start' => 'nullable|string|max:5',
                'academic_year_end' => 'nullable|string|max:5',
                'current_academic_year' => 'nullable|string|max:20',
                
                // Capacity
                'total_capacity' => 'nullable|integer|min:0',
                'facilities' => 'nullable|array',
                
                // Financial
                'tax_id' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:50',
                'ifsc_code' => 'nullable|string|max:20',
                
                // Flags
                'is_main_branch' => 'boolean',
                'is_residential' => 'boolean',
                'has_hostel' => 'boolean',
                'has_transport' => 'boolean',
                'has_library' => 'boolean',
                'has_lab' => 'boolean',
                'has_canteen' => 'boolean',
                'has_sports' => 'boolean',
                
                // Status
                'status' => 'nullable|in:Active,Inactive,UnderConstruction,Maintenance,Closed',
                'is_active' => 'boolean',
                'settings' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $branchData = $request->except(['logo']);
            $branchData['code'] = strtoupper($branchData['code'] ?? '');
            $branchData['status'] = $branchData['status'] ?? 'Active';
            $branchData['current_enrollment'] = 0;
            
            // Sanitize text fields
            if (isset($branchData['name'])) $branchData['name'] = strip_tags($branchData['name']);
            if (isset($branchData['address'])) $branchData['address'] = strip_tags($branchData['address']);
            if (isset($branchData['city'])) $branchData['city'] = strip_tags($branchData['city']);
            if (isset($branchData['email'])) $branchData['email'] = filter_var($branchData['email'], FILTER_SANITIZE_EMAIL);

            // Handle logo upload if present
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('branch-logos', 'public');
                $branchData['logo'] = $logoPath;
            }

            $branch = Branch::create($branchData);

            DB::commit();

            Log::info('Branch created', [
                'branch_id' => $branch->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch created successfully',
                'data' => $branch->load('parentBranch')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            $branch = Branch::with([
                'parentBranch',
                'childBranches',
                'departments',
                'users',
                'branchSettings'
            ])->findOrFail($id);
            
            // Add computed fields
            $branch->capacity_utilization = $branch->getCapacityUtilization();
            $branch->has_children = $branch->hasChildren();
            $branch->has_parent = $branch->hasParent();
            $branch->total_users = $branch->users()->count();
            $branch->total_students = $branch->students()->count();
            $branch->total_teachers = $branch->teachers()->count();
            
            return response()->json([
                'success' => true,
                'data' => $branch
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            $branch = Branch::findOrFail($id);

            $validator = Validator::make($request->all(), [
                // Basic Information
                'name' => 'sometimes|string|max:255',
                'code' => 'sometimes|string|max:50|unique:branches,code,' . $id,
                'branch_type' => 'sometimes|in:HeadOffice,RegionalOffice,School,Campus,SubBranch',
                'parent_branch_id' => 'nullable|exists:branches,id',
                
                // Location
                'address' => 'sometimes|string|max:500',
                'city' => 'sometimes|string|max:100',
                'state' => 'sometimes|string|max:100',
                'country' => 'sometimes|string|max:100',
                'region' => 'nullable|string|max:100',
                'pincode' => 'sometimes|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'timezone' => 'nullable|string|max:50',
                
                // Contact Information
                'phone' => 'sometimes|string|max:20|unique:branches,phone,' . $id,
                'email' => 'sometimes|email|max:255|unique:branches,email,' . $id,
                'website' => 'nullable|url|max:255',
                'fax' => 'nullable|string|max:20',
                'emergency_contact' => 'nullable|string|max:20',
                
                // Principal Information
                'principal_name' => 'nullable|string|max:255',
                'principal_contact' => 'nullable|string|max:20',
                'principal_email' => 'nullable|email|max:255',
                
                // Dates
                'established_date' => 'nullable|date|before_or_equal:today',
                'opening_date' => 'nullable|date',
                'closing_date' => 'nullable|date|after:opening_date',
                
                // Academic Information
                'board' => 'nullable|string|max:100',
                'affiliation_number' => 'nullable|string|max:100',
                'accreditations' => 'nullable|array',
                'grades_offered' => 'nullable|array',
                'academic_year_start' => 'nullable|string|max:5',
                'academic_year_end' => 'nullable|string|max:5',
                'current_academic_year' => 'nullable|string|max:20',
                
                // Capacity
                'total_capacity' => 'nullable|integer|min:0',
                'current_enrollment' => 'nullable|integer|min:0',
                'facilities' => 'nullable|array',
                
                // Financial
                'tax_id' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:50',
                'ifsc_code' => 'nullable|string|max:20',
                
                // Flags
                'is_main_branch' => 'sometimes|boolean',
                'is_residential' => 'sometimes|boolean',
                'has_hostel' => 'sometimes|boolean',
                'has_transport' => 'sometimes|boolean',
                'has_library' => 'sometimes|boolean',
                'has_lab' => 'sometimes|boolean',
                'has_canteen' => 'sometimes|boolean',
                'has_sports' => 'sometimes|boolean',
                
                // Status
                'status' => 'sometimes|in:Active,Inactive,UnderConstruction,Maintenance,Closed',
                'is_active' => 'sometimes|boolean',
                'settings' => 'nullable|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->except(['logo', 'id', 'created_at', 'updated_at', 'deleted_at']);
            
            // Sanitize code field
            if (isset($updateData['code'])) {
                $updateData['code'] = strtoupper($updateData['code']);
            }
            
            // Sanitize text fields
            if (isset($updateData['name'])) $updateData['name'] = strip_tags($updateData['name']);
            if (isset($updateData['address'])) $updateData['address'] = strip_tags($updateData['address']);
            if (isset($updateData['city'])) $updateData['city'] = strip_tags($updateData['city']);
            if (isset($updateData['email'])) $updateData['email'] = filter_var($updateData['email'], FILTER_SANITIZE_EMAIL);

            // Handle logo upload if present
            if ($request->hasFile('logo')) {
                // Delete old logo if exists
                if ($branch->logo && \Storage::disk('public')->exists($branch->logo)) {
                    \Storage::disk('public')->delete($branch->logo);
                }
                $logoPath = $request->file('logo')->store('branch-logos', 'public');
                $updateData['logo'] = $logoPath;
            }

            $branch->update($updateData);

            DB::commit();

            Log::info('Branch updated', [
                'branch_id' => $branch->id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch updated successfully',
                'data' => $branch->fresh(['parentBranch', 'childBranches'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Soft delete branch by updating status to "Closed"
     * This keeps the record in database but marks it as deleted
     */
    public function destroy($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            DB::beginTransaction();

            $branch = Branch::findOrFail($id);
            
            // Check if already deleted (status is Closed)
            if ($branch->status === 'Closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch is already deleted'
                ], 400);
            }

            // Update status to Closed and set is_active to false
            $branch->update([
                'status' => 'Closed',
                'is_active' => false,
                'closing_date' => now()
            ]);

            // Also deactivate all child branches recursively
            $this->deactivateChildBranches($branch);

            DB::commit();

            Log::info('Branch soft deleted (status updated to Closed)', [
                'branch_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch deleted successfully',
                'data' => $branch->fresh()
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Restore a deleted branch (change status from Closed to Active)
     */
    public function restore($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            DB::beginTransaction();

            $branch = Branch::findOrFail($id);

            if ($branch->status !== 'Closed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Branch is not deleted. Only closed branches can be restored.'
                ], 400);
            }

            // Check if parent branch is active
            if ($branch->parent_branch_id) {
                $parent = Branch::find($branch->parent_branch_id);
                if (!$parent || $parent->status === 'Closed' || !$parent->is_active) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot restore branch: Parent branch is not active'
                    ], 400);
                }
            }

            // Restore the branch
            $branch->update([
                'status' => 'Active',
                'is_active' => true,
                'closing_date' => null
            ]);

            DB::commit();

            Log::info('Branch restored', [
                'branch_id' => $id,
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch restored successfully',
                'data' => $branch->fresh(['parentBranch', 'childBranches'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Branch not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Restore branch error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore branch',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get deleted branches (status = Closed)
     */
    public function getDeleted(Request $request)
    {
        try {
            $query = Branch::with(['parentBranch', 'childBranches'])
                ->where('status', 'Closed');

            // Search in deleted branches
            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('city', 'like', '%' . $search . '%');
                });
            }

            // Filter by branch type
            if ($request->has('branch_type')) {
                $query->where('branch_type', $request->branch_type);
            }

            $deletedBranches = $query->orderBy('closing_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $deletedBranches,
                'count' => $deletedBranches->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get deleted branches error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch deleted branches',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Bulk delete branches (update status to Closed)
     */
    public function bulkDelete(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_ids' => 'required|array|min:1',
                'branch_ids.*' => 'required|integer|exists:branches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $deletedCount = 0;
            $failedIds = [];

            foreach ($request->branch_ids as $branchId) {
                $branch = Branch::find($branchId);
                
                if ($branch && $branch->status !== 'Closed') {
                    $branch->update([
                        'status' => 'Closed',
                        'is_active' => false,
                        'closing_date' => now()
                    ]);
                    
                    // Deactivate child branches
                    $this->deactivateChildBranches($branch);
                    
                    $deletedCount++;
                } else {
                    $failedIds[] = [
                        'id' => $branchId,
                        'reason' => 'Already deleted or not found'
                    ];
                }
            }

            DB::commit();

            Log::info('Bulk delete branches', [
                'deleted_count' => $deletedCount,
                'failed_count' => count($failedIds),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully deleted {$deletedCount} branch(es)",
                'deleted_count' => $deletedCount,
                'failed' => $failedIds
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk delete error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Bulk delete failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Bulk restore branches
     */
    public function bulkRestore(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_ids' => 'required|array|min:1',
                'branch_ids.*' => 'required|integer|exists:branches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $restoredCount = 0;
            $failedIds = [];

            foreach ($request->branch_ids as $branchId) {
                $branch = Branch::find($branchId);
                
                if ($branch && $branch->status === 'Closed') {
                    // Check if parent is active
                    if ($branch->parent_branch_id) {
                        $parent = Branch::find($branch->parent_branch_id);
                        if (!$parent || $parent->status === 'Closed' || !$parent->is_active) {
                            $failedIds[] = [
                                'id' => $branchId,
                                'reason' => 'Parent branch is not active'
                            ];
                            continue;
                        }
                    }
                    
                    $branch->update([
                        'status' => 'Active',
                        'is_active' => true,
                        'closing_date' => null
                    ]);
                    $restoredCount++;
                } else {
                    $failedIds[] = [
                        'id' => $branchId,
                        'reason' => 'Not deleted or not found'
                    ];
                }
            }

            DB::commit();

            Log::info('Bulk restore branches', [
                'restored_count' => $restoredCount,
                'failed_count' => count($failedIds),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => "Successfully restored {$restoredCount} branch(es)",
                'restored_count' => $restoredCount,
                'failed' => $failedIds
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk restore error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Bulk restore failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Helper method to recursively deactivate child branches
     */
    private function deactivateChildBranches($branch)
    {
        $children = $branch->childBranches;
        
        foreach ($children as $child) {
            if ($child->status !== 'Closed') {
                $child->update([
                    'status' => 'Inactive',
                    'is_active' => false
                ]);
                
                // Recursively deactivate children's children
                $this->deactivateChildBranches($child);
            }
        }
    }

    public function stats($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            $branch = Branch::findOrFail($id);
            
            $stats = [
                'total_students' => $branch->students()->count(),
                'total_teachers' => $branch->teachers()->count(),
                'total_departments' => $branch->departments()->count(),
                'active_students' => $branch->students()->where('is_active', true)->count(),
                'active_teachers' => $branch->teachers()->where('is_active', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Get branch stats error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch statistics'
            ], 500);
        }
    }

    public function toggleStatus($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid branch ID'
                ], 400);
            }

            DB::beginTransaction();

            $branch = Branch::findOrFail($id);
            $branch->is_active = !$branch->is_active;
            $branch->save();

            DB::commit();

            Log::info('Branch status toggled', [
                'branch_id' => $id,
                'new_status' => $branch->is_active
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch status updated',
                'data' => ['is_active' => $branch->is_active]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Toggle branch status error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch status'
            ], 500);
        }
    }

    /**
     * Get branches accessible to current user
     * This endpoint is used by frontend to populate branch selectors
     */
    public function getAccessibleBranches(Request $request)
    {
        try {
            $user = $request->user();
            
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            // SuperAdmin or users with cross-branch permission see all branches
            if ($accessibleBranchIds === 'all') {
                $branches = Branch::where('is_active', true)
                    ->orderBy('name')
                    ->get();
            } else {
                $branches = Branch::whereIn('id', $accessibleBranchIds)
                    ->where('is_active', true)
                    ->orderBy('name')
                    ->get();
            }
            
            // Include permission info for frontend
            $canSelectBranch = $user->role === 'SuperAdmin' 
                            || $user->role === 'BranchAdmin' 
                            || $user->hasCrossBranchAccess();
            
            return response()->json([
                'success' => true,
                'data' => $branches,
                'user_branch_id' => $user->branch_id,
                'user_role' => $user->role,
                'can_select_branch' => $canSelectBranch,
                'has_cross_branch_access' => $user->hasCrossBranchAccess(),
                'can_manage_all_branches' => $user->canManageAllBranches(),
                'can_view_all_branches' => $user->canViewAllBranches(),
                'accessible_branch_ids' => $accessibleBranchIds === 'all' ? 'all' : $accessibleBranchIds
            ]);
            
        } catch (\Exception $e) {
            Log::error('Get accessible branches error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branches',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
