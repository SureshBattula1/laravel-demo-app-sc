<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Branch;
use App\Models\Student;
use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use App\Exports\AttendanceExport;
use App\Services\PdfExportService;
use App\Services\CsvExportService;
use App\Services\ExportService;
use Maatwebsite\Excel\Facades\Excel;

class AttendanceController extends Controller
{
    use PaginatesAndSorts;
    use \App\Http\Traits\FiltersActiveRecords;

    /**
     * Get attendance records with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $type = $request->get('type', 'student'); // student or teacher
            $academicYearId = $request->attributes->get('academic_year_id');
            
            if ($type === 'student') {
                $query = DB::table('student_attendance')
                    ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                    ->join('users', 'students.user_id', '=', 'users.id')
                    ->leftJoin('branches', 'student_attendance.branch_id', '=', 'branches.id')
                    ->leftJoin('grades', function ($join) {
                        $join->on('grades.value', '=', 'students.grade');
                        // Prevent duplicate rows when grades are branch-specific
                        if (\Illuminate\Support\Facades\Schema::hasColumn('grades', 'branch_id')) {
                            $join->on('grades.branch_id', '=', 'students.branch_id');
                        }
                    })
                    ->select(
                        'student_attendance.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'students.admission_number',
                        'students.grade',
                        'grades.label as grade_label',
                        'students.section',
                        'branches.name as branch_name'
                    );
            } else {
                $query = DB::table('teacher_attendance')
                    ->join('users', 'teacher_attendance.teacher_id', '=', 'users.id')
                    ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                    ->select(
                        'teacher_attendance.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'teachers.employee_id'
                    );
            }

            // 🔥 APPLY SCHOOL FILTERING - School-level isolation
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where($type . '_attendance.school_id', $schoolId);
            }

            // 🔥 APPLY BRANCH FILTERING
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn($type . '_attendance.branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filters: always respect requested branch_id if it is within accessible scope
            if ($request->has('branch_id') && $request->filled('branch_id')) {
                $requestedBranchId = (int) $request->input('branch_id');

                if ($accessibleBranchIds === 'all') {
                    $query->where($type . '_attendance.branch_id', $requestedBranchId);
                } elseif (is_array($accessibleBranchIds) && in_array($requestedBranchId, $accessibleBranchIds, true)) {
                    $query->where($type . '_attendance.branch_id', $requestedBranchId);
                } else {
                    // Requested branch is outside accessible scope -> return no rows
                    $query->whereRaw('1 = 0');
                }
            }

            // Academic year scoping (Option A)
            if ($academicYearId) {
                $query->where($type . '_attendance.academic_year_id', (int) $academicYearId);
            }

            if ($request->has('date')) {
                $query->whereDate($type . '_attendance.date', $request->date);
            }

            if ($request->has('from_date')) {
                $query->whereDate($type . '_attendance.date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate($type . '_attendance.date', '<=', $request->to_date);
            }

            if ($request->has('status')) {
                $query->where($type . '_attendance.status', $request->status);
            }

            if ($type === 'student') {
                if ($request->has('grade')) {
                    $query->where('students.grade', $request->grade);
                }
                if ($request->has('section')) {
                    $query->where('students.section', $request->section);
                }
            }

            // OPTIMIZED Search filter - removed leading wildcards for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search, $type) {
                    $q->where('users.first_name', 'like', $search . '%')
                      ->orWhere('users.last_name', 'like', $search . '%')
                      ->orWhere('users.email', 'like', $search . '%');
                    
                    // Add type-specific search fields
                    if ($type === 'student') {
                        $q->orWhere('students.admission_number', 'like', $search . '%');
                    }
                });
            }

            // Define sortable columns
            $sortableColumns = $type === 'student' 
                ? [
                    'student_attendance.id',
                    'student_attendance.date',
                    'student_attendance.status',
                    'student_attendance.created_at',
                    'users.first_name',
                    'users.last_name',
                    'students.admission_number',
                    'students.grade',
                    'students.section'
                ]
                : [
                    'teacher_attendance.id',
                    'teacher_attendance.date',
                    'teacher_attendance.status',
                    'teacher_attendance.created_at',
                    'users.first_name',
                    'users.last_name'
                ];

            // Apply pagination and sorting (default: 25 per page, sorted by date desc)
            $attendance = $this->paginateAndSort(
                $query, 
                $request, 
                $sortableColumns, 
                $type . '_attendance.date', 
                'desc'
            );

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Attendance records retrieved successfully',
                'data' => $attendance->items(),
                'meta' => [
                    'current_page' => $attendance->currentPage(),
                    'per_page' => $attendance->perPage(),
                    'total' => $attendance->total(),
                    'last_page' => $attendance->lastPage(),
                    'from' => $attendance->firstItem(),
                    'to' => $attendance->lastItem(),
                    'has_more_pages' => $attendance->hasMorePages()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store attendance record
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $type = $request->get('type', 'student');
            // Resolve academic year (body > middleware attribute > by name > current)
            $academicYearId = $request->attributes->get('academic_year_id')
                ?? $request->input('academic_year_id')
                ?? ($request->filled('academic_year') ? AcademicYear::query()->where('name', $request->input('academic_year'))->value('id') : null)
                ?? AcademicYear::current()->value('id');
            $academicYearName = $academicYearId
                ? (AcademicYear::query()->where('id', (int) $academicYearId)->value('name') ?? $request->input('academic_year'))
                : $request->input('academic_year');
            
            if ($type === 'student') {
                $validator = Validator::make($request->all(), [
                    'student_id' => 'required|exists:users,id',
                    'branch_id' => 'required|exists:branches,id',
                    'grade_level' => 'required|string',
                    'section' => 'required|string',
                    'date' => 'required|date',
                    'status' => 'required|in:Present,Absent,Late,Half-Day,Sick Leave,Leave',
                    'remarks' => 'nullable|string'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                // Check if already marked
                $existing = DB::table('student_attendance')
                    ->where('student_id', $request->student_id)
                    ->whereDate('date', $request->date)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attendance already marked for this student on this date'
                    ], 422);
                }

                $branch = Branch::find($request->branch_id);
                $schoolId = $branch ? $branch->school_id : null;

                DB::table('student_attendance')->insert([
                    'student_id' => $request->student_id,
                    'branch_id' => $request->branch_id,
                    'school_id' => $schoolId,
                    'grade_level' => $request->grade_level,
                    'section' => $request->section,
                    'date' => $request->date,
                    'status' => $request->status,
                    'remarks' => $request->remarks,
                    'marked_by' => auth()->user()->email ?? null,
                    'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                    'academic_year' => $academicYearName,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                $validator = Validator::make($request->all(), [
                    'teacher_id' => 'required|exists:users,id',
                    'branch_id' => 'required|exists:branches,id',
                    'date' => 'required|date',
                    'status' => 'required|in:Present,Absent,Late,Half-Day,Leave',
                    'remarks' => 'nullable|string'
                ]);

                if ($validator->fails()) {
                    return response()->json([
                        'success' => false,
                        'errors' => $validator->errors()
                    ], 422);
                }

                $existing = DB::table('teacher_attendance')
                    ->where('teacher_id', $request->teacher_id)
                    ->whereDate('date', $request->date)
                    ->first();

                if ($existing) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Attendance already marked for this teacher on this date'
                    ], 422);
                }

                $branch = Branch::find($request->branch_id);
                $schoolId = $branch ? $branch->school_id : null;

                DB::table('teacher_attendance')->insert([
                    'teacher_id' => $request->teacher_id,
                    'branch_id' => $request->branch_id,
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                    'date' => $request->date,
                    'status' => $request->status,
                    'remarks' => $request->remarks,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance marked successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Mark bulk attendance
     */
    public function markBulk(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:student,teacher',
                'date' => 'required|date',
                'branch_id' => 'required|exists:branches,id',
                'attendance' => 'required|array',
                'attendance.*.id' => 'required|exists:users,id',
                'attendance.*.status' => 'required|in:Present,Absent,Late,Half-Day,Sick Leave,Leave',
                'attendance.*.remarks' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = $request->type;
            $marked = 0;
            $errors = [];

            // Resolve academic year (body > middleware attribute > by name > current)
            $academicYearId = $request->attributes->get('academic_year_id')
                ?? $request->input('academic_year_id')
                ?? ($request->filled('academic_year') ? AcademicYear::query()->where('name', $request->input('academic_year'))->value('id') : null)
                ?? AcademicYear::current()->value('id');
            $academicYearName = $academicYearId
                ? (AcademicYear::query()->where('id', (int) $academicYearId)->value('name') ?? $request->input('academic_year'))
                : $request->input('academic_year');

            $branch = Branch::find($request->branch_id);
            $schoolId = $branch ? $branch->school_id : null;

            foreach ($request->attendance as $item) {
                try {
                    if ($type === 'student') {
                        // Check grade and section are provided
                        if (!isset($item['grade_level']) || !isset($item['section'])) {
                            $errors[] = "Grade and section required for student ID: {$item['id']}";
                            continue;
                        }

                        DB::table('student_attendance')->updateOrInsert(
                            [
                                'student_id' => $item['id'],
                                'date' => $request->date
                            ],
                            [
                                'branch_id' => $request->branch_id,
                                'school_id' => $schoolId,
                                'grade_level' => $item['grade_level'],
                                'section' => $item['section'],
                                'status' => $item['status'],
                                'remarks' => $item['remarks'] ?? null,
                                'marked_by' => auth()->user()->email ?? null,
                                'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                                'academic_year' => $academicYearName,
                                'updated_at' => now(),
                                'created_at' => now()
                            ]
                        );
                    } else {
                        DB::table('teacher_attendance')->updateOrInsert(
                            [
                                'teacher_id' => $item['id'],
                                'date' => $request->date
                            ],
                            [
                                'branch_id' => $request->branch_id,
                                'school_id' => $schoolId,
                                'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                                'status' => $item['status'],
                                'remarks' => $item['remarks'] ?? null,
                                'updated_at' => now(),
                                'created_at' => now()
                            ]
                        );
                    }
                    $marked++;
                } catch (\Exception $e) {
                    $errors[] = "Failed for ID {$item['id']}: " . $e->getMessage();
                }
            }

            DB::commit();

            // If nothing was marked, treat as failure so UI doesn't show false success
            if ($marked === 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'No attendance records were saved. Please check errors and payload (grade/section, IDs, academic year).',
                    'errors' => $errors,
                ], 422);
            }

            return response()->json([
                'success' => true,
                'message' => "Bulk attendance marked successfully",
                'data' => [
                    'marked' => $marked,
                    'errors' => $errors,
                    'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                    'academic_year' => $academicYearName,
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking bulk attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking bulk attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get teacher attendance
     */
    public function getTeacherAttendance($teacherId)
    {
        try {
            // Get teacher info first
            $teacher = DB::table('users')
                ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                ->where('users.id', $teacherId)
                ->select(
                    'users.id as user_id',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone',
                    'teachers.employee_id',
                    'teachers.designation',
                    'teachers.employee_type'
                )
                ->first();
            
            if (!$teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found'
                ], 404);
            }
            
            // OPTIMIZED: Build base query with filters
            $baseQuery = DB::table('teacher_attendance')
                ->where('teacher_id', $teacherId);
            
            if (request()->has('from_date')) {
                $baseQuery->whereDate('date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $baseQuery->whereDate('date', '<=', request('to_date'));
            }
            
            // Get attendance records
            $attendance = (clone $baseQuery)->orderBy('date', 'desc')->get();

            // OPTIMIZED: Calculate summary using SQL aggregation instead of PHP loops
            $summaryQuery = (clone $baseQuery)
                ->select(
                    DB::raw('COUNT(*) as total_days'),
                    DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                    DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                    DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late'),
                    DB::raw('SUM(CASE WHEN status IN ("Sick Leave", "Leave") THEN 1 ELSE 0 END) as leaves'),
                    DB::raw('SUM(CASE WHEN status = "Half-Day" THEN 1 ELSE 0 END) as half_day')
                )
                ->first();

            $totalDays = $summaryQuery->total_days ?? 0;
            $presentCount = $summaryQuery->present ?? 0;
            
            $summary = [
                'total_days' => (int) $totalDays,
                'present' => (int) $presentCount,
                'absent' => (int) ($summaryQuery->absent ?? 0),
                'late' => (int) ($summaryQuery->late ?? 0),
                'leaves' => (int) ($summaryQuery->leaves ?? 0),
                'half_day' => (int) ($summaryQuery->half_day ?? 0),
                'percentage' => $totalDays > 0 
                    ? round(($presentCount / $totalDays) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'summary' => $summary,
                'teacher' => $teacher // Include teacher info
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching teacher attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching teacher attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get student attendance
     */
    public function getStudentAttendance($studentId)
    {
        try {
            // Get student info first (including academic_year)
            $student = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->where('students.user_id', $studentId)
                ->select(
                    'students.id as student_db_id',
                    'students.user_id',
                    'students.admission_number',
                    'students.grade',
                    'students.academic_year',
                    'grades.label as grade_label',
                    'students.section',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'branches.name as branch_name'
                )
                ->first();
            
            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }
            
            // Build base query with filters
            // Filter by academic_year_id when toolbar year is set (from X-Academic-Year-Id header)
            $baseQuery = DB::table('student_attendance')
                ->where('student_id', $studentId);

            $academicYearId = request()->attributes->get('academic_year_id');
            if ($academicYearId && \Illuminate\Support\Facades\Schema::hasColumn('student_attendance', 'academic_year_id')) {
                $baseQuery->where('student_attendance.academic_year_id', (int) $academicYearId);
            } elseif ($academicYearId) {
                $academicYearName = AcademicYear::query()->where('id', $academicYearId)->value('name');
                if ($academicYearName) {
                    $baseQuery->where('student_attendance.academic_year', $academicYearName);
                }
            }
            
            if (request()->has('from_date')) {
                $baseQuery->whereDate('date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $baseQuery->whereDate('date', '<=', request('to_date'));
            }
            
            // Get attendance records
            $attendance = (clone $baseQuery)->orderBy('date', 'desc')->get();

            // OPTIMIZED: Calculate summary using SQL aggregation instead of PHP loops
            $summaryQuery = (clone $baseQuery)
                ->select(
                    DB::raw('COUNT(*) as total_days'),
                    DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                    DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                    DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late'),
                    DB::raw('SUM(CASE WHEN status IN ("Sick Leave", "Leave") THEN 1 ELSE 0 END) as leaves'),
                    DB::raw('SUM(CASE WHEN status = "Half-Day" THEN 1 ELSE 0 END) as half_day')
                )
                ->first();

            $totalDays = $summaryQuery->total_days ?? 0;
            $presentCount = $summaryQuery->present ?? 0;
            
            $summary = [
                'total_days' => (int) $totalDays,
                'present' => (int) $presentCount,
                'absent' => (int) ($summaryQuery->absent ?? 0),
                'late' => (int) ($summaryQuery->late ?? 0),
                'leaves' => (int) ($summaryQuery->leaves ?? 0),
                'half_day' => (int) ($summaryQuery->half_day ?? 0),
                'percentage' => $totalDays > 0 
                    ? round(($presentCount / $totalDays) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'summary' => $summary,
                'student' => $student // Include student info
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching student attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching student attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Current academic year attendance for student header + month-wise absent breakdown.
     */
    public function getStudentAttendanceOverview($studentId)
    {
        try {
            $student = DB::table('students')
                ->where('user_id', $studentId)
                ->whereNull('deleted_at')
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found',
                ], 404);
            }

            $baseQuery = DB::table('student_attendance')->where('student_id', $studentId);
            $academicYearId = request()->attributes->get('academic_year_id');
            $academicYearName = null;

            if ($academicYearId) {
                $year = AcademicYear::query()->find((int) $academicYearId);
                $academicYearName = $year?->name;
                if (\Illuminate\Support\Facades\Schema::hasColumn('student_attendance', 'academic_year_id')) {
                    $baseQuery->where('student_attendance.academic_year_id', (int) $academicYearId);
                } elseif ($academicYearName) {
                    $baseQuery->where('student_attendance.academic_year', $academicYearName);
                }
            }

            $summary = $this->summarizeStudentAttendance($baseQuery);
            $byMonth = $this->getAttendanceAbsentByMonth($baseQuery);

            return response()->json([
                'success' => true,
                'data' => [
                    'current_year' => array_merge($summary, [
                        'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                        'academic_year_name' => $academicYearName,
                    ]),
                    'by_month' => $byMonth,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching student attendance overview: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error fetching student attendance overview',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function summarizeStudentAttendance($query): array
    {
        $summaryQuery = (clone $query)
            ->select(
                DB::raw('COUNT(*) as total_days'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN status IN ("Sick Leave", "Leave") THEN 1 ELSE 0 END) as leaves'),
                DB::raw('SUM(CASE WHEN status = "Half-Day" THEN 1 ELSE 0 END) as half_day')
            )
            ->first();

        $totalDays = (int) ($summaryQuery->total_days ?? 0);
        $presentCount = (int) ($summaryQuery->present ?? 0);

        return [
            'total_days' => $totalDays,
            'present' => $presentCount,
            'absent' => (int) ($summaryQuery->absent ?? 0),
            'late' => (int) ($summaryQuery->late ?? 0),
            'leaves' => (int) ($summaryQuery->leaves ?? 0),
            'half_day' => (int) ($summaryQuery->half_day ?? 0),
            'percentage' => $totalDays > 0
                ? round(($presentCount / $totalDays) * 100, 2)
                : 0,
        ];
    }

    /**
     * @param \Illuminate\Database\Query\Builder $query
     */
    private function getAttendanceAbsentByMonth($query): array
    {
        $rows = (clone $query)
            ->select(
                DB::raw('DATE_FORMAT(date, "%Y-%m") as month'),
                DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('COUNT(*) as total_days')
            )
            ->groupBy(DB::raw('DATE_FORMAT(date, "%Y-%m")'))
            ->orderBy('month', 'desc')
            ->get();

        return $rows->map(function ($row) {
            $month = (string) ($row->month ?? '');
            $label = $month;
            try {
                $label = Carbon::createFromFormat('Y-m', $month)->format('M Y');
            } catch (\Exception $e) {
                // keep raw month key
            }

            return [
                'month' => $month,
                'month_label' => $label,
                'absent' => (int) ($row->absent ?? 0),
                'present' => (int) ($row->present ?? 0),
                'total_days' => (int) ($row->total_days ?? 0),
            ];
        })->values()->all();
    }

    /**
     * Get class attendance
     */
    public function getClassAttendance($grade, $section)
    {
        try {
            $date = request()->get('date', date('Y-m-d'));

            $attendance = DB::table('student_attendance')
                ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('students.grade', $grade)
                ->where('students.section', $section)
                ->whereNull('students.deleted_at') // ✅ ONLY ACTIVE STUDENTS
                ->whereDate('student_attendance.date', $date)
                ->scopedToTenant('student_attendance.branch_id')
                ->select(
                    'student_attendance.*',
                    'users.first_name',
                    'users.last_name',
                    'students.admission_number',
                    'students.roll_number'
                )
                ->orderBy('students.roll_number')
                ->get();

            // OPTIMIZED: Calculate summary using SQL instead of PHP
            $summaryQuery = DB::table('student_attendance')
                ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                ->where('students.grade', $grade)
                ->where('students.section', $section)
                ->whereNull('students.deleted_at') // ✅ ONLY ACTIVE STUDENTS
                ->whereDate('student_attendance.date', $date)
                ->scopedToTenant('student_attendance.branch_id')
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN student_attendance.status = "Present" THEN 1 ELSE 0 END) as present'),
                    DB::raw('SUM(CASE WHEN student_attendance.status = "Absent" THEN 1 ELSE 0 END) as absent')
                )
                ->first();

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'meta' => [
                    'grade' => $grade,
                    'section' => $section,
                    'date' => $date,
                    'total' => (int) ($summaryQuery->total ?? 0),
                    'present' => (int) ($summaryQuery->present ?? 0),
                    'absent' => (int) ($summaryQuery->absent ?? 0)
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching class attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching class attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get attendance report
     */
    public function getReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'type' => 'required|in:student,teacher',
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'branch_id' => 'nullable|exists:branches,id',
                'grade' => 'nullable|string',
                'section' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = $request->type;
            $table = $type . '_attendance';

            $query = DB::table($table)
                ->whereBetween('date', [$request->from_date, $request->to_date])
                ->scopedToTenant('branch_id');

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($type === 'student' && $request->has('grade')) {
                $query->where('grade_level', $request->grade);
            }

            if ($type === 'student' && $request->has('section')) {
                $query->where('section', $request->section);
            }

            $records = $query->get();

            // OPTIMIZED: Calculate summary using SQL aggregation
            $summaryQuery = (clone DB::table($table)
                ->whereBetween('date', [$request->from_date, $request->to_date])
                ->scopedToTenant('branch_id'));

            if ($request->has('branch_id')) {
                $summaryQuery->where('branch_id', $request->branch_id);
            }
            if ($type === 'student' && $request->has('grade')) {
                $summaryQuery->where('grade_level', $request->grade);
            }
            if ($type === 'student' && $request->has('section')) {
                $summaryQuery->where('section', $request->section);
            }

            $summaryData = $summaryQuery->select(
                DB::raw('COUNT(*) as total_records'),
                DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late')
            )->first();

            $totalRecords = $summaryData->total_records ?? 0;
            $presentCount = $summaryData->present ?? 0;

            $summary = [
                'total_records' => (int) $totalRecords,
                'present' => (int) $presentCount,
                'absent' => (int) ($summaryData->absent ?? 0),
                'late' => (int) ($summaryData->late ?? 0),
                'percentage' => $totalRecords > 0
                    ? round(($presentCount / $totalRecords) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $records,
                'summary' => $summary,
                'filters' => [
                    'from_date' => $request->from_date,
                    'to_date' => $request->to_date,
                    'type' => $type
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating attendance report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate attendance report for specific student
     */
    public function generateReport($studentId)
    {
        try {
            $from_date = request()->get('from_date', date('Y-m-01'));
            $to_date = request()->get('to_date', date('Y-m-d'));

            // Get attendance records
            $attendance = DB::table('student_attendance')
                ->where('student_id', $studentId)
                ->whereBetween('date', [$from_date, $to_date])
                ->scopedToTenant('branch_id')
                ->orderBy('date', 'desc')
                ->get();

            // OPTIMIZED: Calculate summary using SQL aggregation
            $summaryQuery = DB::table('student_attendance')
                ->where('student_id', $studentId)
                ->whereBetween('date', [$from_date, $to_date])
                ->scopedToTenant('branch_id')
                ->select(
                    DB::raw('COUNT(*) as total_days'),
                    DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
                    DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
                    DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late'),
                    DB::raw('SUM(CASE WHEN status IN ("Sick Leave", "Leave") THEN 1 ELSE 0 END) as leaves')
                )
                ->first();

            $totalDays = $summaryQuery->total_days ?? 0;
            $presentCount = $summaryQuery->present ?? 0;

            $summary = [
                'total_days' => (int) $totalDays,
                'present' => (int) $presentCount,
                'absent' => (int) ($summaryQuery->absent ?? 0),
                'late' => (int) ($summaryQuery->late ?? 0),
                'leaves' => (int) ($summaryQuery->leaves ?? 0),
                'percentage' => $totalDays > 0
                    ? round(($presentCount / $totalDays) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'summary' => $summary,
                'period' => [
                    'from' => $from_date,
                    'to' => $to_date
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating student report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    // Dummy methods for resource routes
    public function show($id) {
        try {
            // Get single attendance record by ID
            $attendance = DB::table('student_attendance')
                ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->where('student_attendance.id', $id)
                ->select(
                    'student_attendance.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'students.admission_number',
                    'students.grade',
                    'grades.label as grade_label',
                    'students.section'
                )
                ->first();
            
            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance record not found'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $attendance
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance record: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance record',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function update(Request $request, $id) {
        try {
            // Validate the request
            $validated = $request->validate([
                'status' => 'required|in:Present,Absent,Late,Half-Day,Sick Leave,Leave',
                'remarks' => 'nullable|string|max:500',
                'date' => 'nullable|date',
                'marked_by' => 'nullable|string|max:255'
            ]);

            // Check if attendance record exists
            $attendance = DB::table('student_attendance')
                ->where('id', $id)
                ->first();

            if (!$attendance) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance record not found'
                ], 404);
            }

            // Prepare update data
            $updateData = [
                'status' => $validated['status'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_at' => now()
            ];

            // Add optional fields if provided
            if (isset($validated['date'])) {
                $updateData['date'] = $validated['date'];
            }

            if (isset($validated['marked_by'])) {
                $updateData['marked_by'] = $validated['marked_by'];
            }

            // Update the attendance record
            DB::table('student_attendance')
                ->where('id', $id)
                ->update($updateData);

            // Fetch the updated record with student details
            $updatedAttendance = DB::table('student_attendance')
                ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->where('student_attendance.id', $id)
                ->select(
                    'student_attendance.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'students.admission_number',
                    'students.grade',
                    'grades.label as grade_label',
                    'students.section'
                )
                ->first();

            Log::info('Attendance updated successfully', [
                'id' => $id,
                'status' => $validated['status'],
                'updated_by' => auth()->user()->email ?? 'system'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attendance updated successfully',
                'data' => $updatedAttendance
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error updating attendance: ' . $e->getMessage(), [
                'id' => $id,
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Error updating attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function destroy($id) {
        return response()->json(['message' => 'Attendance deletion not allowed'], 400);
    }

    /**
     * Export attendance data
     * Supports Excel, PDF, and CSV formats
     * Reusable for both student and teacher attendance
     */
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:excel,pdf,csv',
                'type' => 'required|in:student,teacher',
                'columns' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $type = $request->type; // 'student' or 'teacher'
            $format = $request->format;
            $columns = $request->columns; // Custom columns if provided

            // Build query with same filters as index method
            $query = $this->buildAttendanceQuery($request, $type);

            // Get all matching records (respecting filters but not pagination)
            $attendanceRecords = $query->get();

            // Export based on format
            return match($format) {
                'excel' => $this->exportExcel($attendanceRecords, $type, $columns),
                'pdf' => $this->exportPdf($attendanceRecords, $type, $columns),
                'csv' => $this->exportCsv($attendanceRecords, $type, $columns),
            };

        } catch (\Exception $e) {
            Log::error('Export attendance error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Build attendance query with filters (reusable for index and export)
     */
    protected function buildAttendanceQuery(Request $request, string $type)
    {
        $academicYearId = $request->attributes->get('academic_year_id');

        if ($type === 'student') {
            $query = DB::table('student_attendance')
                ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->whereNull('students.deleted_at') // ✅ ONLY ACTIVE STUDENTS
                ->select(
                    'student_attendance.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'students.admission_number',
                    'students.grade',
                    'grades.label as grade_label',
                    'students.section'
                );
        } else {
            $query = DB::table('teacher_attendance')
                ->join('users', 'teacher_attendance.teacher_id', '=', 'users.id')
                ->leftJoin('teachers', 'users.id', '=', 'teachers.user_id')
                ->whereNull('teachers.deleted_at') // ✅ ONLY ACTIVE TEACHERS
                ->select(
                    'teacher_attendance.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'teachers.employee_id'
                );
        }

        if ($academicYearId) {
            $query->where($type . '_attendance.academic_year_id', (int) $academicYearId);
        }

        // Apply branch filtering
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $query->whereIn($type . '_attendance.branch_id', $accessibleBranchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply filters
        if ($request->has('branch_id') && $accessibleBranchIds === 'all') {
            $query->where($type . '_attendance.branch_id', $request->branch_id);
        }

        if ($request->has('date')) {
            $query->whereDate($type . '_attendance.date', $request->date);
        }

        if ($request->has('from_date')) {
            $query->whereDate($type . '_attendance.date', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate($type . '_attendance.date', '<=', $request->to_date);
        }

        if ($request->has('status')) {
            $query->where($type . '_attendance.status', $request->status);
        }

        if ($type === 'student') {
            if ($request->has('grade')) {
                $query->where('students.grade', $request->grade);
            }
            if ($request->has('section')) {
                $query->where('students.section', $request->section);
            }
        }

        // OPTIMIZED Search filter - prefix search for better index usage
        if ($request->has('search') && !empty($request->search)) {
            $search = strip_tags($request->search);
            $query->where(function($q) use ($search, $type) {
                $q->where('users.first_name', 'like', "{$search}%")
                  ->orWhere('users.last_name', 'like', "{$search}%")
                  ->orWhere('users.email', 'like', "{$search}%");
                
                if ($type === 'student') {
                    $q->orWhere('students.admission_number', 'like', "{$search}%");
                }
            });
        }

        return $query;
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, string $type, ?array $columns)
    {
        $export = new AttendanceExport(collect($data), $type, $columns);
        $module = $type === 'student' ? 'student_attendance' : 'teacher_attendance';
        $filename = (new ExportService($module))->generateFilename('xlsx');
        
        return Excel::download($export, $filename);
    }

    /**
     * Export to PDF
     */
    protected function exportPdf($data, string $type, ?array $columns)
    {
        $module = $type === 'student' ? 'student_attendance' : 'teacher_attendance';
        $pdfService = new PdfExportService($module);
        
        if ($columns) {
            $pdfService->setColumns($columns);
        }
        
        // Use A3 paper size for attendance to accommodate more columns
        $pdfService->setPaperSize('a3');
        $pdfService->setOrientation('landscape');
        
        $title = $type === 'student' ? 'Student Attendance Report' : 'Teacher Attendance Report';
        $pdf = $pdfService->generate(collect($data), $title);
        $filename = (new ExportService($module))->generateFilename('pdf');
        
        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    protected function exportCsv($data, string $type, ?array $columns)
    {
        $module = $type === 'student' ? 'student_attendance' : 'teacher_attendance';
        $csvService = new CsvExportService($module);
        
        if ($columns) {
            $csvService->setColumns($columns);
        }
        
        $filename = (new ExportService($module))->generateFilename('csv');
        
        return $csvService->generate(collect($data), $filename);
    }

    /**
     * Get attendance dashboard overview with date range filtering
     * ✅ OPTIMIZED: Returns aggregated data for dashboard view
     * Supports: today, week, month, custom date range
     */
    public function getDashboard(Request $request)
    {
        try {
            $type = $request->get('type', 'student'); // student or teacher
            $dateRange = $this->parseAttendanceDateRange($request);
            $fromDate = $dateRange['from'];
            $toDate = $dateRange['to'];
            $period = $request->get('period', 'today');
            
            Log::info('Attendance Dashboard Request', [
                'type' => $type,
                'period' => $period,
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'filters' => $request->all()
            ]);
            
            if ($type === 'student') {
                return $this->getStudentAttendanceDashboard($request, $fromDate, $toDate, $period);
            } else {
                return $this->getTeacherAttendanceDashboard($request, $fromDate, $toDate, $period);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching attendance dashboard: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance dashboard',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get student attendance dashboard data
     */
    private function getStudentAttendanceDashboard(Request $request, $fromDate, $toDate, $period)
    {
        // Base query for student attendance - Similar to main dashboard approach
        $baseQuery = DB::table('student_attendance as sa')
            ->whereBetween('sa.date', [$fromDate, $toDate]);

        // Academic year scoping (from toolbar dropdown via middleware)
        $academicYearId = $request->attributes->get('academic_year_id');
        if ($academicYearId) {
            $baseQuery->where('sa.academic_year_id', (int) $academicYearId);
        } elseif ($request->filled('academic_year')) {
            $baseQuery->where('sa.academic_year', $request->input('academic_year'));
        }

        // Apply school filtering (only if school_id is set and not null)
        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $baseQuery->where(function($query) use ($schoolId) {
                $query->where('sa.school_id', $schoolId)
                      ->orWhereNull('sa.school_id'); // Include records without school_id
            });
        }

        // Apply branch filtering
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $baseQuery->whereIn('sa.branch_id', $accessibleBranchIds);
            } else {
                $baseQuery->whereRaw('1 = 0');
            }
        }

        // Filter by branch
        if ($request->has('branch_id') && $request->branch_id) {
            $baseQuery->where('sa.branch_id', $request->branch_id);
        }

        // For breakdown queries, we need joins (grades join must be branch-specific to avoid duplicate rows)
        $breakdownQuery = DB::table('student_attendance as sa')
            ->join('students as s', 'sa.student_id', '=', 's.user_id')
            ->join('users as u', 's.user_id', '=', 'u.id')
            ->leftJoin('grades as g', function ($join) {
                $join->on('g.value', '=', 's.grade');
                if (\Illuminate\Support\Facades\Schema::hasColumn('grades', 'branch_id')) {
                    $join->on('g.branch_id', '=', 's.branch_id');
                }
            })
            ->whereBetween('sa.date', [$fromDate, $toDate])
            ->whereNull('s.deleted_at');

        if ($academicYearId) {
            $breakdownQuery->where('sa.academic_year_id', (int) $academicYearId);
        } elseif ($request->filled('academic_year')) {
            $breakdownQuery->where('sa.academic_year', $request->input('academic_year'));
        }

        // Apply same filters to breakdown query
        if ($schoolId) {
            $breakdownQuery->where(function($query) use ($schoolId) {
                $query->where('sa.school_id', $schoolId)
                      ->orWhereNull('sa.school_id');
            });
        }

        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $breakdownQuery->whereIn('sa.branch_id', $accessibleBranchIds);
            } else {
                $breakdownQuery->whereRaw('1 = 0');
            }
        }

        if ($request->has('branch_id') && $request->branch_id) {
            $breakdownQuery->where('sa.branch_id', $request->branch_id);
        }

        // Filter by grade (only apply to breakdownQuery since it has the join)
        if ($request->has('grade') && $request->grade) {
            $breakdownQuery->where('s.grade', $request->grade);
        }

        // Filter by section (only apply to breakdownQuery since it has the join)
        if ($request->has('section') && $request->section) {
            $breakdownQuery->where('s.section', $request->section);
        }

        // Determine which query to use for summary based on filters
        // If grade or section filters are present, use breakdownQuery (has joins)
        // Otherwise, use baseQuery (simpler, no joins)
        $summaryQuerySource = ($request->has('grade') && $request->grade) || ($request->has('section') && $request->section)
            ? $breakdownQuery
            : $baseQuery;

        // Get summary (use appropriate query based on filters)
        $summaryQuery = (clone $summaryQuerySource)
            ->select(
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN sa.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN sa.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN sa.status = "Late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN sa.status IN ("Leave", "Sick Leave") THEN 1 ELSE 0 END) as leaves'),
                DB::raw('SUM(CASE WHEN sa.status = "Sick Leave" THEN 1 ELSE 0 END) as sick_leave'),
                DB::raw('SUM(CASE WHEN sa.status = "Leave" THEN 1 ELSE 0 END) as leave_count')
            )
            ->first();

        $summary = [
            'total_count' => (int) ($summaryQuery->total_count ?? 0),
            'present' => (int) ($summaryQuery->present ?? 0),
            'absent' => (int) ($summaryQuery->absent ?? 0),
            'late' => (int) ($summaryQuery->late ?? 0),
            'leaves' => (int) ($summaryQuery->leaves ?? 0),
            'sick_leave' => (int) ($summaryQuery->sick_leave ?? 0),
            'leave' => (int) ($summaryQuery->leave_count ?? 0),
        ];

        // Get total count for percentage calculation (use same query source as summary)
        $totalCount = (clone $summaryQuerySource)->count('sa.id');

        // Get breakdown by status (use same query source as summary)
        $byStatus = (clone $summaryQuerySource)
            ->select(
                'sa.status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('sa.status')
            ->get()
            ->map(function($item) use ($totalCount) {
                $count = (int) $item->count;
                $percentage = $totalCount > 0 ? round(($count / $totalCount) * 100, 2) : 0;
                
                return [
                    'status' => $item->status,
                    'count' => $count,
                    'percentage' => $percentage
                ];
            });

        // Get breakdown by grade (one row per grade; label from any matching grade row)
        $byGrade = (clone $breakdownQuery)
            ->select(
                's.grade',
                DB::raw('COALESCE(MAX(g.label), CONCAT("Grade ", s.grade)) as grade_label'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN sa.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN sa.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN sa.status = "Late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN sa.status = "Sick Leave" THEN 1 ELSE 0 END) as sick_leave'),
                DB::raw('SUM(CASE WHEN sa.status = "Leave" THEN 1 ELSE 0 END) as leave_count'),
                DB::raw('COUNT(DISTINCT sa.student_id) as student_count')
            )
            ->whereNotNull('s.grade')
            ->groupBy('s.grade')
            ->orderBy('s.grade')
            ->get()
            ->map(function($item) {
                $total = (int) $item->total;
                $present = (int) $item->present;
                $attendancePercentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                return [
                    'grade' => $item->grade,
                    'grade_label' => $item->grade_label,
                    'total' => $total,
                    'present' => $present,
                    'absent' => (int) $item->absent,
                    'late' => (int) $item->late,
                    'sick_leave' => (int) $item->sick_leave,
                    'leave' => (int) $item->leave_count,
                    'student_count' => (int) $item->student_count,
                    'attendance_percentage' => $attendancePercentage
                ];
            });

        // Get breakdown by section (one row per grade+section; label from any matching grade row)
        $bySection = (clone $breakdownQuery)
            ->select(
                's.grade',
                DB::raw('COALESCE(MAX(g.label), CONCAT("Grade ", s.grade)) as grade_label'),
                's.section',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN sa.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN sa.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN sa.status = "Late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN sa.status = "Sick Leave" THEN 1 ELSE 0 END) as sick_leave'),
                DB::raw('SUM(CASE WHEN sa.status = "Leave" THEN 1 ELSE 0 END) as leave_count'),
                DB::raw('COUNT(DISTINCT sa.student_id) as student_count')
            )
            ->whereNotNull('s.grade')
            ->whereNotNull('s.section')
            ->groupBy('s.grade', 's.section')
            ->orderBy('s.grade')
            ->orderBy('s.section')
            ->get()
            ->map(function($item) {
                $total = (int) $item->total;
                $present = (int) $item->present;
                $attendancePercentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;

                return [
                    'grade' => $item->grade,
                    'grade_label' => $item->grade_label,
                    'section' => $item->section,
                    'total' => $total,
                    'present' => $present,
                    'absent' => (int) $item->absent,
                    'late' => (int) $item->late,
                    'sick_leave' => (int) $item->sick_leave,
                    'leave' => (int) $item->leave_count,
                    'student_count' => (int) $item->student_count,
                    'attendance_percentage' => $attendancePercentage
                ];
            });

        // Grade + section: per-student aggregated stats (all students in class), same idea as branch-wise teachers
        if ($request->filled('grade') && $request->filled('section')) {
            $grade = $request->input('grade');
            $section = $request->input('section');

            $studentListQuery = DB::table('students as s')
                ->join('users as u', 's.user_id', '=', 'u.id')
                ->whereNull('s.deleted_at')
                ->whereNull('u.deleted_at')
                ->where('s.grade', $grade)
                ->where('s.section', $section);

            if ($schoolId) {
                $studentListQuery->where(function ($q) use ($schoolId) {
                    $q->where('s.school_id', $schoolId)->orWhereNull('s.school_id');
                });
            }

            if ($request->filled('branch_id') && $request->branch_id) {
                $studentListQuery->where('s.branch_id', (int) $request->branch_id);
            } elseif ($accessibleBranchIds !== 'all') {
                if (! empty($accessibleBranchIds)) {
                    $studentListQuery->whereIn('s.branch_id', $accessibleBranchIds);
                } else {
                    $studentListQuery->whereRaw('1 = 0');
                }
            }

            $allStudents = $studentListQuery
                ->select(
                    's.user_id as student_id',
                    'u.first_name',
                    'u.last_name',
                    'u.phone',
                    's.admission_number',
                    's.roll_number'
                )
                ->orderBy('s.roll_number')
                ->get();

            $studentIds = $allStudents->pluck('student_id')->filter()->unique()->values()->all();

            if (empty($studentIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'summary' => $summary,
                        'students' => [],
                    ],
                ]);
            }

            $allAttendanceQuery = DB::table('student_attendance as sa')
                ->whereBetween('sa.date', [$fromDate, $toDate])
                ->whereIn('sa.student_id', $studentIds);

            if ($academicYearId) {
                $allAttendanceQuery->where('sa.academic_year_id', (int) $academicYearId);
            } elseif ($request->filled('academic_year')) {
                $allAttendanceQuery->where('sa.academic_year', $request->input('academic_year'));
            }

            if ($schoolId) {
                $allAttendanceQuery->where(function ($q) use ($schoolId) {
                    $q->where('sa.school_id', $schoolId)->orWhereNull('sa.school_id');
                });
            }

            if ($request->filled('branch_id') && $request->branch_id) {
                $allAttendanceQuery->where('sa.branch_id', (int) $request->branch_id);
            } elseif ($accessibleBranchIds !== 'all' && ! empty($accessibleBranchIds)) {
                $allAttendanceQuery->whereIn('sa.branch_id', $accessibleBranchIds);
            }

            $allAttendanceRecords = $allAttendanceQuery
                ->select('sa.student_id', 'sa.status', 'sa.date')
                ->get()
                ->groupBy('student_id');

            $filteredAttendanceRecords = $allAttendanceRecords;
            if ($request->filled('status') && $request->status) {
                $filteredAttendanceQuery = DB::table('student_attendance as sa')
                    ->whereBetween('sa.date', [$fromDate, $toDate])
                    ->whereIn('sa.student_id', $studentIds)
                    ->where('sa.status', $request->status);

                if ($academicYearId) {
                    $filteredAttendanceQuery->where('sa.academic_year_id', (int) $academicYearId);
                } elseif ($request->filled('academic_year')) {
                    $filteredAttendanceQuery->where('sa.academic_year', $request->input('academic_year'));
                }

                if ($schoolId) {
                    $filteredAttendanceQuery->where(function ($q) use ($schoolId) {
                        $q->where('sa.school_id', $schoolId)->orWhereNull('sa.school_id');
                    });
                }

                if ($request->filled('branch_id') && $request->branch_id) {
                    $filteredAttendanceQuery->where('sa.branch_id', (int) $request->branch_id);
                } elseif ($accessibleBranchIds !== 'all' && ! empty($accessibleBranchIds)) {
                    $filteredAttendanceQuery->whereIn('sa.branch_id', $accessibleBranchIds);
                }

                $filteredAttendanceRecords = $filteredAttendanceQuery
                    ->select('sa.student_id', 'sa.status', 'sa.date')
                    ->get()
                    ->groupBy('student_id');
            }

            $studentList = $allStudents->map(function ($student) use ($allAttendanceRecords, $filteredAttendanceRecords, $request) {
                $sid = $student->student_id;
                $allRecords = $allAttendanceRecords->get($sid) ?? collect();
                $filteredRecords = $filteredAttendanceRecords->get($sid) ?? collect();

                $totalRecords = $allRecords->count();
                $presentCount = $allRecords->where('status', 'Present')->count();
                $absentCount = $allRecords->where('status', 'Absent')->count();
                $lateCount = $allRecords->where('status', 'Late')->count();
                $halfDayCount = $allRecords->where('status', 'Half-Day')->count();
                $sickLeaveCount = $allRecords->where('status', 'Sick Leave')->count();
                $generalLeaveCount = $allRecords->where('status', 'Leave')->count();
                $leaveCount = $sickLeaveCount + $generalLeaveCount;

                $latestRecord = $allRecords->sortByDesc('date')->first();

                return [
                    'student_id' => $sid,
                    'first_name' => $student->first_name,
                    'last_name' => $student->last_name,
                    'phone' => $student->phone,
                    'admission_number' => $student->admission_number,
                    'roll_number' => $student->roll_number,
                    'total_records' => $totalRecords,
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'late' => $lateCount,
                    'half_day' => $halfDayCount,
                    'leaves' => $leaveCount,
                    'sick_leave' => $sickLeaveCount,
                    'leave' => $generalLeaveCount,
                    'attendance_percentage' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0,
                    'latest_status' => $latestRecord ? $latestRecord->status : 'N/A',
                    'latest_date' => $latestRecord ? $latestRecord->date : null,
                    '_has_filtered_status' => $filteredRecords->count() > 0,
                ];
            })
                ->filter(function ($row) use ($request) {
                    if ($request->filled('status') && $request->status) {
                        return $row['_has_filtered_status'] === true;
                    }

                    return true;
                })
                ->map(function ($row) {
                    unset($row['_has_filtered_status']);

                    return $row;
                })
                ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'students' => $studentList,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'by_status' => $byStatus,
                'by_grade' => $byGrade,
                'by_section' => $bySection
            ]
        ]);
    }

    /**
     * Get teacher attendance dashboard data
     */
    private function getTeacherAttendanceDashboard(Request $request, $fromDate, $toDate, $period)
    {
        // Base query for teacher attendance - Similar to main dashboard approach
        $baseQuery = DB::table('teacher_attendance as ta')
            ->whereBetween('ta.date', [$fromDate, $toDate]);

        // Academic year scoping (from toolbar dropdown via middleware)
        $academicYearId = $request->attributes->get('academic_year_id');
        if ($academicYearId) {
            $baseQuery->where('ta.academic_year_id', (int) $academicYearId);
        } elseif ($request->filled('academic_year')) {
            $baseQuery->where('ta.academic_year', $request->input('academic_year'));
        }

        // Apply school filtering (only if school_id is set and not null)
        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $baseQuery->where(function($query) use ($schoolId) {
                $query->where('ta.school_id', $schoolId)
                      ->orWhereNull('ta.school_id'); // Include records without school_id
            });
        }

        // Apply branch filtering
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $baseQuery->whereIn('ta.branch_id', $accessibleBranchIds);
            } else {
                $baseQuery->whereRaw('1 = 0');
            }
        }

        // Filter by branch
        if ($request->has('branch_id') && $request->branch_id) {
            $baseQuery->where('ta.branch_id', $request->branch_id);
        }

        // For breakdown queries, we need joins
        $breakdownQuery = DB::table('teacher_attendance as ta')
            ->join('users as u', 'ta.teacher_id', '=', 'u.id')
            ->leftJoin('teachers as t', 'u.id', '=', 't.user_id')
            ->leftJoin('departments as d', 't.department_id', '=', 'd.id')
            ->whereBetween('ta.date', [$fromDate, $toDate]);

        if ($academicYearId) {
            $breakdownQuery->where('ta.academic_year_id', (int) $academicYearId);
        } elseif ($request->filled('academic_year')) {
            $breakdownQuery->where('ta.academic_year', $request->input('academic_year'));
        }

        // Apply same filters to breakdown query
        if ($schoolId) {
            $breakdownQuery->where(function($query) use ($schoolId) {
                $query->where('ta.school_id', $schoolId)
                      ->orWhereNull('ta.school_id');
            });
        }

        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $breakdownQuery->whereIn('ta.branch_id', $accessibleBranchIds);
            } else {
                $breakdownQuery->whereRaw('1 = 0');
            }
        }

        if ($request->has('branch_id') && $request->branch_id) {
            $breakdownQuery->where('ta.branch_id', $request->branch_id);
        }

        // Get summary (use baseQuery without joins for accurate count)
        $summaryQuery = (clone $baseQuery)
            ->select(
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN ta.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN ta.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN ta.status = "Late" THEN 1 ELSE 0 END) as late'),
                DB::raw('SUM(CASE WHEN ta.status IN ("Leave", "Sick Leave") THEN 1 ELSE 0 END) as leaves'),
                DB::raw('SUM(CASE WHEN ta.status = "Sick Leave" THEN 1 ELSE 0 END) as sick_leave'),
                DB::raw('SUM(CASE WHEN ta.status = "Leave" THEN 1 ELSE 0 END) as leave_count')
            )
            ->first();

        $summary = [
            'total_count' => (int) ($summaryQuery->total_count ?? 0),
            'present' => (int) ($summaryQuery->present ?? 0),
            'absent' => (int) ($summaryQuery->absent ?? 0),
            'late' => (int) ($summaryQuery->late ?? 0),
            'leaves' => (int) ($summaryQuery->leaves ?? 0),
            'sick_leave' => (int) ($summaryQuery->sick_leave ?? 0),
            'leave' => (int) ($summaryQuery->leave_count ?? 0),
        ];

        // Get total count for percentage calculation
        $totalCount = (clone $baseQuery)->count('ta.id');

        // Get breakdown by status (use baseQuery for simple count)
        $byStatus = (clone $baseQuery)
            ->select(
                'ta.status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('ta.status')
            ->get()
            ->map(function($item) use ($totalCount) {
                $count = (int) $item->count;
                $percentage = $totalCount > 0 ? round(($count / $totalCount) * 100, 2) : 0;
                
                return [
                    'status' => $item->status,
                    'count' => $count,
                    'percentage' => $percentage
                ];
            });

        // Get breakdown by department (use breakdownQuery for joins)
        $byDepartment = (clone $breakdownQuery)
            ->select(
                'd.id as department_id',
                DB::raw('COALESCE(d.name, "No Department") as department_name'),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN ta.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN ta.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('COUNT(DISTINCT ta.teacher_id) as teacher_count')
            )
            ->groupBy('d.id', 'd.name')
            ->orderBy('d.name')
            ->get()
            ->map(function($item) {
                $total = (int) $item->total;
                $present = (int) $item->present;
                $attendancePercentage = $total > 0 ? round(($present / $total) * 100, 2) : 0;
                
                return [
                    'department_id' => $item->department_id,
                    'department_name' => $item->department_name,
                    'total' => $total,
                    'present' => $present,
                    'absent' => (int) $item->absent,
                    'teacher_count' => (int) $item->teacher_count,
                    'attendance_percentage' => $attendancePercentage
                ];
            });

        // If return_teachers is requested, return detailed teacher list
        // NOTE: branch_id is optional (when omitted, we return teachers across all accessible branches).
        if ($request->get('return_teachers', false)) {
            $requestedBranchId = $request->input('branch_id');
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);

            // Get ALL teachers for the selected branch(s), then LEFT JOIN/aggregate with attendance
            $teacherListQuery = DB::table('teachers as t')
                ->join('users as u', 't.user_id', '=', 'u.id')
                ->leftJoin('departments as d', 't.department_id', '=', 'd.id')
                ->whereNull('t.deleted_at')
                ->whereNull('u.deleted_at');

            // Apply branch filter
            if ($requestedBranchId) {
                $teacherListQuery->where('t.branch_id', (int) $requestedBranchId);
            } elseif ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $teacherListQuery->whereIn('t.branch_id', $accessibleBranchIds);
                } else {
                    // No accessible branches -> return empty
                    $teacherListQuery->whereRaw('1 = 0');
                }
            }

            // Apply school filtering if needed
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $teacherListQuery->where(function($query) use ($schoolId) {
                    $query->where('t.school_id', $schoolId)
                          ->orWhereNull('t.school_id');
                });
            }

            // Get all teachers with their basic info
            $allTeachers = $teacherListQuery
                ->select(
                    't.user_id as teacher_id',
                    'u.id as user_id',
                    'u.first_name',
                    'u.last_name',
                    'u.email',
                    'u.phone',
                    't.employee_id',
                    't.designation',
                    DB::raw('COALESCE(d.name, "No Department") as department_name')
                )
                ->orderBy('u.first_name')
                ->orderBy('u.last_name')
                ->get();

            // Get ALL attendance records for these teachers in the date range (for stats calculation)
            $allAttendanceQuery = DB::table('teacher_attendance as ta')
                ->whereBetween('ta.date', [$fromDate, $toDate])
                ->whereIn('ta.teacher_id', $allTeachers->pluck('teacher_id')->toArray());

            // Apply school filtering
            if ($schoolId) {
                $allAttendanceQuery->where(function($query) use ($schoolId) {
                    $query->where('ta.school_id', $schoolId)
                          ->orWhereNull('ta.school_id');
                });
            }

            // Apply branch filter when a branch_id is explicitly provided
            if ($requestedBranchId) {
                $allAttendanceQuery->where('ta.branch_id', (int) $requestedBranchId);
            } elseif ($accessibleBranchIds !== 'all' && !empty($accessibleBranchIds)) {
                $allAttendanceQuery->whereIn('ta.branch_id', $accessibleBranchIds);
            }

            // Get all attendance records for stats calculation
            $allAttendanceRecords = $allAttendanceQuery
                ->select('ta.teacher_id', 'ta.status', 'ta.date')
                ->get()
                ->groupBy('teacher_id');

            // If status filter is provided, get filtered records to determine which teachers to show
            $filteredAttendanceRecords = $allAttendanceRecords;
            if ($request->has('status') && $request->status) {
                $filteredAttendanceQuery = (clone $allAttendanceQuery)
                    ->where('ta.status', $request->status);
                
                $filteredAttendanceRecords = $filteredAttendanceQuery
                    ->select('ta.teacher_id', 'ta.status', 'ta.date')
                    ->get()
                    ->groupBy('teacher_id');
            }

            // Combine teachers with their attendance stats
            $teacherList = $allTeachers->map(function($teacher) use ($allAttendanceRecords, $filteredAttendanceRecords, $request) {
                $teacherId = $teacher->teacher_id;
                
                // Use all records for stats calculation (to show complete stats)
                $allRecords = $allAttendanceRecords->get($teacherId) ?? collect();
                
                // Use filtered records to determine if teacher should be shown
                $filteredRecords = $filteredAttendanceRecords->get($teacherId) ?? collect();
                
                // Calculate stats from all records
                $totalRecords = $allRecords->count();
                $presentCount = $allRecords->where('status', 'Present')->count();
                $absentCount = $allRecords->where('status', 'Absent')->count();
                $lateCount = $allRecords->where('status', 'Late')->count();
                $sickLeaveCount = $allRecords->where('status', 'Sick Leave')->count();
                $generalLeaveCount = $allRecords->where('status', 'Leave')->count();
                $leaveCount = $sickLeaveCount + $generalLeaveCount;

                $latestRecord = $allRecords->sortByDesc('date')->first();

                return [
                    'teacher_id' => $teacherId,
                    'user_id' => $teacher->user_id,
                    'first_name' => $teacher->first_name,
                    'last_name' => $teacher->last_name,
                    'email' => $teacher->email,
                    'phone' => $teacher->phone,
                    'employee_id' => $teacher->employee_id,
                    'designation' => $teacher->designation,
                    'department_name' => $teacher->department_name,
                    'total_records' => $totalRecords,
                    'present' => $presentCount,
                    'absent' => $absentCount,
                    'late' => $lateCount,
                    'leaves' => $leaveCount,
                    'sick_leave' => $sickLeaveCount,
                    'leave' => $generalLeaveCount,
                    'attendance_percentage' => $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 2) : 0,
                    'latest_status' => $latestRecord ? $latestRecord->status : 'N/A',
                    'latest_date' => $latestRecord ? $latestRecord->date : null,
                    '_has_filtered_status' => $filteredRecords->count() > 0 // Flag to filter by
                ];
            })
            ->filter(function($teacher) use ($request) {
                // If status filter is applied, only show teachers who have at least one record with that status
                if ($request->has('status') && $request->status) {
                    return $teacher['_has_filtered_status'] === true;
                }
                // Otherwise show all teachers (even with 0 records)
                return true;
            })
            ->map(function($teacher) {
                // Remove the internal flag before returning
                unset($teacher['_has_filtered_status']);
                return $teacher;
            })
            ->values();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => $summary,
                    'teachers' => $teacherList
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $summary,
                'by_status' => $byStatus,
                'by_department' => $byDepartment
            ]
        ]);
    }

    /**
     * Parse attendance date range from request
     */
    private function parseAttendanceDateRange(Request $request): array
    {
        $period = $request->get('period', 'today');
        
        switch ($period) {
            case 'today':
                return [
                    'from' => Carbon::today()->toDateString(),
                    'to' => Carbon::today()->toDateString()
                ];
            
            case 'week':
                return [
                    'from' => Carbon::now()->startOfWeek()->toDateString(),
                    'to' => Carbon::now()->endOfWeek()->toDateString()
                ];
            
            case 'month':
                return [
                    'from' => Carbon::now()->startOfMonth()->toDateString(),
                    'to' => Carbon::now()->endOfMonth()->toDateString()
                ];
            
            case 'custom':
                return [
                    'from' => $request->get('from_date', Carbon::today()->toDateString()),
                    'to' => $request->get('to_date', Carbon::today()->toDateString())
                ];
            
            default:
                return [
                    'from' => Carbon::today()->toDateString(),
                    'to' => Carbon::today()->toDateString()
                ];
        }
    }
}
