<?php

namespace App\Http\Controllers\CompanyPortal;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Company;
use App\Models\Branch;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

            // Show schools across all companies in the portal, with optional filter
            $query = School::with(['company', 'mainBranch'])
                ->withCount(['branches', 'activeBranches']);

            // Optional filter by company if provided
            if ($request->has('company_id') && $request->company_id) {
                $query->where('company_id', $request->company_id);
            }

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
            // Allow loading any school by ID (no company_id restriction)
            $school = School::with(['company', 'mainBranch', 'branches'])
                ->findOrFail($id);

            // Get admin user for the main branch if it exists
            $adminUser = null;
            if ($school->main_branch_id) {
                $adminUser = User::where('branch_id', $school->main_branch_id)
                    ->whereIn('role', ['BranchAdmin', 'SuperAdmin', 'Admin'])
                    ->where('is_active', true)
                    ->first();
            }

            $schoolData = $school->toArray();
            if ($adminUser) {
                $schoolData['admin_user'] = [
                    'id' => $adminUser->id,
                    'first_name' => $adminUser->first_name,
                    'last_name' => $adminUser->last_name,
                    'email' => $adminUser->email,
                    'phone' => $adminUser->phone,
                    'role' => $adminUser->role
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $schoolData
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
                // Company selection (for multi-company management)
                'company_id' => 'required|exists:companies,id',

                // School fields
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:schools,code',
                'status' => 'sometimes|in:Active,Inactive,Suspended,UnderConstruction',
                
                // Branch fields (required when creating school)
                'branch' => 'required|array',
                'branch.name' => 'required|string|max:255',
                'branch.code' => 'required|string|max:50|unique:branches,code',
                'branch.address' => 'required|string|max:500',
                'branch.city' => 'required|string|max:100',
                'branch.state' => 'required|string|max:100',
                'branch.country' => 'required|string|max:100',
                'branch.pincode' => 'required|string|max:10',
                'branch.phone' => 'required|string|max:20|unique:branches,phone',
                'branch.email' => 'required|email|max:255|unique:branches,email',
                'branch.website' => 'nullable|url|max:255',
                
                // Admin user fields (required when creating school)
                'admin_user' => 'required|array',
                'admin_user.first_name' => 'required|string|max:255',
                'admin_user.last_name' => 'required|string|max:255',
                'admin_user.email' => 'required|email|max:255|unique:users,email',
                'admin_user.password' => 'required|string|min:8',
                'admin_user.phone' => 'nullable|string|max:20',
                'admin_user.role' => 'required|in:BranchAdmin,SuperAdmin'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Determine company for the new school (selected in UI)
            $companyId = $request->company_id;

            // Create school
            $school = School::create([
                'company_id' => $companyId,
                'name' => $request->name,
                'code' => $request->code,
                'status' => $request->status ?? 'Active',
                'settings' => $request->settings ?? []
            ]);

            // Create main branch for the school
            $branchData = $request->branch;
            $branch = Branch::create([
                'name' => $branchData['name'],
                'code' => strtoupper($branchData['code']),
                'school_id' => $school->id,
                'branch_type' => 'School',
                'address' => $branchData['address'],
                'city' => $branchData['city'],
                'state' => $branchData['state'],
                'country' => $branchData['country'],
                'pincode' => $branchData['pincode'],
                'phone' => $branchData['phone'],
                'email' => $branchData['email'],
                'website' => $branchData['website'] ?? null,
                'is_main_branch' => true,
                'status' => 'Active',
                'is_active' => true,
                'current_enrollment' => 0
            ]);

            // Update school with main branch ID
            $school->update(['main_branch_id' => $branch->id]);

            // Create admin user for the branch (always SuperAdmin when creating school from company portal)
            $adminData = $request->admin_user;
            $adminRole = 'SuperAdmin';
            $adminUser = User::create([
                'first_name' => $adminData['first_name'],
                'last_name' => $adminData['last_name'],
                'email' => $adminData['email'],
                'password' => Hash::make($adminData['password']),
                'phone' => $adminData['phone'] ?? null,
                'role' => $adminRole,
                'user_type' => 'SchoolUser',
                'branch_id' => $branch->id,
                'company_id' => $companyId,
                'is_active' => true
            ]);

            // Assign SuperAdmin role via user_roles table (school admin from company portal is always SuperAdmin)
            $roleSlug = 'super-admin';
            $role = Role::where('slug', $roleSlug)->first();
            
            if ($role) {
                $adminUser->roles()->attach($role->id, [
                    'is_primary' => true,
                    'branch_id' => $branch->id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                // Log warning if role not found, but don't fail the transaction
                Log::warning('Role not found when creating admin user', [
                    'role_slug' => $roleSlug,
                    'user_id' => $adminUser->id,
                    'user_role' => $adminRole
                ]);
            }

            DB::commit();

            Log::info('School created', [
                'school_id' => $school->id,
                'company_id' => $user->company_id,
                'created_by' => $user->id,
                'branch_id' => $branch->id,
                'admin_user_id' => $adminUser->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'School created successfully with main branch and admin user',
                'data' => $school->load(['company', 'mainBranch']),
                'branch' => $branch,
                'admin_user' => [
                    'id' => $adminUser->id,
                    'name' => $adminUser->full_name,
                    'email' => $adminUser->email,
                    'role' => $adminUser->role
                ]
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

            // Allow updating any school by ID (no company_id restriction)
            $school = School::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'code' => 'sometimes|required|string|max:50|unique:schools,code,' . $id,
                'status' => 'sometimes|in:Active,Inactive,Suspended,UnderConstruction',
                'settings' => 'sometimes|array',
                
                // Branch fields (optional for updates)
                'branch' => 'sometimes|array',
                'branch.name' => 'sometimes|string|max:255',
                'branch.code' => 'sometimes|string|max:50',
                'branch.address' => 'sometimes|string|max:500',
                'branch.city' => 'sometimes|string|max:100',
                'branch.state' => 'sometimes|string|max:100',
                'branch.country' => 'sometimes|string|max:100',
                'branch.pincode' => 'sometimes|string|max:10',
                'branch.phone' => 'sometimes|string|max:20',
                'branch.email' => 'sometimes|email|max:255',
                'branch.website' => 'nullable|url|max:255',

                // Admin user fields (optional for updates)
                'admin_user' => 'sometimes|array',
                'admin_user.first_name' => 'sometimes|string|max:255',
                'admin_user.last_name' => 'sometimes|string|max:255',
                'admin_user.email' => 'sometimes|email|max:255',
                'admin_user.password' => 'sometimes|nullable|string|min:8',
                'admin_user.phone' => 'nullable|string|max:20',
                'admin_user.role' => 'sometimes|in:BranchAdmin,SuperAdmin,Admin'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update school basic info
            $school->update($request->only(['name', 'code', 'status', 'settings']));

            // Update main branch if branch data is provided
            if ($request->has('branch') && $school->main_branch_id) {
                $branchData = $request->branch;
                $mainBranch = Branch::where('id', $school->main_branch_id)
                    ->where('school_id', $school->id)
                    ->first();
                
                if ($mainBranch) {
                    $mainBranch->update([
                        'name' => $branchData['name'] ?? $mainBranch->name,
                        'code' => $branchData['code'] ?? $mainBranch->code,
                        'address' => $branchData['address'] ?? $mainBranch->address,
                        'city' => $branchData['city'] ?? $mainBranch->city,
                        'state' => $branchData['state'] ?? $mainBranch->state,
                        'country' => $branchData['country'] ?? $mainBranch->country,
                        'pincode' => $branchData['pincode'] ?? $mainBranch->pincode,
                        'phone' => $branchData['phone'] ?? $mainBranch->phone,
                        'email' => $branchData['email'] ?? $mainBranch->email,
                        'website' => $branchData['website'] ?? $mainBranch->website
                    ]);
                }
            }

            // Update admin user if admin_user data is provided
            if ($request->has('admin_user') && $school->main_branch_id) {
                $adminData = $request->admin_user;
                $adminUser = User::where('branch_id', $school->main_branch_id)
                    ->whereIn('role', ['BranchAdmin', 'SuperAdmin', 'Admin'])
                    ->where('is_active', true)
                    ->first();
                
                if ($adminUser) {
                    // Store original role before update
                    $oldRole = $adminUser->role;
                    
                    $updateData = [
                        'first_name' => $adminData['first_name'] ?? $adminUser->first_name,
                        'last_name' => $adminData['last_name'] ?? $adminUser->last_name,
                        'email' => $adminData['email'] ?? $adminUser->email,
                        'phone' => $adminData['phone'] ?? $adminUser->phone,
                        'role' => $adminData['role'] ?? $adminUser->role
                    ];
                    
                    // Only update password if provided
                    if (!empty($adminData['password'])) {
                        $updateData['password'] = Hash::make($adminData['password']);
                    }
                    
                    $adminUser->update($updateData);
                    
                    // Update role assignment if role changed
                    if (isset($adminData['role']) && $adminData['role'] !== $oldRole) {
                        // Remove old role
                        $oldRoleSlug = ($oldRole === 'SuperAdmin' || $oldRole === 'Admin') 
                            ? 'super-admin' 
                            : 'branch-admin';
                        $oldRoleModel = Role::where('slug', $oldRoleSlug)->first();
                        if ($oldRoleModel) {
                            $adminUser->roles()->detach($oldRoleModel->id);
                        }
                        
                        // Attach new role
                        $newRoleSlug = ($adminData['role'] === 'SuperAdmin' || $adminData['role'] === 'Admin')
                            ? 'super-admin'
                            : 'branch-admin';
                        $newRoleModel = Role::where('slug', $newRoleSlug)->first();
                        if ($newRoleModel) {
                            $adminUser->roles()->attach($newRoleModel->id, [
                                'is_primary' => true,
                                'branch_id' => $school->main_branch_id,
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);
                        }
                    }
                }
            }

            DB::commit();

            Log::info('School updated', ['school_id' => $school->id, 'updated_by' => $user->id]);

            // Reload school with relationships
            $school->refresh();
            $adminUser = null;
            if ($school->main_branch_id) {
                $adminUser = User::where('branch_id', $school->main_branch_id)
                    ->whereIn('role', ['BranchAdmin', 'SuperAdmin', 'Admin'])
                    ->where('is_active', true)
                    ->first();
            }

            $schoolData = $school->load(['company', 'mainBranch'])->toArray();
            if ($adminUser) {
                $schoolData['admin_user'] = [
                    'id' => $adminUser->id,
                    'first_name' => $adminUser->first_name,
                    'last_name' => $adminUser->last_name,
                    'email' => $adminUser->email,
                    'phone' => $adminUser->phone,
                    'role' => $adminUser->role
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'School updated successfully',
                'data' => $schoolData
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('School update error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
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

            // Allow loading users for any school by ID (consistent with show() and portal listing all schools)
            $school = School::findOrFail($id);

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
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'School not found or you do not have access to it.'
            ], 404);
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

