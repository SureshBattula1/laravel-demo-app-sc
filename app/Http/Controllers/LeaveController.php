<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LeaveController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get leave records with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $type = $request->get('type', 'student'); // student or teacher
            
            if ($type === 'student') {
                $query = DB::table('student_leaves')
                    ->join('users', 'student_leaves.student_id', '=', 'users.id')
                    ->leftJoin('students', 'users.id', '=', 'students.user_id')
                    ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                    ->select(
                        'student_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'students.admission_number',
                        'students.grade',
                        'grades.label as grade_label',
                        'students.section'
                    );
            } else {
                $query = DB::table('teacher_leaves')
                    ->join('users', 'teacher_leaves.teacher_id', '=', 'users.id')
                    ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                    ->select(
                        'teacher_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'teachers.employee_id',
                        'teachers.designation'
                    );
            }

            // Apply branch filtering
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn($type . '_leaves.branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters
            if ($request->has('branch_id') && $accessibleBranchIds === 'all') {
                $query->where($type . '_leaves.branch_id', $request->branch_id);
            }

            if ($request->has('from_date')) {
                $query->whereDate($type . '_leaves.from_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate($type . '_leaves.to_date', '<=', $request->to_date);
            }

            if ($request->has('status')) {
                $query->where($type . '_leaves.status', $request->status);
            }

            if ($request->has('leave_type')) {
                $query->where($type . '_leaves.leave_type', $request->leave_type);
            }

            if ($type === 'student') {
                if ($request->has('grade')) {
                    $query->where('students.grade', $request->grade);
                }
                if ($request->has('section')) {
                    $query->where('students.section', $request->section);
                }
            }

            // Search filter
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search, $type) {
                    $q->where('users.first_name', 'like', $search . '%')
                      ->orWhere('users.last_name', 'like', $search . '%')
                      ->orWhere('users.email', 'like', $search . '%');
                    
                    if ($type === 'student') {
                        $q->orWhere('students.admission_number', 'like', $search . '%');
                    } else {
                        $q->orWhere('teachers.employee_id', 'like', $search . '%');
                    }
                });
            }

            // Define sortable columns
            $sortableColumns = $type === 'student' 
                ? [
                    'student_leaves.id',
                    'student_leaves.from_date',
                    'student_leaves.to_date',
                    'student_leaves.status',
                    'student_leaves.leave_type',
                    'student_leaves.created_at',
                    'users.first_name',
                    'users.last_name',
                    'students.admission_number',
                    'students.grade'
                ]
                : [
                    'teacher_leaves.id',
                    'teacher_leaves.from_date',
                    'teacher_leaves.to_date',
                    'teacher_leaves.status',
                    'teacher_leaves.leave_type',
                    'teacher_leaves.created_at',
                    'users.first_name',
                    'users.last_name',
                    'teachers.employee_id'
                ];

            // Apply pagination and sorting
            $leaves = $this->paginateAndSort(
                $query, 
                $request, 
                $sortableColumns, 
                $type . '_leaves.created_at', 
                'desc'
            );

            return response()->json([
                'success' => true,
                'message' => 'Leave records retrieved successfully',
                'data' => $leaves->items(),
                'meta' => [
                    'current_page' => $leaves->currentPage(),
                    'per_page' => $leaves->perPage(),
                    'total' => $leaves->total(),
                    'last_page' => $leaves->lastPage(),
                    'from' => $leaves->firstItem(),
                    'to' => $leaves->lastItem(),
                    'has_more_pages' => $leaves->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching leaves: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching leaves',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store leave record
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $type = $request->get('type', 'student');
            
            if ($type === 'student') {
                $validator = Validator::make($request->all(), [
                    'student_id' => 'required|exists:users,id',
                    'branch_id' => 'nullable|exists:branches,id',
                    'from_date' => 'required|date',
                    'to_date' => 'required|date|after_or_equal:from_date',
                    'leave_type' => 'required|in:Sick Leave,Casual Leave,Medical Leave,Family Emergency,Other',
                    'reason' => 'required|string',
                    'remarks' => 'nullable|string'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                DB::table('student_leaves')->insert([
                    'student_id' => $request->student_id,
                    'branch_id' => $request->branch_id,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'leave_type' => $request->leave_type,
                    'status' => 'Pending',
                    'reason' => $request->reason,
                    'remarks' => $request->remarks,
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'teacher_id' => 'required|exists:users,id',
                    'branch_id' => 'nullable|exists:branches,id',
                    'from_date' => 'required|date',
                    'to_date' => 'required|date|after_or_equal:from_date',
                    'leave_type' => 'required|in:Sick Leave,Casual Leave,Medical Leave,Maternity Leave,Paternity Leave,Compensatory Leave,Unpaid Leave,Other',
                    'reason' => 'required|string',
                    'remarks' => 'nullable|string',
                    'substitute_teacher_id' => 'nullable|exists:users,id'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                DB::table('teacher_leaves')->insert([
                    'teacher_id' => $request->teacher_id,
                    'branch_id' => $request->branch_id,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'leave_type' => $request->leave_type,
                    'status' => 'Pending',
                    'reason' => $request->reason,
                    'remarks' => $request->remarks,
                    'substitute_teacher_id' => $request->substitute_teacher_id,
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leave application submitted successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating leave: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating leave',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single leave record
     */
    public function show($id)
    {
        try {
            // Try student leave first
            $leave = DB::table('student_leaves')
                ->join('users', 'student_leaves.student_id', '=', 'users.id')
                ->leftJoin('students', 'users.id', '=', 'students.user_id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->where('student_leaves.id', $id)
                ->select(
                    'student_leaves.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'students.admission_number',
                    'students.grade',
                    'grades.label as grade_label',
                    'students.section',
                    DB::raw("'student' as leave_for")
                )
                ->first();
            
            // If not found, try teacher leave
            if (!$leave) {
                $leave = DB::table('teacher_leaves')
                    ->join('users', 'teacher_leaves.teacher_id', '=', 'users.id')
                    ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                    ->where('teacher_leaves.id', $id)
                    ->select(
                        'teacher_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'teachers.employee_id',
                        'teachers.designation',
                        DB::raw("'teacher' as leave_for")
                    )
                    ->first();
            }
            
            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave record not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $leave
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching leave record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching leave record',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update leave record
     */
    public function update(Request $request, $id)
    {
        try {
            $type = $request->get('type', 'student');
            $table = $type === 'student' ? 'student_leaves' : 'teacher_leaves';

            $validator = Validator::make($request->all(), [
                'status' => 'nullable|in:Pending,Approved,Rejected,Cancelled',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'leave_type' => 'nullable|string',
                'reason' => 'nullable|string',
                'remarks' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $leave = DB::table($table)->where('id', $id)->first();

            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave record not found'
                ], 404);
            }

            $updateData = ['updated_at' => now()];

            if ($request->has('status')) {
                $updateData['status'] = $request->status;
                if ($request->status === 'Approved') {
                    $updateData['approved_by'] = auth()->id() ?? null;
                    $updateData['approved_at'] = now();
                }
            }

            if ($request->has('from_date')) $updateData['from_date'] = $request->from_date;
            if ($request->has('to_date')) $updateData['to_date'] = $request->to_date;
            if ($request->has('leave_type')) $updateData['leave_type'] = $request->leave_type;
            if ($request->has('reason')) $updateData['reason'] = $request->reason;
            if ($request->has('remarks')) $updateData['remarks'] = $request->remarks;

            DB::table($table)->where('id', $id)->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Leave updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error updating leave: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating leave',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete leave record
     */
    public function destroy($id)
    {
        try {
            $request = request();
            $type = $request->get('type', 'student');
            $table = $type === 'student' ? 'student_leaves' : 'teacher_leaves';

            DB::table($table)->where('id', $id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Leave deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error deleting leave: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting leave',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get student leaves
     */
    public function getStudentLeaves($studentId)
    {
        try {
            $baseQuery = DB::table('student_leaves')
                ->where('student_id', $studentId);
            
            if (request()->has('from_date')) {
                $baseQuery->whereDate('from_date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $baseQuery->whereDate('to_date', '<=', request('to_date'));
            }
            
            $leaves = (clone $baseQuery)->orderBy('created_at', 'desc')->get();

            // Calculate summary
            $summaryQuery = (clone $baseQuery)
                ->select(
                    DB::raw('COUNT(*) as total_leaves'),
                    DB::raw('SUM(total_days) as total_days_taken'),
                    DB::raw('SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) as approved'),
                    DB::raw('SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending'),
                    DB::raw('SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) as rejected')
                )
                ->first();

            $summary = [
                'total_leaves' => (int) ($summaryQuery->total_leaves ?? 0),
                'total_days_taken' => (int) ($summaryQuery->total_days_taken ?? 0),
                'approved' => (int) ($summaryQuery->approved ?? 0),
                'pending' => (int) ($summaryQuery->pending ?? 0),
                'rejected' => (int) ($summaryQuery->rejected ?? 0)
            ];

            return response()->json([
                'success' => true,
                'data' => $leaves,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching student leaves: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching student leaves',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get teacher leaves
     */
    public function getTeacherLeaves($teacherId)
    {
        try {
            $baseQuery = DB::table('teacher_leaves')
                ->where('teacher_id', $teacherId);
            
            if (request()->has('from_date')) {
                $baseQuery->whereDate('from_date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $baseQuery->whereDate('to_date', '<=', request('to_date'));
            }
            
            $leaves = (clone $baseQuery)->orderBy('created_at', 'desc')->get();

            // Calculate summary
            $summaryQuery = (clone $baseQuery)
                ->select(
                    DB::raw('COUNT(*) as total_leaves'),
                    DB::raw('SUM(total_days) as total_days_taken'),
                    DB::raw('SUM(CASE WHEN status = "Approved" THEN 1 ELSE 0 END) as approved'),
                    DB::raw('SUM(CASE WHEN status = "Pending" THEN 1 ELSE 0 END) as pending'),
                    DB::raw('SUM(CASE WHEN status = "Rejected" THEN 1 ELSE 0 END) as rejected')
                )
                ->first();

            $summary = [
                'total_leaves' => (int) ($summaryQuery->total_leaves ?? 0),
                'total_days_taken' => (int) ($summaryQuery->total_days_taken ?? 0),
                'approved' => (int) ($summaryQuery->approved ?? 0),
                'pending' => (int) ($summaryQuery->pending ?? 0),
                'rejected' => (int) ($summaryQuery->rejected ?? 0)
            ];

            return response()->json([
                'success' => true,
                'data' => $leaves,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching teacher leaves: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching teacher leaves',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

