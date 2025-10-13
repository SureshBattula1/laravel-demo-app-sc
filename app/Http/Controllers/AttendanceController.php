<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Attendance::with(['user', 'branch', 'markedBy']);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            if ($request->has('date')) {
                $query->whereDate('attendance_date', $request->date);
            }

            if ($request->has('from_date')) {
                $query->whereDate('attendance_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->whereDate('attendance_date', '<=', $request->to_date);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $attendance = $query->orderBy('attendance_date', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'message' => 'Attendance records retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|uuid|exists:branches,id',
                'user_id' => 'required|uuid|exists:users,id',
                'user_type' => 'required|string|in:Student,Teacher,Staff',
                'attendance_date' => 'required|date',
                'status' => 'required|string|in:Present,Absent,Late,Excused,On Leave',
                'check_in_time' => 'nullable|date',
                'check_out_time' => 'nullable|date|after:check_in_time',
                'remarks' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check if attendance already exists for this user on this date
            $existing = Attendance::where('user_id', $request->user_id)
                ->whereDate('attendance_date', $request->attendance_date)
                ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attendance already marked for this user on this date'
                ], 422);
            }

            $attendance = Attendance::create([
                ...$request->all(),
                'marked_by' => $request->user()->id,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $attendance->load(['user', 'branch', 'markedBy']),
                'message' => 'Attendance marked successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function markBulk(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|uuid|exists:branches,id',
                'attendance_date' => 'required|date',
                'user_type' => 'required|string|in:Student,Teacher,Staff',
                'attendances' => 'required|array',
                'attendances.*.user_id' => 'required|uuid|exists:users,id',
                'attendances.*.status' => 'required|string|in:Present,Absent,Late,Excused,On Leave',
                'attendances.*.remarks' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $created = [];
            foreach ($request->attendances as $item) {
                // Check if already exists
                $existing = Attendance::where('user_id', $item['user_id'])
                    ->whereDate('attendance_date', $request->attendance_date)
                    ->first();

                if (!$existing) {
                    $attendance = Attendance::create([
                        'branch_id' => $request->branch_id,
                        'user_id' => $item['user_id'],
                        'user_type' => $request->user_type,
                        'attendance_date' => $request->attendance_date,
                        'status' => $item['status'],
                        'remarks' => $item['remarks'] ?? null,
                        'marked_by' => $request->user()->id,
                        'created_by' => $request->user()->id
                    ]);
                    $created[] = $attendance;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $created,
                'message' => count($created) . ' attendance records marked successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error marking bulk attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error marking bulk attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getReport(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'from_date' => 'required|date',
                'to_date' => 'required|date|after_or_equal:from_date',
                'user_type' => 'nullable|string|in:Student,Teacher,Staff',
                'branch_id' => 'nullable|uuid|exists:branches,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = Attendance::with(['user', 'branch'])
                ->whereBetween('attendance_date', [$request->from_date, $request->to_date]);

            if ($request->has('user_type')) {
                $query->where('user_type', $request->user_type);
            }

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            $records = $query->get();

            // Calculate statistics
            $stats = [
                'total_records' => $records->count(),
                'present' => $records->where('status', 'Present')->count(),
                'absent' => $records->where('status', 'Absent')->count(),
                'late' => $records->where('status', 'Late')->count(),
                'excused' => $records->where('status', 'Excused')->count(),
                'on_leave' => $records->where('status', 'On Leave')->count(),
                'attendance_percentage' => $records->count() > 0 
                    ? round(($records->whereIn('status', ['Present', 'Late'])->count() / $records->count()) * 100, 2) 
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $records,
                    'statistics' => $stats
                ],
                'message' => 'Attendance report generated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error generating attendance report: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error generating attendance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $attendance = Attendance::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'string|in:Present,Absent,Late,Excused,On Leave',
                'check_in_time' => 'nullable|date',
                'check_out_time' => 'nullable|date|after:check_in_time',
                'remarks' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $attendance->update([
                ...$request->all(),
                'updated_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $attendance->load(['user', 'branch', 'markedBy']),
                'message' => 'Attendance updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $attendance = Attendance::findOrFail($id);
            $attendance->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Attendance record deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting attendance',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(string $id)
    {
        try {
            $attendance = Attendance::with(['user', 'branch', 'markedBy', 'creator', 'updater'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'message' => 'Attendance record retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching attendance: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Attendance record not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Get attendance for a specific student
     */
    public function getStudentAttendance(string $studentId)
    {
        try {
            $attendance = Attendance::with(['branch', 'markedBy'])
                ->where('user_id', $studentId)
                ->where('user_type', 'Student')
                ->orderBy('attendance_date', 'desc')
                ->get();

            // Calculate statistics
            $stats = [
                'total_days' => $attendance->count(),
                'present' => $attendance->where('status', 'Present')->count(),
                'absent' => $attendance->where('status', 'Absent')->count(),
                'late' => $attendance->where('status', 'Late')->count(),
                'excused' => $attendance->where('status', 'Excused')->count(),
                'on_leave' => $attendance->where('status', 'On Leave')->count(),
                'attendance_percentage' => $attendance->count() > 0 
                    ? round(($attendance->whereIn('status', ['Present', 'Late'])->count() / $attendance->count()) * 100, 2) 
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'records' => $attendance,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get student attendance error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get attendance for a specific class
     */
    public function getClassAttendance(string $grade, string $section)
    {
        try {
            $date = request()->get('date', Carbon::today()->toDateString());
            
            // Get students of this class (you may need to adjust based on your student model structure)
            $students = \App\Models\User::where('role', 'Student')
                ->where('grade', $grade)
                ->where('section', $section)
                ->get();

            $attendanceRecords = Attendance::with(['user', 'markedBy'])
                ->whereIn('user_id', $students->pluck('id'))
                ->whereDate('attendance_date', $date)
                ->get()
                ->keyBy('user_id');

            // Map students with their attendance
            $classAttendance = $students->map(function($student) use ($attendanceRecords) {
                return [
                    'student' => $student,
                    'attendance' => $attendanceRecords->get($student->id)
                ];
            });

            $stats = [
                'total_students' => $students->count(),
                'marked' => $attendanceRecords->count(),
                'unmarked' => $students->count() - $attendanceRecords->count(),
                'present' => $attendanceRecords->where('status', 'Present')->count(),
                'absent' => $attendanceRecords->where('status', 'Absent')->count(),
                'late' => $attendanceRecords->where('status', 'Late')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'grade' => $grade,
                    'section' => $section,
                    'date' => $date,
                    'attendance' => $classAttendance,
                    'statistics' => $stats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get class attendance error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch class attendance',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Generate detailed attendance report for a student
     */
    public function generateReport(string $studentId)
    {
        try {
            $fromDate = request()->get('from_date', Carbon::now()->subDays(30)->toDateString());
            $toDate = request()->get('to_date', Carbon::today()->toDateString());

            $attendance = Attendance::with(['branch', 'markedBy'])
                ->where('user_id', $studentId)
                ->whereBetween('attendance_date', [$fromDate, $toDate])
                ->orderBy('attendance_date', 'desc')
                ->get();

            // Monthly breakdown
            $monthlyStats = $attendance->groupBy(function($item) {
                return Carbon::parse($item->attendance_date)->format('Y-m');
            })->map(function($monthRecords) {
                return [
                    'total' => $monthRecords->count(),
                    'present' => $monthRecords->where('status', 'Present')->count(),
                    'absent' => $monthRecords->where('status', 'Absent')->count(),
                    'late' => $monthRecords->where('status', 'Late')->count(),
                    'percentage' => $monthRecords->count() > 0 
                        ? round(($monthRecords->whereIn('status', ['Present', 'Late'])->count() / $monthRecords->count()) * 100, 2)
                        : 0
                ];
            });

            $overallStats = [
                'total_days' => $attendance->count(),
                'present' => $attendance->where('status', 'Present')->count(),
                'absent' => $attendance->where('status', 'Absent')->count(),
                'late' => $attendance->where('status', 'Late')->count(),
                'excused' => $attendance->where('status', 'Excused')->count(),
                'on_leave' => $attendance->where('status', 'On Leave')->count(),
                'attendance_percentage' => $attendance->count() > 0 
                    ? round(($attendance->whereIn('status', ['Present', 'Late'])->count() / $attendance->count()) * 100, 2) 
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'student_id' => $studentId,
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'records' => $attendance,
                    'overall_statistics' => $overallStats,
                    'monthly_breakdown' => $monthlyStats
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Generate attendance report error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate attendance report',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
