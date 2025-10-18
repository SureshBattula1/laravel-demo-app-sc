<?php

namespace App\Http\Controllers;

use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class HolidayController extends Controller
{
    /**
     * Get all holidays with role-based filtering
     */
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Holiday::with(['branch', 'createdBy']);

            // Role-based filtering
            if ($user->role === 'BranchAdmin') {
                // Branch admin sees: their branch holidays + national/state holidays
                $query->where(function($q) use ($user) {
                    $q->where('branch_id', $user->branch_id)
                      ->orWhereNull('branch_id')
                      ->orWhereIn('type', ['National', 'State']);
                });
            }

            // Apply filters
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('from_date') && $request->has('to_date')) {
                $query->inDateRange($request->from_date, $request->to_date);
            }

            if ($request->has('month')) {
                $month = $request->month;
                $query->whereRaw('MONTH(start_date) = ?', [$month])
                      ->orWhereRaw('MONTH(end_date) = ?', [$month]);
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            $holidays = $query->orderBy('start_date', 'asc')->get();

            // Append duration to each holiday
            $holidays->each(function ($holiday) {
                $holiday->append('duration');
            });

            return response()->json([
                'success' => true,
                'data' => $holidays,
                'count' => $holidays->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get holidays error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch holidays',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new holiday (Admin only)
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Check role permissions
            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Only administrators can create holidays.'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'description' => 'nullable|string',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date',
                'type' => 'required|in:National,State,School,Optional,Restricted',
                'color' => 'nullable|string|max:20',
                'branch_id' => 'nullable|exists:branches,id',
                'is_recurring' => 'boolean',
                'academic_year' => 'nullable|string|max:20'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Branch admin can only create for their branch
            $branchId = $request->branch_id;
            if ($user->role === 'BranchAdmin') {
                $branchId = $user->branch_id;
            }

            $title = strip_tags($request->title);
            $startDate = $request->start_date;
            $academicYear = $request->academic_year ?: $this->getCurrentAcademicYear();
            
            $holiday = Holiday::create([
                'branch_id' => $branchId ?: 1, // Default to branch 1 if null (for old schema)
                'name' => $title, // Old schema column
                'title' => $title,
                'date' => $startDate, // Old schema column
                'description' => $request->description ?: '', // Empty string instead of null
                'start_date' => $startDate,
                'end_date' => $request->end_date,
                'type' => $request->type,
                'color' => $request->color ?: $this->getDefaultColor($request->type),
                'is_recurring' => $request->boolean('is_recurring', false),
                'academic_year' => $academicYear, // Required field, always has value
                'is_active' => $request->boolean('is_active', true),
                'created_by' => $user->id
            ]);

            DB::commit();

            Log::info('Holiday created', ['holiday_id' => $holiday->id, 'user_id' => $user->id]);

            return response()->json([
                'success' => true,
                'message' => 'Holiday created successfully',
                'data' => $holiday->load(['branch', 'createdBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create holiday error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create holiday',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single holiday
     */
    public function show($id)
    {
        try {
            $holiday = Holiday::with(['branch', 'createdBy'])->findOrFail($id);
            $holiday->append('duration');

            return response()->json([
                'success' => true,
                'data' => $holiday
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Holiday not found'
            ], 404);
        }
    }

    /**
     * Update holiday (Admin only, with ownership check)
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $holiday = Holiday::findOrFail($id);

            // Check permissions
            if ($user->role === 'BranchAdmin' && $holiday->branch_id !== $user->branch_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'You can only edit holidays for your branch'
                ], 403);
            }

            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'start_date' => 'sometimes|date',
                'end_date' => 'sometimes|date|after_or_equal:start_date',
                'type' => 'sometimes|in:National,State,School,Optional,Restricted',
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
                'title', 'description', 'start_date', 'end_date', 
                'type', 'color', 'is_recurring', 'is_active'
            ]);

            foreach (['title', 'description'] as $field) {
                if (isset($updateData[$field])) {
                    $updateData[$field] = strip_tags($updateData[$field]);
                }
            }
            
            // Also update old schema columns for compatibility
            if (isset($updateData['title'])) {
                $updateData['name'] = $updateData['title'];
            }
            if (isset($updateData['start_date'])) {
                $updateData['date'] = $updateData['start_date'];
            }

            $holiday->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Holiday updated successfully',
                'data' => $holiday->fresh(['branch', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to update holiday'
            ], 500);
        }
    }

    /**
     * Delete holiday (SuperAdmin only)
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            if ($user->role !== 'SuperAdmin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only Super Admin can delete holidays'
                ], 403);
            }

            $holiday = Holiday::findOrFail($id);
            $holiday->delete();

            return response()->json([
                'success' => true,
                'message' => 'Holiday deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete holiday'
            ], 500);
        }
    }

    /**
     * Get calendar data for a specific month
     */
    public function getCalendarData($year, $month)
    {
        try {
            $user = Auth::user();
            
            $startDate = date('Y-m-01', strtotime("$year-$month-01"));
            $endDate = date('Y-m-t', strtotime("$year-$month-01"));

            $query = Holiday::active()
                ->inDateRange($startDate, $endDate);

            // Role-based filtering
            if ($user->role === 'BranchAdmin') {
                $query->where(function($q) use ($user) {
                    $q->where('branch_id', $user->branch_id)
                      ->orWhereNull('branch_id')
                      ->orWhereIn('type', ['National', 'State']);
                });
            }

            $holidays = $query->get();

            return response()->json([
                'success' => true,
                'data' => $holidays
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load calendar data'
            ], 500);
        }
    }

    /**
     * Get upcoming holidays
     */
    public function getUpcoming(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 10);

            $query = Holiday::active()->upcoming();

            if ($user->role === 'BranchAdmin') {
                $query->where(function($q) use ($user) {
                    $q->where('branch_id', $user->branch_id)
                      ->orWhereNull('branch_id')
                      ->orWhereIn('type', ['National', 'State']);
                });
            }

            $holidays = $query->orderBy('start_date', 'asc')
                             ->limit($limit)
                             ->get();

            return response()->json([
                'success' => true,
                'data' => $holidays
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load upcoming holidays'
            ], 500);
        }
    }

    /**
     * Get default color for holiday type
     */
    private function getDefaultColor($type): string
    {
        return match($type) {
            'National' => '#FF5733',
            'State' => '#FFA500',
            'School' => '#3498DB',
            'Optional' => '#9B59B6',
            'Restricted' => '#95A5A6',
            default => '#3498DB'
        };
    }

    /**
     * Get current academic year
     */
    private function getCurrentAcademicYear(): string
    {
        $year = date('Y');
        $month = date('n');
        
        return $month < 4 ? ($year - 1) . '-' . $year : $year . '-' . ($year + 1);
    }
}

