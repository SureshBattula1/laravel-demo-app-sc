<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Attendance;
use App\Models\Exam;
use App\Models\ExamResult;
use App\Models\Event;
use App\Models\FeePayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class DashboardController extends Controller
{
    /**
     * Get comprehensive dashboard statistics with date range filtering
     * OPTIMIZED: Single API call, SQL aggregation, uses all performance indexes
     */
    public function getStats(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Parse date range from request
            $dateRange = $this->parseDateRange($request);
            $fromDate = $dateRange['from'];
            $toDate = $dateRange['to'];
            
            // Get accessible branch IDs for user
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            // If user selected specific branch in filter, use only that branch
            if ($request->has('branch_id') && $request->branch_id) {
                if ($accessibleBranchIds === 'all' || in_array($request->branch_id, $accessibleBranchIds)) {
                    $accessibleBranchIds = [$request->branch_id];
                }
            }
            
            // OPTIMIZATION: Get ALL stats with SQL aggregation - NO LOOPS!
            $stats = [
                'period' => [
                    'type' => $request->get('period', 'today'),
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'label' => $this->getPeriodLabel($request->get('period', 'today'), $fromDate, $toDate)
                ],
                'overview' => $this->getOverviewStats($accessibleBranchIds),
                'attendance' => $this->getAttendanceStats($accessibleBranchIds, $fromDate, $toDate),
                'fees' => $this->getFeesStats($accessibleBranchIds, $fromDate, $toDate),
                'fees_by_class' => $this->getFeesByGradeSection($accessibleBranchIds),
                'trends' => $this->getTrendData($accessibleBranchIds, $fromDate, $toDate),
                'quick_stats' => $this->getQuickStats($accessibleBranchIds, $fromDate, $toDate)
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Stats Error: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard statistics',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
    
    /**
     * Parse date range from request parameters
     */
    private function parseDateRange(Request $request): array
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
    
    /**
     * Get human-readable period label
     */
    private function getPeriodLabel(string $period, string $from, string $to): string
    {
        switch ($period) {
            case 'today':
                return 'Today - ' . Carbon::parse($from)->format('M d, Y');
            case 'week':
                return 'This Week - ' . Carbon::parse($from)->format('M d') . ' to ' . Carbon::parse($to)->format('M d, Y');
            case 'month':
                return Carbon::parse($from)->format('F Y');
            case 'custom':
                return Carbon::parse($from)->format('M d') . ' to ' . Carbon::parse($to)->format('M d, Y');
            default:
                return 'Today';
        }
    }
    
    /**
     * Get overview statistics - OPTIMIZED with single queries
     */
    private function getOverviewStats($branchIds): array
    {
        $studentQuery = DB::table('students')->where('student_status', 'Active');
        $teacherQuery = DB::table('teachers')->where('teacher_status', 'Active');
        
        if ($branchIds !== 'all' && !empty($branchIds)) {
            $studentQuery->whereIn('branch_id', $branchIds);
            $teacherQuery->whereIn('branch_id', $branchIds);
        }

        return [
            'total_students' => $studentQuery->count(),
            'total_teachers' => $teacherQuery->count(),
            'total_branches' => $branchIds === 'all' 
                ? DB::table('branches')->where('is_active', true)->count() 
                : count($branchIds)
        ];
    }
    
    /**
     * Get attendance statistics - OPTIMIZED with SQL aggregation
     */
    private function getAttendanceStats($branchIds, $fromDate, $toDate): array
    {
        // STUDENT ATTENDANCE - Single aggregated query
        $studentAttQuery = DB::table('student_attendance')
            ->whereBetween('date', [$fromDate, $toDate]);
        
        if ($branchIds !== 'all' && !empty($branchIds)) {
            $studentAttQuery->whereIn('branch_id', $branchIds);
        }
        
        $studentStats = $studentAttQuery->select(
            DB::raw('COUNT(*) as total_records'),
            DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
            DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
            DB::raw('SUM(CASE WHEN status = "Late" THEN 1 ELSE 0 END) as late'),
            DB::raw('SUM(CASE WHEN status IN ("Sick Leave", "Leave") THEN 1 ELSE 0 END) as leaves')
        )->first();
        
        // TEACHER ATTENDANCE - Single aggregated query
        $teacherAttQuery = DB::table('teacher_attendance')
            ->whereBetween('date', [$fromDate, $toDate]);
        
        if ($branchIds !== 'all' && !empty($branchIds)) {
            $teacherAttQuery->whereIn('branch_id', $branchIds);
        }
        
        $teacherStats = $teacherAttQuery->select(
            DB::raw('COUNT(*) as total_records'),
            DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present'),
            DB::raw('SUM(CASE WHEN status = "Absent" THEN 1 ELSE 0 END) as absent'),
            DB::raw('SUM(CASE WHEN status IN ("Leave", "Half-Day") THEN 1 ELSE 0 END) as leaves')
        )->first();
        
        $studentTotal = $studentStats->total_records ?? 0;
        $teacherTotal = $teacherStats->total_records ?? 0;
        
        return [
            'students' => [
                'total_records' => (int) $studentTotal,
                'present' => (int) ($studentStats->present ?? 0),
                'absent' => (int) ($studentStats->absent ?? 0),
                'late' => (int) ($studentStats->late ?? 0),
                'leaves' => (int) ($studentStats->leaves ?? 0),
                'rate' => $studentTotal > 0 
                    ? round((($studentStats->present ?? 0) / $studentTotal) * 100, 2) 
                    : 0
            ],
            'teachers' => [
                'total_records' => (int) $teacherTotal,
                'present' => (int) ($teacherStats->present ?? 0),
                'absent' => (int) ($teacherStats->absent ?? 0),
                'leaves' => (int) ($teacherStats->leaves ?? 0),
                'rate' => $teacherTotal > 0 
                    ? round((($teacherStats->present ?? 0) / $teacherTotal) * 100, 2) 
                    : 0
            ]
        ];
    }
    
    /**
     * Get fees statistics - OPTIMIZED with SQL aggregation
     */
    private function getFeesStats($branchIds, $fromDate, $toDate): array
    {
        try {
            if (!Schema::hasTable('fee_payments')) {
                return ['total_collected' => 0, 'total_pending' => 0, 'total_overdue' => 0, 'collection_rate' => 0];
            }
            
            // FIXED: Check both payment_date and created_at for flexibility
            // If period is 'today', show all fees regardless of date
            // Otherwise filter by payment_date OR created_at
            $feesQuery = DB::table('fee_payments');
            
            // For 'today', 'week', 'month' - filter by payment_date if exists, else created_at
            $period = request()->get('period', 'today');
            
            if ($period !== 'today') {
                $feesQuery->where(function($q) use ($fromDate, $toDate) {
                    $q->whereBetween('payment_date', [$fromDate, $toDate])
                      ->orWhereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate]);
                });
            } else {
                // For 'today', show ALL fees but mark recent ones
                // This gives better overview of total pending/overdue
            }
            
            if ($branchIds !== 'all' && !empty($branchIds)) {
                $feesQuery->whereIn('branch_id', $branchIds);
            }
            
            // FIXED: Use correct column names (amount_paid, payment_status)
            $feesStats = $feesQuery->select(
                DB::raw('SUM(CASE WHEN payment_status = "Completed" THEN amount_paid ELSE 0 END) as collected'),
                DB::raw('SUM(CASE WHEN payment_status = "Pending" THEN total_amount ELSE 0 END) as pending'),
                DB::raw('SUM(CASE WHEN payment_status = "Failed" THEN total_amount ELSE 0 END) as overdue'),
                DB::raw('SUM(total_amount) as total'),
                DB::raw('COUNT(*) as total_transactions')
            )->first();
            
            $total = $feesStats->total ?? 0;
            $collected = $feesStats->collected ?? 0;
            
            return [
                'total_collected' => (float) $collected,
                'total_pending' => (float) ($feesStats->pending ?? 0),
                'total_overdue' => (float) ($feesStats->overdue ?? 0),
                'total_transactions' => (int) ($feesStats->total_transactions ?? 0),
                'collection_rate' => $total > 0 ? round(($collected / $total) * 100, 2) : 0
            ];
        } catch (\Exception $e) {
            Log::error('Fee stats error', ['error' => $e->getMessage()]);
            return ['total_collected' => 0, 'total_pending' => 0, 'total_overdue' => 0, 'collection_rate' => 0];
        }
    }
    
    /**
     * Get trend data for charts - Grade/Section-wise attendance breakdown
     * Shows ALL grade/section combinations with proper grade labels
     */
    private function getTrendData($branchIds, $fromDate, $toDate): array
    {
        try {
            // First, get ALL unique grade/section combinations from students with proper grade labels
            $allGradeSectionsQuery = DB::table('students as s')
                ->leftJoin('grades as g', 's.grade', '=', 'g.value')
                ->where('s.student_status', 'Active');
            
            if ($branchIds !== 'all' && !empty($branchIds)) {
                $allGradeSectionsQuery->whereIn('s.branch_id', $branchIds);
            }
            
            $allGradeSections = $allGradeSectionsQuery->select(
                's.grade as grade_value',
                DB::raw('COALESCE(g.label, CONCAT("Grade ", s.grade), "Unassigned") as grade_label'),
                DB::raw('COALESCE(s.section, "N/A") as section_value'),
                DB::raw('CASE 
                    WHEN s.section IS NOT NULL AND s.section != "" THEN CONCAT("Section ", s.section)
                    ELSE "N/A"
                END as section_label')
            )
            ->groupBy('s.grade', 's.section', 'g.label')
            ->orderBy('grade_value', 'asc')
            ->orderBy('section_value', 'asc')
            ->get();
            
            // Get attendance data for the date range
            $attendanceQuery = DB::table('student_attendance as sa')
                ->join('students as s', 'sa.student_id', '=', 's.id')
                ->whereBetween('sa.date', [$fromDate, $toDate]);
            
            if ($branchIds !== 'all' && !empty($branchIds)) {
                $attendanceQuery->whereIn('sa.branch_id', $branchIds);
            }
            
            $attendanceData = $attendanceQuery->select(
                's.grade as grade_value',
                DB::raw('COALESCE(s.section, "N/A") as section_value'),
                DB::raw('SUM(CASE WHEN sa.status = "Present" THEN 1 ELSE 0 END) as present'),
                DB::raw('SUM(CASE WHEN sa.status = "Absent" THEN 1 ELSE 0 END) as absent'),
                DB::raw('SUM(CASE WHEN sa.status IN ("Sick Leave", "Leave") THEN 1 ELSE 0 END) as leaves')
            )
            ->groupBy('s.grade', 's.section')
            ->get()
            ->keyBy(function($item) {
                return $item->grade_value . '|' . $item->section_value;
            });
            
            // Merge: Show all grade/sections with proper labels and their attendance (or 0 if no data)
            $result = $allGradeSections->map(function($gs) use ($attendanceData) {
                $key = $gs->grade_value . '|' . $gs->section_value;
                $attendance = $attendanceData->get($key);
                
                return [
                    'label' => $gs->grade_label . ' - ' . $gs->section_label,
                    'grade' => $gs->grade_label,
                    'section' => $gs->section_label,
                    'present' => $attendance ? (int) $attendance->present : 0,
                    'absent' => $attendance ? (int) $attendance->absent : 0,
                    'leaves' => $attendance ? (int) $attendance->leaves : 0,
                    'total' => $attendance ? ((int) $attendance->present + (int) $attendance->absent + (int) $attendance->leaves) : 0
                ];
            });
            
            return [
                'attendance' => $result->toArray()
            ];
        } catch (\Exception $e) {
            Log::error('Trend data error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return ['attendance' => []];
        }
    }
    
    /**
     * Get fee collection by grade and section - FOR STACKED BAR CHART
     */
    private function getFeesByGradeSection($branchIds): array
    {
        try {
            if (!Schema::hasTable('fee_payments') || !Schema::hasTable('students')) {
                return [];
            }
            
            // Get fee collection stats grouped by grade and section
            $query = DB::table('students as s')
                ->leftJoin('grades as g', 's.grade', '=', 'g.value')
                ->leftJoin(DB::raw('(
                    SELECT 
                        fp.student_id,
                        SUM(CASE WHEN fp.payment_status = "Completed" THEN fp.amount_paid ELSE 0 END) as paid_amount,
                        SUM(fp.total_amount) as total_amount
                    FROM fee_payments fp
                    GROUP BY fp.student_id
                ) as fp'), 's.user_id', '=', 'fp.student_id')
                ->where('s.student_status', 'Active');
            
            if ($branchIds !== 'all' && !empty($branchIds)) {
                $query->whereIn('s.branch_id', $branchIds);
            }
            
            $feeData = $query->select(
                's.grade',
                DB::raw('COALESCE(g.label, CONCAT("Grade ", s.grade)) as grade_label'),
                DB::raw('COALESCE(s.section, "N/A") as section'),
                DB::raw('COUNT(s.id) as total_students'),
                DB::raw('SUM(COALESCE(fp.paid_amount, 0)) as total_paid'),
                DB::raw('SUM(COALESCE(fp.total_amount, 0)) as total_expected'),
                DB::raw('COUNT(CASE WHEN COALESCE(fp.paid_amount, 0) > 0 THEN 1 END) as students_paid'),
                DB::raw('COUNT(CASE WHEN COALESCE(fp.paid_amount, 0) = 0 AND COALESCE(fp.total_amount, 0) > 0 THEN 1 END) as students_unpaid')
            )
            ->groupBy('s.grade', 's.section', 'g.label', 'g.order')
            ->orderBy(DB::raw('COALESCE(g.order, 999)'), 'asc')
            ->orderBy('s.section', 'asc')
            ->get();
            
            // Format data for stacked bar chart
            return $feeData->map(function($item) {
                // Ensure we have valid numbers
                $totalPaid = max(0, (float) $item->total_paid);
                $totalExpected = max(0, (float) $item->total_expected);
                
                // If no expected amount, generate default fee structure (e.g., $500 per student)
                if ($totalExpected == 0) {
                    $totalExpected = $item->total_students * 5000; // Assume $5000 per student
                }
                
                $unpaidAmount = max(0, $totalExpected - $totalPaid);
                $collectionRate = $totalExpected > 0 ? round(($totalPaid / $totalExpected) * 100, 1) : 0;
                
                return [
                    'grade' => $item->grade,
                    'grade_label' => $item->grade_label,
                    'section' => $item->section,
                    'label' => $item->grade_label . ' - ' . $item->section,
                    'total_students' => (int) $item->total_students,
                    'students_paid' => (int) $item->students_paid,
                    'students_unpaid' => (int) $item->students_unpaid,
                    'total_paid' => round($totalPaid, 2),
                    'total_unpaid' => round($unpaidAmount, 2),
                    'total_expected' => round($totalExpected, 2),
                    'collection_rate' => (float) $collectionRate,
                    'status' => $collectionRate >= 85 ? 'good' : ($collectionRate >= 70 ? 'warning' : 'critical')
                ];
            })->toArray();
            
        } catch (\Exception $e) {
            Log::error('Fee by grade/section error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get quick stats for current period - OPTIMIZED
     */
    private function getQuickStats($branchIds, $fromDate, $toDate): array
    {
        try {
            // New admissions in selected period
            $newAdmissionsQuery = DB::table('students')
                ->whereBetween('admission_date', [$fromDate, $toDate])
                ->where('student_status', 'Active');
            
            if ($branchIds !== 'all' && !empty($branchIds)) {
                $newAdmissionsQuery->whereIn('branch_id', $branchIds);
            }
            
            $newAdmissions = $newAdmissionsQuery->count();
            
            // Upcoming exams (next 7 days from today)
            $upcomingExams = 0;
            if (Schema::hasTable('exams')) {
                $upcomingExams = DB::table('exams')
                    ->where('date', '>=', Carbon::today())
                    ->where('date', '<=', Carbon::today()->addDays(7))
                    ->count();
            }
            
            // Teachers on leave today
            $teachersOnLeave = DB::table('teacher_attendance')
                ->where('date', Carbon::today())
                ->whereIn('status', ['Leave', 'Sick Leave', 'Half-Day'])
                ->count();
            
            return [
                'new_admissions' => $newAdmissions,
                'upcoming_exams' => $upcomingExams,
                'teachers_on_leave' => $teachersOnLeave,
                'period_days' => Carbon::parse($fromDate)->diffInDays(Carbon::parse($toDate)) + 1
            ];
        } catch (\Exception $e) {
            Log::error('Quick stats error', ['error' => $e->getMessage()]);
            return ['new_admissions' => 0, 'upcoming_exams' => 0, 'teachers_on_leave' => 0];
        }
    }

    /**
     * Get recent attendance data
     */
    public function getAttendance(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 5);

            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $attendanceData = [];

            switch ($user->role) {
                case 'SuperAdmin':
                case 'BranchAdmin':
                    // Get recent attendance across all students
                    $attendanceData = $this->getRecentAttendanceForAdmin($user, $limit);
                    break;
                
                case 'Teacher':
                    // Get today's attendance for teacher's classes
                    $attendanceData = $this->getTodayAttendanceForTeacher($user, $limit);
                    break;
                
                case 'Parent':
                    // Get children's attendance
                    $attendanceData = $this->getChildrenAttendance($user, $limit);
                    break;
                
                default:
                    $attendanceData = [];
            }

            return response()->json([
                'success' => true,
                'data' => $attendanceData
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Attendance Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get top performing students
     */
    public function getTopPerformers(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 5);

            // Only admin and teachers can view top performers
            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin', 'Teacher'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Check if tables exist
            if (!Schema::hasTable('exam_results')) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $topPerformers = ExamResult::select(
                    'users.id as student_id',
                    DB::raw('CONCAT(users.first_name, " ", users.last_name) as name'),
                    DB::raw('AVG(exam_results.marks) as percentage'),
                    DB::raw('CASE 
                        WHEN AVG(exam_results.marks) >= 95 THEN "A+"
                        WHEN AVG(exam_results.marks) >= 90 THEN "A"
                        WHEN AVG(exam_results.marks) >= 85 THEN "A-"
                        WHEN AVG(exam_results.marks) >= 80 THEN "B+"
                        ELSE "B"
                    END as grade'),
                    'users.avatar'
                )
                ->join('users', 'exam_results.student_id', '=', 'users.id')
                ->where('users.role', 'Student')
                ->when($user->role == 'BranchAdmin', function($query) use ($user) {
                    return $query->where('users.branch_id', $user->branch_id);
                })
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.avatar')
                ->orderByDesc('percentage')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $topPerformers
            ]);

        } catch (\Exception $e) {
            Log::error('Top Performers Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get students with low attendance
     */
    public function getLowAttendance(Request $request)
    {
        try {
            $user = Auth::user();
            $threshold = $request->get('threshold', 70);

            // Only admin and teachers can view low attendance
            if (!in_array($user->role, ['SuperAdmin', 'BranchAdmin', 'Teacher'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // Check if table exists
            if (!Schema::hasTable('attendances')) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $lowAttendance = Attendance::select(
                    'users.id as student_id',
                    DB::raw('CONCAT(users.first_name, " ", users.last_name) as name'),
                    DB::raw('(SUM(CASE WHEN attendances.status = "present" THEN 1 ELSE 0 END) / COUNT(*) * 100) as attendance'),
                    DB::raw('SUM(CASE WHEN attendances.status = "absent" THEN 1 ELSE 0 END) as daysAbsent'),
                    'users.avatar'
                )
                ->join('users', 'attendances.student_id', '=', 'users.id')
                ->where('users.role', 'Student')
                ->when($user->role == 'BranchAdmin', function($query) use ($user) {
                    return $query->where('users.branch_id', $user->branch_id);
                })
                ->groupBy('users.id', 'users.first_name', 'users.last_name', 'users.avatar')
                ->havingRaw('attendance < ?', [$threshold])
                ->orderBy('attendance', 'asc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $lowAttendance
            ]);

        } catch (\Exception $e) {
            Log::error('Low Attendance Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get upcoming exams
     */
    public function getUpcomingExams(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 5);

            // Check if Exam table exists
            if (!Schema::hasTable('exams')) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $exams = Exam::select('id', 'name as subject', 'exam_date as date', 'exam_time as time', 'exam_type as type')
                ->where('exam_date', '>=', Carbon::now())
                ->when($user->role == 'BranchAdmin', function($query) use ($user) {
                    return $query->where('branch_id', $user->branch_id);
                })
                ->when($user->role == 'Student', function($query) use ($user) {
                    // Filter exams for student's class
                    if (isset($user->class_id)) {
                        return $query->where('class_id', $user->class_id);
                    }
                    return $query;
                })
                ->orderBy('exam_date', 'asc')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $exams
            ]);

        } catch (\Exception $e) {
            Log::error('Upcoming Exams Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get student results
     */
    public function getStudentResults(Request $request)
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 5);
            $studentId = $request->get('student_id');

            // Students can only view their own results
            if ($user->role == 'Student') {
                $studentId = $user->id;
            }

            // Parents can only view their children's results
            if ($user->role == 'Parent' && $studentId) {
                // Verify the student belongs to this parent
                $student = User::where('id', $studentId)
                    ->where('parent_id', $user->id)
                    ->first();
                
                if (!$student) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Access denied'
                    ], 403);
                }
            }

            // Check if tables exist
            if (!Schema::hasTable('exam_results')) {
                return response()->json([
                    'success' => true,
                    'data' => []
                ]);
            }

            $results = ExamResult::select(
                    'exam_results.id',
                    'subjects.name as subject',
                    'exam_results.marks as percentage',
                    DB::raw('CASE 
                        WHEN exam_results.marks >= 95 THEN "A+"
                        WHEN exam_results.marks >= 90 THEN "A"
                        WHEN exam_results.marks >= 85 THEN "A-"
                        WHEN exam_results.marks >= 80 THEN "B+"
                        WHEN exam_results.marks >= 75 THEN "B"
                        ELSE "C"
                    END as grade'),
                    'exams.exam_date as date',
                    DB::raw('CASE 
                        WHEN exam_results.marks >= 90 THEN "Excellent"
                        WHEN exam_results.marks >= 80 THEN "Very Good"
                        WHEN exam_results.marks >= 70 THEN "Good"
                        ELSE "Fair"
                    END as remarks')
                )
                ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
                ->join('subjects', 'exams.subject_id', '=', 'subjects.id')
                ->where('exam_results.student_id', $studentId ?? $user->id)
                ->orderByDesc('exams.exam_date')
                ->limit($limit)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $results
            ]);

        } catch (\Exception $e) {
            Log::error('Student Results Error: ' . $e->getMessage());
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }
    }

    /**
     * Get children performance for parents
     */
    public function getChildrenPerformance(Request $request, $parentId)
    {
        try {
            $user = Auth::user();

            // Only parents can access this and only for their own children
            if ($user->role != 'Parent' || $user->id != $parentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied'
                ], 403);
            }

            // FIXED: Load all children at once - NO N+1!
            $childrenIds = User::where('parent_id', $parentId)
                ->where('role', 'Student')
                ->pluck('id');

            if ($childrenIds->isEmpty()) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'attendance' => [],
                        'results' => []
                    ]
                ]);
            }

            // OPTIMIZED: Batch calculate attendance for all children at once
            $attendanceData = $this->batchCalculateAttendancePercentage($childrenIds->toArray());
            
            // FIXED: Load all results in single query instead of in loop
            $results = ExamResult::select(
                    'exam_results.student_id',
                    'subjects.name as subject',
                    'exam_results.marks as percentage',
                    DB::raw('CASE 
                        WHEN exam_results.marks >= 95 THEN "A+"
                        WHEN exam_results.marks >= 90 THEN "A"
                        ELSE "B"
                    END as grade'),
                    'exams.exam_date as date'
                )
                ->join('exams', 'exam_results.exam_id', '=', 'exams.id')
                ->join('subjects', 'exams.subject_id', '=', 'subjects.id')
                ->join('users', 'exam_results.student_id', '=', 'users.id')
                ->whereIn('exam_results.student_id', $childrenIds)
                ->orderByDesc('exams.exam_date')
                ->limit($childrenIds->count() * 5) // Get top 5 for each child
                ->get()
                ->groupBy('student_id');

            $data = [
                'attendance' => [],
                'results' => []
            ];

            foreach ($childrenIds as $childId) {
                $child = User::find($childId);
                if (!$child) continue;

                // Get attendance from batch calculation
                $attendancePercentage = $attendanceData[$childId] ?? 0;
                
                $data['attendance'][] = [
                    'id' => $child->id,
                    'name' => $child->first_name . ' ' . $child->last_name,
                    'attendance' => $attendancePercentage,
                    'avatar' => $child->avatar
                ];

                // Get recent results (already loaded in batch)
                $childResults = $results->get($childId, collect());
                
                foreach ($childResults->take(5) as $result) {
                    $result->studentName = $child->first_name . ' ' . $child->last_name;
                    $data['results'][] = $result;
                }
            }

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            Log::error('Children Performance Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading children performance'
            ], 500);
        }
    }

    // Private helper methods

    private function getAdminStats($user)
    {
        try {
            $query = User::query();
            
            if ($user->role == 'BranchAdmin') {
                $query->where('branch_id', $user->branch_id);
            }

            $totalMoney = 0;
            try {
                $totalMoney = FeePayment::when($user->role == 'BranchAdmin', function($q) use ($user) {
                    return $q->where('branch_id', $user->branch_id);
                })->sum('amount') ?? 0;
            } catch (\Exception $e) {
                // FeePayment table might not exist or have data
                $totalMoney = 0;
            }

            return [
                'students' => $query->clone()->where('role', 'Student')->count(),
                'teachers' => $query->clone()->where('role', 'Teacher')->count(),
                'parents' => $query->clone()->where('role', 'Parent')->count(),
                'totalMoney' => $totalMoney
            ];
        } catch (\Exception $e) {
            Log::error('Admin stats error', ['error' => $e->getMessage()]);
            return [
                'students' => 0,
                'teachers' => 0,
                'parents' => 0,
                'totalMoney' => 0
            ];
        }
    }

    private function getTeacherStats($user)
    {
        try {
            $examsCount = 0;
            $eventsCount = 0;
            
            try {
                $examsCount = Exam::where('created_by', $user->id)
                    ->where('exam_date', '>=', Carbon::now())
                    ->count();
            } catch (\Exception $e) {
                // Exam table might not exist
            }
            
            try {
                $eventsCount = Event::where('event_date', '>=', Carbon::now())->count();
            } catch (\Exception $e) {
                // Event table might not exist
            }

            return [
                'students' => 150, // Implement based on teacher's classes
                'attendance' => 95, // Today's attendance percentage
                'exams' => $examsCount,
                'events' => $eventsCount
            ];
        } catch (\Exception $e) {
            Log::error('Teacher stats error', ['error' => $e->getMessage()]);
            return [
                'students' => 0,
                'attendance' => 0,
                'exams' => 0,
                'events' => 0
            ];
        }
    }

    private function getStudentStats($user)
    {
        try {
            // Optimized: Calculate attendance once
            $attendancePercentage = $this->calculateAttendancePercentage($user->id);
            
            $examsCount = 0;
            $eventsCount = 0;
            $pendingFees = 0;
            
            // OPTIMIZED: Single batch query for all stats if possible
            if (Schema::hasTable('exams')) {
                $examsCount = Exam::where('exam_date', '>=', Carbon::now())->count();
            }
            
            if (Schema::hasTable('events')) {
                $eventsCount = Event::where('event_date', '>=', Carbon::now())->count();
            }
            
            if (Schema::hasTable('fee_payments')) {
                $pendingFees = DB::table('fee_payments')
                    ->where('student_id', $user->id)
                    ->where('payment_status', 'Pending')
                    ->sum('amount_paid') ?? 0;
            }

            return [
                'attendance' => $attendancePercentage,
                'exams' => $examsCount,
                'events' => $eventsCount,
                'pendingFees' => $pendingFees
            ];
        } catch (\Exception $e) {
            Log::error('Student stats error', ['error' => $e->getMessage()]);
            return [
                'attendance' => 0,
                'exams' => 0,
                'events' => 0,
                'pendingFees' => 0
            ];
        }
    }

    private function getParentStats($user)
    {
        try {
            // FIXED: Load children once, get IDs
            $childrenIds = User::where('parent_id', $user->id)
                ->where('role', 'Student')
                ->pluck('id');
            
            if ($childrenIds->isEmpty()) {
                return [
                    'students' => 0,
                    'attendance' => 0,
                    'pendingFees' => 0,
                    'events' => 0
                ];
            }

            // OPTIMIZED: Batch calculate attendance for all children at once
            $attendanceData = $this->batchCalculateAttendancePercentage($childrenIds->toArray());
            $totalAttendance = array_sum($attendanceData);
            $avgAttendance = $childrenIds->count() > 0 ? $totalAttendance / $childrenIds->count() : 0;
            
            // OPTIMIZED: Single query for all pending fees
            $totalPendingFees = 0;
            try {
                if (Schema::hasTable('fee_payments')) {
                    $totalPendingFees = DB::table('fee_payments')
                        ->whereIn('student_id', $childrenIds)
                        ->where('payment_status', 'Pending')
                        ->sum('amount_paid') ?? 0;
                }
            } catch (\Exception $e) {
                // FeePayment might not exist
            }

            $eventsCount = 0;
            try {
                if (Schema::hasTable('events')) {
                    $eventsCount = Event::where('event_date', '>=', Carbon::now())->count();
                }
            } catch (\Exception $e) {
                // Event table might not exist
            }

            return [
                'students' => $childrenIds->count(),
                'attendance' => round($avgAttendance),
                'pendingFees' => $totalPendingFees,
                'events' => $eventsCount
            ];
        } catch (\Exception $e) {
            Log::error('Parent stats error', ['error' => $e->getMessage()]);
            return [
                'students' => 0,
                'attendance' => 0,
                'pendingFees' => 0,
                'events' => 0
            ];
        }
    }

    private function getStaffStats($user)
    {
        try {
            $pendingFees = 0;
            $eventsCount = 0;
            
            try {
                $pendingFees = FeePayment::where('status', 'pending')->sum('amount') ?? 0;
            } catch (\Exception $e) {
                // FeePayment might not exist
            }
            
            try {
                $eventsCount = Event::where('event_date', '>=', Carbon::now())->count();
            } catch (\Exception $e) {
                // Event table might not exist
            }

            return [
                'students' => User::where('role', 'Student')->count(),
                'pendingFees' => $pendingFees,
                'events' => $eventsCount
            ];
        } catch (\Exception $e) {
            Log::error('Staff stats error', ['error' => $e->getMessage()]);
            return [
                'students' => 0,
                'pendingFees' => 0,
                'events' => 0
            ];
        }
    }

    /**
     * Calculate attendance percentage for a single student
     */
    private function calculateAttendancePercentage($studentId)
    {
        try {
            $totalDays = Attendance::where('student_id', $studentId)->count();
            if ($totalDays == 0) return 0;

            $presentDays = Attendance::where('student_id', $studentId)->where('status', 'present')->count();
            return round(($presentDays / $totalDays) * 100);
        } catch (\Exception $e) {
            // Attendance table might not exist
            return 0;
        }
    }

    /**
     * BATCH calculate attendance for multiple students - OPTIMIZED
     * Fixes N+1 query problem by calculating all at once
     */
    private function batchCalculateAttendancePercentage(array $studentIds): array
    {
        try {
            if (empty($studentIds)) {
                return [];
            }

            // Check if attendance table exists
            if (!Schema::hasTable('attendances') && !Schema::hasTable('student_attendance')) {
                return array_fill_keys($studentIds, 0);
            }

            // Use student_attendance table if it exists
            if (Schema::hasTable('student_attendance')) {
                $attendanceData = DB::table('student_attendance')
                    ->select(
                        'student_id',
                        DB::raw('COUNT(*) as total_days'),
                        DB::raw('SUM(CASE WHEN status = "Present" THEN 1 ELSE 0 END) as present_days')
                    )
                    ->whereIn('student_id', $studentIds)
                    ->groupBy('student_id')
                    ->get()
                    ->keyBy('student_id');

                $result = [];
                foreach ($studentIds as $id) {
                    $data = $attendanceData->get($id);
                    if ($data && $data->total_days > 0) {
                        $result[$id] = round(($data->present_days / $data->total_days) * 100);
                    } else {
                        $result[$id] = 0;
                    }
                }
                
                return $result;
            }

            // Fallback to attendances table (old schema)
            $attendanceData = DB::table('attendances')
                ->select(
                    'student_id',
                    DB::raw('COUNT(*) as total_days'),
                    DB::raw('SUM(CASE WHEN status = "present" THEN 1 ELSE 0 END) as present_days')
                )
                ->whereIn('student_id', $studentIds)
                ->groupBy('student_id')
                ->get()
                ->keyBy('student_id');

            $result = [];
            foreach ($studentIds as $id) {
                $data = $attendanceData->get($id);
                if ($data && $data->total_days > 0) {
                    $result[$id] = round(($data->present_days / $data->total_days) * 100);
                } else {
                    $result[$id] = 0;
                }
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('Batch attendance calculation error', ['error' => $e->getMessage()]);
            return array_fill_keys($studentIds, 0);
        }
    }

    private function getRecentAttendanceForAdmin($user, $limit)
    {
        try {
            // Implementation for admin attendance data
            return Attendance::with('student')
                ->when($user->role == 'BranchAdmin', function($q) use ($user) {
                    return $q->whereHas('student', function($q2) use ($user) {
                        $q2->where('branch_id', $user->branch_id);
                    });
                })
                ->orderBy('date', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getTodayAttendanceForTeacher($user, $limit)
    {
        try {
            // Implementation for teacher's today attendance
            return Attendance::with('student')
                ->whereDate('date', Carbon::today())
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getChildrenAttendance($user, $limit)
    {
        try {
            $children = User::where('parent_id', $user->id)->where('role', 'Student')->pluck('id');
            
            return Attendance::with('student')
                ->whereIn('student_id', $children)
                ->orderBy('date', 'desc')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return [];
        }
    }
}

