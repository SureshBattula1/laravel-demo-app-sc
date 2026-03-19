<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
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
            $studentHasAcademicYear = $this->tableHasAcademicYearId('student_leaves');
            $teacherHasAcademicYear = $this->tableHasAcademicYearId('teacher_leaves');
            
            if ($type === 'student') {
                $query = DB::table('student_leaves')
                    ->join('users', 'student_leaves.student_id', '=', 'users.id')
                    ->leftJoin('students', 'users.id', '=', 'students.user_id')
                    ->leftJoin('grades', function ($join) {
                        $join->on('students.grade', '=', 'grades.value')
                            ->whereColumn('grades.branch_id', '=', 'student_leaves.branch_id');
                    })
                    ->leftJoin('branches', 'student_leaves.branch_id', '=', 'branches.id')
                    ->select(
                        'student_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'users.phone as mobile_number',
                        'students.admission_number',
                        'students.grade',
                        'grades.label as grade_label',
                        'students.section',
                        'branches.name as branch_name'
                    );
                if ($studentHasAcademicYear) {
                    $query->leftJoin('academic_years as leave_academic_years', 'student_leaves.academic_year_id', '=', 'leave_academic_years.id')
                        ->addSelect('leave_academic_years.name as academic_year_name');
                } else {
                    $query->addSelect(DB::raw('NULL as academic_year_name'));
                }
            } else {
                $query = DB::table('teacher_leaves')
                    ->join('users', 'teacher_leaves.teacher_id', '=', 'users.id')
                    ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                    ->leftJoin('branches', 'teacher_leaves.branch_id', '=', 'branches.id')
                    ->select(
                        'teacher_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'users.phone as mobile_number',
                        'teachers.employee_id',
                        'teachers.designation',
                        'branches.name as branch_name'
                    );
                if ($teacherHasAcademicYear) {
                    $query->leftJoin('academic_years as leave_academic_years', 'teacher_leaves.academic_year_id', '=', 'leave_academic_years.id')
                        ->addSelect('leave_academic_years.name as academic_year_name');
                } else {
                    $query->addSelect(DB::raw('NULL as academic_year_name'));
                }
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

            // Advanced search: branch filter (apply when request has branch_id and user is allowed to filter by it)
            if ($request->filled('branch_id')) {
                $requestBranchId = (int) $request->branch_id;
                if ($accessibleBranchIds === 'all') {
                    $query->where($type . '_leaves.branch_id', $requestBranchId);
                } elseif (is_array($accessibleBranchIds) && in_array($requestBranchId, $accessibleBranchIds, false)) {
                    $query->where($type . '_leaves.branch_id', $requestBranchId);
                }
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
            if ($request->has('academic_year_id') && (($type === 'student' && $studentHasAcademicYear) || ($type === 'teacher' && $teacherHasAcademicYear))) {
                $query->where($type . '_leaves.academic_year_id', $request->academic_year_id);
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
                    'branches.name',
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
                    'branches.name',
                    'teachers.employee_id'
                ];
            if ($type === 'student' && $studentHasAcademicYear) {
                $sortableColumns[] = 'student_leaves.academic_year_id';
            }
            if ($type === 'teacher' && $teacherHasAcademicYear) {
                $sortableColumns[] = 'teacher_leaves.academic_year_id';
            }

            // Apply pagination and sorting
            $leaves = $this->paginateAndSort(
                $query, 
                $request, 
                $sortableColumns, 
                $type . '_leaves.created_at', 
                'desc'
            );

            $items = collect($leaves->items())->map(function ($row) {
                $row = (array) $row;
                $from = $row['from_date'] ?? null;
                $to = $row['to_date'] ?? null;
                if ($from && $to) {
                    $row['total_days'] = $this->calculateTotalDays($from, $to);
                }
                return $row;
            })->all();

            return response()->json([
                'success' => true,
                'message' => 'Leave records retrieved successfully',
                'data' => $items,
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
            $studentHasAcademicYear = $this->tableHasAcademicYearId('student_leaves');
            $teacherHasAcademicYear = $this->tableHasAcademicYearId('teacher_leaves');
            
            if ($type === 'student') {
                $rules = [
                    'student_id' => 'required|exists:users,id',
                    'branch_id' => 'nullable|exists:branches,id',
                    'from_date' => 'required|date',
                    'to_date' => 'required|date|after_or_equal:from_date',
                    'leave_type' => 'required|in:Sick Leave,Casual Leave,Medical Leave,Family Emergency,Other',
                    'reason' => 'required|string',
                    'remarks' => 'nullable|string'
                ];
                if ($studentHasAcademicYear) {
                    $rules['academic_year_id'] = 'nullable|exists:academic_years,id';
                }
                $validator = Validator::make($request->all(), $rules);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $totalDays = $this->calculateTotalDays($request->from_date, $request->to_date);

                $insertData = [
                    'student_id' => $request->student_id,
                    'branch_id' => $request->branch_id,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'total_days' => $totalDays,
                    'leave_type' => $request->leave_type,
                    'status' => 'Pending',
                    'reason' => $request->reason,
                    'remarks' => $request->remarks,
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                if ($studentHasAcademicYear) {
                    $insertData['academic_year_id'] = $request->academic_year_id;
                }
                if (Schema::hasColumn('student_leaves', 'school_id')) {
                    $schoolId = $request->branch_id
                        ? DB::table('branches')->where('id', $request->branch_id)->value('school_id')
                        : null;
                    $insertData['school_id'] = $schoolId;
                }
                DB::table('student_leaves')->insert($insertData);
            } else {
                $rules = [
                    'teacher_id' => 'required|exists:users,id',
                    'branch_id' => 'nullable|exists:branches,id',
                    'from_date' => 'required|date',
                    'to_date' => 'required|date|after_or_equal:from_date',
                    'leave_type' => 'required|in:Sick Leave,Casual Leave,Medical Leave,Maternity Leave,Paternity Leave,Compensatory Leave,Unpaid Leave,Other',
                    'reason' => 'required|string',
                    'remarks' => 'nullable|string',
                    'substitute_teacher_id' => 'nullable|exists:users,id'
                ];
                if ($teacherHasAcademicYear) {
                    $rules['academic_year_id'] = 'nullable|exists:academic_years,id';
                }
                $validator = Validator::make($request->all(), $rules);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $totalDays = $this->calculateTotalDays($request->from_date, $request->to_date);

                $insertData = [
                    'teacher_id' => $request->teacher_id,
                    'branch_id' => $request->branch_id,
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'total_days' => $totalDays,
                    'leave_type' => $request->leave_type,
                    'status' => 'Pending',
                    'reason' => $request->reason,
                    'remarks' => $request->remarks,
                    'substitute_teacher_id' => $request->substitute_teacher_id,
                    'created_by' => auth()->id() ?? null,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
                if ($teacherHasAcademicYear) {
                    $insertData['academic_year_id'] = $request->academic_year_id;
                }
                DB::table('teacher_leaves')->insert($insertData);
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
            $type = request()->get('type', 'student');
            $studentHasAcademicYear = $this->tableHasAcademicYearId('student_leaves');
            $teacherHasAcademicYear = $this->tableHasAcademicYearId('teacher_leaves');
            
            if ($type === 'student') {
                $leave = DB::table('student_leaves')
                    ->join('users', 'student_leaves.student_id', '=', 'users.id')
                    ->leftJoin('users as approver_users', 'student_leaves.approved_by', '=', 'approver_users.id')
                    ->leftJoin('students', 'users.id', '=', 'students.user_id')
                    ->leftJoin('grades', function ($join) {
                        $join->on('students.grade', '=', 'grades.value')
                            ->whereColumn('grades.branch_id', '=', 'student_leaves.branch_id');
                    })
                    ->leftJoin('branches', 'student_leaves.branch_id', '=', 'branches.id')
                    ->where('student_leaves.id', $id)
                    ->select(
                        'student_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        DB::raw('COALESCE(users.mobile, users.phone) as mobile_number'),
                        'students.admission_number',
                        'students.grade',
                        'grades.label as grade_label',
                        'students.section',
                        'branches.name as branch_name',
                        DB::raw("TRIM(CONCAT(COALESCE(approver_users.first_name, ''), ' ', COALESCE(approver_users.last_name, ''))) as approved_by_name"),
                        DB::raw("'student' as leave_for")
                    );
                if ($studentHasAcademicYear) {
                    $leave->leftJoin('academic_years as leave_academic_years', 'student_leaves.academic_year_id', '=', 'leave_academic_years.id')
                        ->addSelect('leave_academic_years.name as academic_year_name');
                } else {
                    $leave->addSelect(DB::raw('NULL as academic_year_name'));
                }
                $leave = $leave->first();
            } else {
                $leave = DB::table('teacher_leaves')
                    ->join('users', 'teacher_leaves.teacher_id', '=', 'users.id')
                    ->leftJoin('users as approver_users', 'teacher_leaves.approved_by', '=', 'approver_users.id')
                    ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                    ->leftJoin('branches', 'teacher_leaves.branch_id', '=', 'branches.id')
                    ->where('teacher_leaves.id', $id)
                    ->select(
                        'teacher_leaves.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        DB::raw('COALESCE(users.mobile, users.phone) as mobile_number'),
                        'teachers.employee_id',
                        'teachers.designation',
                        'branches.name as branch_name',
                        DB::raw("TRIM(CONCAT(COALESCE(approver_users.first_name, ''), ' ', COALESCE(approver_users.last_name, ''))) as approved_by_name"),
                        DB::raw("'teacher' as leave_for")
                    );
                if ($teacherHasAcademicYear) {
                    $leave->leftJoin('academic_years as leave_academic_years', 'teacher_leaves.academic_year_id', '=', 'leave_academic_years.id')
                        ->addSelect('leave_academic_years.name as academic_year_name');
                } else {
                    $leave->addSelect(DB::raw('NULL as academic_year_name'));
                }
                $leave = $leave->first();
            }
            
            if (!$leave) {
                return response()->json([
                    'success' => false,
                    'message' => 'Leave record not found'
                ], 404);
            }

            $leave = (array) $leave;
            $from = $leave['from_date'] ?? null;
            $to = $leave['to_date'] ?? null;
            if ($from && $to) {
                $leave['total_days'] = $this->calculateTotalDays($from, $to);
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
            $hasAcademicYear = $this->tableHasAcademicYearId($table);

            $rules = [
                'status' => 'nullable|in:Pending,Approved,Rejected,Cancelled',
                'from_date' => 'nullable|date',
                'to_date' => 'nullable|date|after_or_equal:from_date',
                'leave_type' => 'nullable|string',
                'reason' => 'nullable|string',
                'remarks' => 'nullable|string'
            ];
            if ($hasAcademicYear) {
                $rules['academic_year_id'] = 'nullable|exists:academic_years,id';
            }
            $validator = Validator::make($request->all(), $rules);

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
            if ($hasAcademicYear && $request->has('academic_year_id')) $updateData['academic_year_id'] = $request->academic_year_id;
            if ($request->has('from_date') || $request->has('to_date')) {
                $from = $request->has('from_date') ? $request->from_date : $leave->from_date;
                $to = $request->has('to_date') ? $request->to_date : $leave->to_date;
                $updateData['total_days'] = $this->calculateTotalDays($from, $to);
            }
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
            $hasAcademicYear = $this->tableHasAcademicYearId('student_leaves');
            // Do NOT filter by academic_year - leaves may fall outside the student's
            // academic year range (e.g. leave in March when year is July-June), causing
            // them to not show. Use student_id + optional from_date/to_date only.
            $baseQuery = DB::table('student_leaves')
                ->where('student_id', $studentId);
            
            if (request()->has('from_date')) {
                $baseQuery->whereDate('from_date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $baseQuery->whereDate('to_date', '<=', request('to_date'));
            }
            
            $leavesQuery = (clone $baseQuery)->orderBy('student_leaves.created_at', 'desc');
            if ($hasAcademicYear) {
                $leavesQuery->leftJoin('academic_years as leave_academic_years', 'student_leaves.academic_year_id', '=', 'leave_academic_years.id')
                    ->select('student_leaves.*', 'leave_academic_years.name as academic_year_name');
            } else {
                $leavesQuery->select('student_leaves.*', DB::raw('NULL as academic_year_name'));
            }
            $leaves = $leavesQuery->get();

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
            $hasAcademicYear = $this->tableHasAcademicYearId('teacher_leaves');
            $baseQuery = DB::table('teacher_leaves')
                ->where('teacher_id', $teacherId);
            
            if (request()->has('from_date')) {
                $baseQuery->whereDate('from_date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $baseQuery->whereDate('to_date', '<=', request('to_date'));
            }
            
            $leavesQuery = (clone $baseQuery)->orderBy('teacher_leaves.created_at', 'desc');
            if ($hasAcademicYear) {
                $leavesQuery->leftJoin('academic_years as leave_academic_years', 'teacher_leaves.academic_year_id', '=', 'leave_academic_years.id')
                    ->select('teacher_leaves.*', 'leave_academic_years.name as academic_year_name');
            } else {
                $leavesQuery->select('teacher_leaves.*', DB::raw('NULL as academic_year_name'));
            }
            $leaves = $leavesQuery->get();

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

    /**
     * Get academic year date range
     * Academic year format: "2024-2025" means from July 2024 to June 2025
     */
    protected function getAcademicYearDateRange($academicYear)
    {
        if (!$academicYear || !preg_match('/^(\d{4})-(\d{4})$/', $academicYear, $matches)) {
            return null;
        }
        
        $startYear = (int)$matches[1];
        $endYear = (int)$matches[2];
        
        // Academic year typically runs from July to June
        // Start: July 1 of start year
        // End: June 30 of end year
        $startDate = Carbon::create($startYear, 7, 1)->startOfDay();
        $endDate = Carbon::create($endYear, 6, 30)->endOfDay();
        
        return [
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d')
        ];
    }

    /**
     * Calculate total days between from_date and to_date (inclusive).
     */
    private function calculateTotalDays(?string $fromDate, ?string $toDate): int
    {
        if (!$fromDate || !$toDate) {
            return 1;
        }
        $from = Carbon::parse($fromDate)->startOfDay();
        $to = Carbon::parse($toDate)->startOfDay();
        return max(1, $from->diffInDays($to) + 1);
    }

    /**
     * Backward compatibility for environments not migrated yet.
     */
    private function tableHasAcademicYearId(string $table): bool
    {
        try {
            return Schema::hasTable($table) && Schema::hasColumn($table, 'academic_year_id');
        } catch (\Throwable $e) {
            return false;
        }
    }
}

