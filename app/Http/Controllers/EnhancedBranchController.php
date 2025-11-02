<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\BranchSetting;
use App\Models\BranchTransfer;
use App\Models\BranchAnalytic;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EnhancedBranchController extends Controller
{
    /**
     * Get branches with hierarchical structure
     */
    public function index(Request $request)
    {
        try {
            $query = Branch::with(['parentBranch', 'childBranches']);
            $user = Auth::user();

            // Filter based on user role
            if ($user->role === 'BranchAdmin') {
                // Branch admin can see their branch and children
                $branch = Branch::find($user->branch_id);
                $branchIds = $branch ? $branch->getDescendantIds() : [$user->branch_id];
                $query->whereIn('id', $branchIds);
            }

            // Filters
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('branch_type')) {
                $query->where('branch_type', $request->branch_type);
            }

            if ($request->has('region')) {
                $query->where('region', $request->region);
            }

            if ($request->has('city')) {
                $query->where('city', $request->city);
            }

            if ($request->has('parent_id')) {
                if ($request->parent_id === 'null' || $request->parent_id === '0') {
                    $query->whereNull('parent_branch_id');
                } else {
                    $query->where('parent_branch_id', $request->parent_id);
                }
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('city', 'like', '%' . $search . '%')
                      ->orWhere('region', 'like', '%' . $search . '%');
                });
            }

            // Get hierarchical structure if requested
            if ($request->boolean('hierarchical')) {
                $branches = $query->whereNull('parent_branch_id')
                    ->with('allDescendants')
                    ->orderBy('name')
                    ->get();
            } else {
                $branches = $query->orderBy('name')->get();
            }

            // Add computed fields
            $branches->each(function($branch) {
                $branch->capacity_utilization = $branch->getCapacityUtilization();
                $branch->has_children = $branch->hasChildren();
            });

            return response()->json([
                'success' => true,
                'data' => $branches,
                'count' => $branches->count()
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

    /**
     * Store a new branch with enhanced validation
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:branches',
                'branch_type' => 'required|in:HeadOffice,RegionalOffice,School,Campus,SubBranch',
                'parent_branch_id' => 'nullable|exists:branches,id',
                'address' => 'required|string|max:500',
                'city' => 'required|string|max:100',
                'state' => 'required|string|max:100',
                'country' => 'required|string|max:100',
                'region' => 'nullable|string|max:100',
                'pincode' => 'required|string|max:10',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'timezone' => 'nullable|string|max:50',
                'phone' => 'required|string|max:20|unique:branches',
                'email' => 'required|email|max:255|unique:branches',
                'website' => 'nullable|url|max:255',
                'fax' => 'nullable|string|max:20',
                'emergency_contact' => 'nullable|string|max:20',
                'principal_name' => 'nullable|string|max:255',
                'principal_contact' => 'nullable|string|max:20',
                'principal_email' => 'nullable|email|max:255',
                'established_date' => 'nullable|date|before_or_equal:today',
                'opening_date' => 'nullable|date',
                'affiliation_number' => 'nullable|string|max:100',
                'board' => 'nullable|string|max:100',
                'accreditations' => 'nullable|array',
                'total_capacity' => 'nullable|integer|min:0',
                'facilities' => 'nullable|array',
                'grades_offered' => 'nullable|array',
                'academic_year_start' => 'nullable|string|max:5',
                'academic_year_end' => 'nullable|string|max:5',
                'current_academic_year' => 'nullable|string|max:20',
                'tax_id' => 'nullable|string|max:50',
                'bank_name' => 'nullable|string|max:100',
                'bank_account_number' => 'nullable|string|max:50',
                'ifsc_code' => 'nullable|string|max:20',
                'is_main_branch' => 'boolean',
                'is_residential' => 'boolean',
                'has_hostel' => 'boolean',
                'has_transport' => 'boolean',
                'has_library' => 'boolean',
                'has_lab' => 'boolean',
                'has_canteen' => 'boolean',
                'has_sports' => 'boolean',
                'status' => 'nullable|in:Active,Inactive,UnderConstruction,Maintenance,Closed',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $branchData = $request->except(['logo']);
            $branchData['code'] = strtoupper($branchData['code']);
            $branchData['status'] = $branchData['status'] ?? 'Active';
            $branchData['current_enrollment'] = 0;

            // Handle logo upload if present
            if ($request->hasFile('logo')) {
                $logoPath = $request->file('logo')->store('branch-logos', 'public');
                $branchData['logo'] = $logoPath;
            }

            $branch = Branch::create($branchData);

            DB::commit();

            Log::info('Branch created', [
                'branch_id' => $branch->id,
                'user_id' => Auth::id()
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

    /**
     * Get branch hierarchy tree
     */
    public function getHierarchy($id = null)
    {
        try {
            if ($id) {
                $branch = Branch::with('allDescendants')->findOrFail($id);
                $tree = $this->buildBranchTree(collect([$branch]));
            } else {
                $branches = Branch::whereNull('parent_branch_id')
                    ->with('allDescendants')
                    ->active()
                    ->get();
                $tree = $this->buildBranchTree($branches);
            }

            return response()->json([
                'success' => true,
                'data' => $tree
            ]);

        } catch (\Exception $e) {
            Log::error('Get branch hierarchy error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch hierarchy'
            ], 500);
        }
    }

    /**
     * Get comprehensive branch statistics
     */
    public function stats($id)
    {
        try {
            $branch = Branch::with(['childBranches', 'parentBranch'])->findOrFail($id);
            
            // Get descendant branch IDs for comprehensive stats
            $branchIds = $branch->getDescendantIds();

            $stats = [
                'branch_info' => [
                    'id' => $branch->id,
                    'name' => $branch->name,
                    'code' => $branch->code,
                    'type' => $branch->branch_type,
                    'status' => $branch->status,
                    'has_parent' => $branch->hasParent(),
                    'parent_branch' => $branch->parentBranch ? $branch->parentBranch->name : null,
                    'child_branches_count' => $branch->childBranches->count()
                ],
                'capacity' => [
                    'total_capacity' => $branch->total_capacity,
                    'current_enrollment' => $branch->current_enrollment,
                    'utilization_percentage' => $branch->getCapacityUtilization(),
                    'available_seats' => max(0, $branch->total_capacity - $branch->current_enrollment)
                ],
                'users' => [
                    'total_students' => User::whereIn('branch_id', $branchIds)->where('role', 'Student')->count(),
                    'active_students' => User::whereIn('branch_id', $branchIds)->where('role', 'Student')->where('is_active', true)->count(),
                    'total_teachers' => User::whereIn('branch_id', $branchIds)->where('role', 'Teacher')->count(),
                    'active_teachers' => User::whereIn('branch_id', $branchIds)->where('role', 'Teacher')->where('is_active', true)->count(),
                    'total_staff' => User::whereIn('branch_id', $branchIds)->where('role', 'Staff')->count(),
                    'total_parents' => User::whereIn('branch_id', $branchIds)->where('role', 'Parent')->count()
                ],
                'facilities' => [
                    'has_hostel' => $branch->has_hostel,
                    'has_transport' => $branch->has_transport,
                    'has_library' => $branch->has_library,
                    'has_lab' => $branch->has_lab,
                    'has_canteen' => $branch->has_canteen,
                    'has_sports' => $branch->has_sports,
                    'is_residential' => $branch->is_residential,
                    'facilities_list' => $branch->facilities
                ],
                'academic' => [
                    'board' => $branch->board,
                    'grades_offered' => $branch->grades_offered,
                    'current_academic_year' => $branch->current_academic_year,
                    'departments' => $branch->departments()->count()
                ],
                'recent_activity' => [
                    'recent_admissions' => User::where('branch_id', $branch->id)
                        ->where('role', 'Student')
                        ->where('created_at', '>=', Carbon::now()->subDays(30))
                        ->count(),
                    'pending_transfers_in' => BranchTransfer::where('to_branch_id', $branch->id)
                        ->where('status', 'Pending')
                        ->count(),
                    'pending_transfers_out' => BranchTransfer::where('from_branch_id', $branch->id)
                        ->where('status', 'Pending')
                        ->count()
                ]
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

    /**
     * Get comparative analytics across branches
     */
    public function getComparativeAnalytics(Request $request)
    {
        try {
            $branchIds = $request->input('branch_ids', []);
            $metricType = $request->input('metric_type', 'enrollment');
            $days = $request->input('days', 30);

            $startDate = Carbon::now()->subDays($days);

            if (empty($branchIds)) {
                $branches = Branch::active()->pluck('id')->toArray();
            } else {
                $branches = $branchIds;
            }

            $analytics = BranchAnalytic::whereIn('branch_id', $branches)
                ->where('metric_type', $metricType)
                ->where('analytics_date', '>=', $startDate)
                ->with('branch:id,name,code')
                ->orderBy('analytics_date')
                ->get()
                ->groupBy('branch_id');

            $comparison = [];
            foreach ($analytics as $branchId => $data) {
                $branch = Branch::find($branchId);
                $comparison[] = [
                    'branch_id' => $branchId,
                    'branch_name' => $branch->name,
                    'branch_code' => $branch->code,
                    'data' => $data->map(function($item) {
                        return [
                            'date' => $item->analytics_date->format('Y-m-d'),
                            'value' => $item->metric_value,
                            'breakdown' => $item->breakdown
                        ];
                    })
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'metric_type' => $metricType,
                    'period' => "{$days} days",
                    'branches' => $comparison
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get comparative analytics error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch comparative analytics'
            ], 500);
        }
    }

    /**
     * Get branch settings
     */
    public function getSettings($id)
    {
        try {
            $branch = Branch::findOrFail($id);
            $settings = $branch->branchSettings()
                ->orderBy('category')
                ->orderBy('setting_key')
                ->get()
                ->groupBy('category');

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);

        } catch (\Exception $e) {
            Log::error('Get branch settings error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch settings'
            ], 500);
        }
    }

    /**
     * Update branch settings
     */
    public function updateSettings(Request $request, $id)
    {
        try {
            $branch = Branch::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'settings' => 'required|array',
                'settings.*.key' => 'required|string',
                'settings.*.value' => 'required',
                'settings.*.type' => 'required|in:string,number,boolean,json',
                'settings.*.category' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            foreach ($request->settings as $setting) {
                BranchSetting::updateOrCreate(
                    [
                        'branch_id' => $id,
                        'setting_key' => $setting['key']
                    ],
                    [
                        'setting_value' => is_array($setting['value']) ? json_encode($setting['value']) : $setting['value'],
                        'setting_type' => $setting['type'],
                        'category' => $setting['category'] ?? 'general'
                    ]
                );
            }

            DB::commit();

            Log::info('Branch settings updated', [
                'branch_id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Branch settings updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update branch settings error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch settings'
            ], 500);
        }
    }

    /**
     * Get branches by region/city for filtering
     */
    public function getBranchLocations()
    {
        try {
            $locations = Branch::select('region', 'city', 'state', 'country')
                ->distinct()
                ->active()
                ->orderBy('country')
                ->orderBy('state')
                ->orderBy('city')
                ->get()
                ->groupBy('country');

            $summary = [
                'total_countries' => $locations->count(),
                'total_cities' => Branch::distinct()->count('city'),
                'total_regions' => Branch::distinct()->whereNotNull('region')->count('region'),
                'locations' => $locations
            ];

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Get branch locations error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch branch locations'
            ], 500);
        }
    }

    /**
     * Update branch capacity and enrollment
     */
    public function updateCapacity(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'total_capacity' => 'required|integer|min:0',
                'current_enrollment' => 'sometimes|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $branch = Branch::findOrFail($id);

            DB::beginTransaction();

            $branch->total_capacity = $request->total_capacity;
            if ($request->has('current_enrollment')) {
                $branch->current_enrollment = $request->current_enrollment;
            }
            $branch->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Branch capacity updated successfully',
                'data' => [
                    'total_capacity' => $branch->total_capacity,
                    'current_enrollment' => $branch->current_enrollment,
                    'utilization' => $branch->getCapacityUtilization()
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update branch capacity error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update branch capacity'
            ], 500);
        }
    }

    // Private helper methods
    private function buildBranchTree($branches)
    {
        return $branches->map(function($branch) {
            return [
                'id' => $branch->id,
                'name' => $branch->name,
                'code' => $branch->code,
                'type' => $branch->branch_type,
                'status' => $branch->status,
                'city' => $branch->city,
                'total_capacity' => $branch->total_capacity,
                'current_enrollment' => $branch->current_enrollment,
                'capacity_utilization' => $branch->getCapacityUtilization(),
                'is_active' => $branch->is_active,
                'children' => $branch->childBranches ? $this->buildBranchTree($branch->childBranches) : []
            ];
        });
    }

    // Re-use existing methods from BranchController
    public function show($id)
    {
        // Implementation from original controller
    }

    public function update(Request $request, $id)
    {
        // Implementation from original controller with added fields
    }

    public function destroy($id)
    {
        // Implementation from original controller
    }

    public function toggleStatus($id)
    {
        // Implementation from original controller
    }
}

