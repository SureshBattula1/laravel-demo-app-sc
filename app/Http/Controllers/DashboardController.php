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
     * Get dashboard statistics based on user role
     */
    public function getStats(Request $request)
    {
        try {
            $user = Auth::user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            $stats = [];

            switch ($user->role) {
                case 'SuperAdmin':
                case 'BranchAdmin':
                    $stats = $this->getAdminStats($user);
                    break;
                
                case 'Teacher':
                    $stats = $this->getTeacherStats($user);
                    break;
                
                case 'Student':
                    $stats = $this->getStudentStats($user);
                    break;
                
                case 'Parent':
                    $stats = $this->getParentStats($user);
                    break;
                
                case 'Staff':
                    $stats = $this->getStaffStats($user);
                    break;
                
                default:
                    $stats = [];
            }

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Dashboard Stats Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error loading dashboard statistics'
            ], 500);
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

            $children = User::where('parent_id', $parentId)
                ->where('role', 'Student')
                ->get();

            $data = [
                'attendance' => [],
                'results' => []
            ];

            foreach ($children as $child) {
                // Get attendance
                $attendancePercentage = $this->calculateAttendancePercentage($child->id);
                $data['attendance'][] = [
                    'id' => $child->id,
                    'name' => $child->first_name . ' ' . $child->last_name,
                    'attendance' => $attendancePercentage,
                    'avatar' => $child->avatar
                ];

                // Get recent results
                $results = ExamResult::select(
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
                    ->where('exam_results.student_id', $child->id)
                    ->orderByDesc('exams.exam_date')
                    ->limit(5)
                    ->get();

                foreach ($results as $result) {
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
            $attendancePercentage = $this->calculateAttendancePercentage($user->id);
            
            $examsCount = 0;
            $eventsCount = 0;
            $pendingFees = 0;
            
            try {
                $examsCount = Exam::where('exam_date', '>=', Carbon::now())->count();
            } catch (\Exception $e) {
                // Exam table might not exist
            }
            
            try {
                $eventsCount = Event::where('event_date', '>=', Carbon::now())->count();
            } catch (\Exception $e) {
                // Event table might not exist
            }
            
            try {
                $pendingFees = FeePayment::where('student_id', $user->id)
                    ->where('status', 'pending')
                    ->sum('amount') ?? 0;
            } catch (\Exception $e) {
                // FeePayment table might not exist
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
            $children = User::where('parent_id', $user->id)->where('role', 'Student')->get();
            $totalAttendance = 0;
            $totalPendingFees = 0;

            foreach ($children as $child) {
                $totalAttendance += $this->calculateAttendancePercentage($child->id);
                
                try {
                    $totalPendingFees += FeePayment::where('student_id', $child->id)
                        ->where('status', 'pending')
                        ->sum('amount') ?? 0;
                } catch (\Exception $e) {
                    // FeePayment might not exist
                }
            }

            $avgAttendance = $children->count() > 0 ? $totalAttendance / $children->count() : 0;
            
            $eventsCount = 0;
            try {
                $eventsCount = Event::where('event_date', '>=', Carbon::now())->count();
            } catch (\Exception $e) {
                // Event table might not exist
            }

            return [
                'students' => $children->count(),
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

