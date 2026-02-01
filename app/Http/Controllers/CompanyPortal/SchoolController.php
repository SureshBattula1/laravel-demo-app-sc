<?php

namespace App\Http\Controllers\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Company;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SchoolController extends Controller
{
    /**
     * List schools for current company admin
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $query = School::with(['company', 'mainBranch'])
                ->where('company_id', $user->company_id)
                ->withCount(['branches', 'activeBranches']);

            // Filtering
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('code', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $request->get('per_page', 15);
            $schools = $query->paginate($perPage);

            // Add student and teacher counts to each school
            $schoolsData = $schools->getCollection()->map(function ($school) {
                $school->branches_count = $school->branches_count ?? 0;
                $school->active_branches_count = $school->active_branches_count ?? 0;
                
                // Get total students and teachers from branches in this school
                $branchIds = DB::table('branches')
                    ->where('school_id', $school->id)
                    ->pluck('id');
                
                $school->total_students = DB::table('users')
                    ->whereIn('branch_id', $branchIds)
                    ->where('role', 'Student')
                    ->where('is_active', true)
                    ->count();
                
                $school->total_teachers = DB::table('users')
                    ->whereIn('branch_id', $branchIds)
                    ->where('role', 'Teacher')
                    ->where('is_active', true)
                    ->count();
                
                return $school;
            });

            return response()->json([
                'success' => true,
                'data' => $schoolsData->values()->all(),
                'meta' => [
                    'current_page' => $schools->currentPage(),
                    'last_page' => $schools->lastPage(),
                    'per_page' => $schools->perPage(),
                    'total' => $schools->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('School list error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch schools'
            ], 500);
        }
    }

    /**
     * Get school details
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $school = School::with(['company', 'mainBranch', 'branches'])
                ->where('company_id', $user->company_id)
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $school
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'School not found'
            ], 404);
        }
    }

    /**
     * Create new school
     */
    public function store(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:schools,code',
                'main_branch_id' => 'nullable|exists:branches,id',
                'status' => 'sometimes|in:Active,Inactive,Suspended,UnderConstruction'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $school = School::create([
                'company_id' => $user->company_id,
                'name' => $request->name,
                'code' => $request->code,
                'main_branch_id' => $request->main_branch_id,
                'status' => $request->status ?? 'Active',
                'settings' => $request->settings ?? []
            ]);

            // If main_branch_id is provided, update the branch's school_id
            if ($request->main_branch_id) {
                Branch::where('id', $request->main_branch_id)->update(['school_id' => $school->id]);
            }

            DB::commit();

            Log::info('School created', ['school_id' => $school->id, 'company_id' => $user->company_id, 'created_by' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'School created successfully',
                'data' => $school->load(['company', 'mainBranch'])
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School creation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create school'
            ], 500);
        }
    }

    /**
     * Update school
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $school = School::where('company_id', $user->company_id)->findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:schools,code,' . $id,
                'main_branch_id' => 'sometimes|nullable|exists:branches,id',
                'status' => 'sometimes|in:Active,Inactive,Suspended,UnderConstruction',
                'settings' => 'sometimes|array'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $school->update($request->only(['name', 'code', 'main_branch_id', 'status', 'settings']));

            // If main_branch_id is updated, update the branch's school_id
            if ($request->has('main_branch_id')) {
                // Remove school_id from old main branch
                if ($school->getOriginal('main_branch_id')) {
                    Branch::where('id', $school->getOriginal('main_branch_id'))
                        ->where('school_id', $school->id)
                        ->update(['school_id' => null]);
                }
                
                // Set school_id on new main branch
                if ($request->main_branch_id) {
                    Branch::where('id', $request->main_branch_id)->update(['school_id' => $school->id]);
                }
            }

            DB::commit();

            Log::info('School updated', ['school_id' => $school->id, 'updated_by' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'School updated successfully',
                'data' => $school->fresh()->load(['company', 'mainBranch'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School update error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update school'
            ], 500);
        }
    }

    /**
     * Activate school
     */
    public function activate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $school = School::where('company_id', $user->company_id)->findOrFail($id);

            if ($school->status === 'Active') {
                return response()->json([
                    'success' => false,
                    'message' => 'School is already active'
                ], 422);
            }

            DB::beginTransaction();

            $school->activate();

            DB::commit();

            Log::info('School activated', ['school_id' => $school->id, 'activated_by' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'School activated successfully',
                'data' => $school->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School activation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to activate school'
            ], 500);
        }
    }

    /**
     * Deactivate school
     */
    public function deactivate(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $school = School::where('company_id', $user->company_id)->findOrFail($id);

            if ($school->status === 'Inactive') {
                return response()->json([
                    'success' => false,
                    'message' => 'School is already inactive'
                ], 422);
            }

            DB::beginTransaction();

            $school->deactivate();

            DB::commit();

            Log::info('School deactivated', ['school_id' => $school->id, 'deactivated_by' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'School deactivated successfully',
                'data' => $school->fresh()
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School deactivation error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate school'
            ], 500);
        }
    }

    /**
     * Delete school
     */
    public function destroy(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $school = School::where('company_id', $user->company_id)->findOrFail($id);

            // Check if school has branches
            if ($school->branches()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete school with branches. Please delete or transfer branches first.'
                ], 422);
            }

            DB::beginTransaction();

            $school->delete();

            DB::commit();

            Log::info('School deleted', ['school_id' => $id, 'deleted_by' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'School deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School deletion error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete school'
            ], 500);
        }
    }

    /**
     * Get school statistics
     */
    public function statistics(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            $school = School::where('company_id', $user->company_id)->findOrFail($id);

            $stats = [
                'total_branches' => $school->branches()->count(),
                'active_branches' => $school->activeBranches()->count(),
                'total_students' => $school->students()->count(),
                'total_teachers' => $school->teachers()->count(),
                'status' => $school->status,
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }

    /**
     * Get users from a school
     * Returns users from all branches in the school, prioritizing BranchAdmin role
     */
    public function getSchoolUsers(Request $request, $id)
    {
        try {
            $user = $request->user();
            
            if (!$user || $user->user_type !== 'CompanyAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Validate school belongs to company admin's company
            $school = School::where('company_id', $user->company_id)->findOrFail($id);

            // Get all branch IDs for this school
            $branchIds = Branch::where('school_id', $school->id)->pluck('id');

            // Build base query conditions
            $baseConditions = [
                ['is_active', '=', true],
                ['user_type', '!=', 'CompanyAdmin'],
            ];

            // Filter by role if provided
            $rolesToInclude = [];
            if ($request->has('role') && $request->role !== 'All' && $request->role !== '' && $request->role !== null) {
                $rolesToInclude = [$request->role];
            } else {
                // When no role filter or "All" is selected, include all relevant roles
                $rolesToInclude = ['BranchAdmin', 'SuperAdmin', 'Admin', 'Staff', 'Teacher'];
            }

            // Get users from branches - this is the primary source
            // Users whose branch belongs to the school
            $usersFromBranches = collect();
            if ($branchIds->isNotEmpty()) {
                $usersFromBranches = User::with(['branch'])
                    ->where($baseConditions)
                    ->whereNotNull('role')
                    ->whereIn('branch_id', $branchIds)
                    ->whereIn('role', $rolesToInclude)
                    ->get();
                
                // Also get users that might not have company_id set but belong to school branches
                // This handles legacy data or users created before company_id was added
                $usersWithoutCompany = User::with(['branch'])
                    ->where('is_active', true)
                    ->where('user_type', '!=', 'CompanyAdmin')
                    ->whereNotNull('role')
                    ->whereNull('company_id') // Users without company_id
                    ->whereIn('branch_id', $branchIds)
                    ->whereIn('role', $rolesToInclude)
                    ->get();
                
                $usersFromBranches = $usersFromBranches->merge($usersWithoutCompany);
            }

            // Get admin users from company (may not have branch_id or have branch_id in school)
            // Also get Staff users
            // Only get SuperAdmin, BranchAdmin, and Staff from company level
            $adminUsers = collect();
            $adminRolesToGet = array_intersect(['SuperAdmin', 'Admin', 'Staff'], $rolesToInclude);
            if (!empty($adminRolesToGet)) {
                // First try with company_id
                $adminUsers = User::with(['branch'])
                    ->where($baseConditions)
                    ->whereNotNull('role')
                    ->where('company_id', $school->company_id)
                    ->whereIn('role', $adminRolesToGet)
                    ->where(function($q) use ($branchIds) {
                        $q->whereNull('branch_id');
                        if ($branchIds->isNotEmpty()) {
                            $q->orWhereIn('branch_id', $branchIds);
                        }
                    })
                    ->get();
                
                // Also get admin users that might not have company_id set but belong to school branches
                // This handles legacy data
                if ($branchIds->isNotEmpty()) {
                    $adminUsersWithoutCompany = User::with(['branch'])
                        ->where('is_active', true)
                        ->where('user_type', '!=', 'CompanyAdmin')
                        ->whereNotNull('role')
                        ->whereNull('company_id') // Users without company_id
                        ->whereIn('branch_id', $branchIds)
                        ->whereIn('role', $adminRolesToGet)
                        ->get();
                    
                    $adminUsers = $adminUsers->merge($adminUsersWithoutCompany);
                }
            }

            // Merge and deduplicate users
            $users = $usersFromBranches->merge($adminUsers)->unique('id');

            // Sort: BranchAdmin first, then Admin, then Staff, then others
            $sortedUsers = $users->sortBy(function ($user) {
                if ($user->role === 'BranchAdmin') {
                    return 0;
                } elseif ($user->role === 'SuperAdmin' || $user->role === 'Admin') {
                    return 1;
                } elseif ($user->role === 'Staff') {
                    return 2;
                } else {
                    return 3;
                }
            })->values();

            // Format user data
            $usersData = $sortedUsers->map(function ($user) {
                return [
                    'id' => $user->id,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'full_name' => $user->full_name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'role' => $user->role,
                    'branch_id' => $user->branch_id,
                    'branch' => $user->branch ? [
                        'id' => $user->branch->id,
                        'name' => $user->branch->name,
                        'code' => $user->branch->code
                    ] : null,
                    'avatar' => $user->avatar,
                    'is_active' => $user->is_active,
                    'last_login' => $user->last_login,
                    'created_at' => $user->created_at,
                    'updated_at' => $user->updated_at
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $usersData->all(),
                'roles_found' => $adminRolesToGet
            ]);
        } catch (\Exception $e) {
            Log::error('Get school users error', [
                'error' => $e->getMessage(),
                'school_id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch school users'
            ], 500);
        }
    }
}

