<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    /**
     * Get attendance records
     */
    public function index(Request $request)
    {
        try {
            $type = $request->get('type', 'student'); // student or teacher
            
            if ($type === 'student') {
                $query = DB::table('student_attendance')
                    ->join('students', 'student_attendance.student_id', '=', 'students.user_id')
                    ->join('users', 'students.user_id', '=', 'users.id')
                    ->select(
                        'student_attendance.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'students.admission_number',
                        'students.grade',
                        'students.section'
                    );
            } else {
                $query = DB::table('teacher_attendance')
                    ->join('teachers', 'teacher_attendance.teacher_id', '=', 'teachers.user_id')
                    ->join('users', 'teachers.user_id', '=', 'users.id')
                    ->select(
                        'teacher_attendance.*',
                        'users.first_name',
                        'users.last_name',
                        'users.email',
                        'teachers.employee_id'
                    );
            }

            // Filters
            if ($request->has('branch_id')) {
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

            $attendance = $query->orderBy($type . '_attendance.date', 'desc')
                               ->paginate($request->get('per_page', 50));

            return response()->json([
                'success' => true,
                'data' => $attendance->items(),
                'meta' => [
                    'current_page' => $attendance->currentPage(),
                    'per_page' => $attendance->perPage(),
                    'total' => $attendance->total(),
                    'last_page' => $attendance->lastPage()
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
            
            if ($type === 'student') {
                $validator = Validator::make($request->all(), [
                    'student_id' => 'required|exists:users,id',
                    'branch_id' => 'required|exists:branches,id',
                    'grade_level' => 'required|string',
                    'section' => 'required|string',
                    'date' => 'required|date',
                    'status' => 'required|in:Present,Absent,Late,Half-Day,Sick Leave,Leave',
                    'academic_year' => 'required|string',
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

                DB::table('student_attendance')->insert([
                    'student_id' => $request->student_id,
                    'branch_id' => $request->branch_id,
                    'grade_level' => $request->grade_level,
                    'section' => $request->section,
                    'date' => $request->date,
                    'status' => $request->status,
                    'remarks' => $request->remarks,
                    'marked_by' => auth()->user()->email ?? null,
                    'academic_year' => $request->academic_year,
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

                DB::table('teacher_attendance')->insert([
                    'teacher_id' => $request->teacher_id,
                    'branch_id' => $request->branch_id,
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
                                'grade_level' => $item['grade_level'],
                                'section' => $item['section'],
                                'status' => $item['status'],
                                'remarks' => $item['remarks'] ?? null,
                                'marked_by' => auth()->user()->email ?? null,
                                'academic_year' => $request->academic_year ?? date('Y') . '-' . (date('Y') + 1),
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

            return response()->json([
                'success' => true,
                'message' => "Bulk attendance marked successfully",
                'marked' => $marked,
                'errors' => $errors
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
     * Get student attendance
     */
    public function getStudentAttendance($studentId)
    {
        try {
            // Apply date filters if provided
            $query = DB::table('student_attendance')
                ->where('student_id', $studentId);
            
            if (request()->has('from_date')) {
                $query->whereDate('date', '>=', request('from_date'));
            }
            
            if (request()->has('to_date')) {
                $query->whereDate('date', '<=', request('to_date'));
            }
            
            $attendance = $query->orderBy('date', 'desc')->get();

            $summary = [
                'total_days' => $attendance->count(),
                'present' => $attendance->where('status', 'Present')->count(),
                'absent' => $attendance->where('status', 'Absent')->count(),
                'late' => $attendance->where('status', 'Late')->count(),
                'leaves' => $attendance->whereIn('status', ['Sick Leave', 'Leave'])->count(),
                'half_day' => $attendance->where('status', 'Half-Day')->count(),
                'percentage' => $attendance->count() > 0 
                    ? round(($attendance->where('status', 'Present')->count() / $attendance->count()) * 100, 2)
                    : 0
            ];

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'summary' => $summary
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
                ->whereDate('student_attendance.date', $date)
                ->select(
                    'student_attendance.*',
                    'users.first_name',
                    'users.last_name',
                    'students.admission_number',
                    'students.roll_number'
                )
                ->orderBy('students.roll_number')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attendance,
                'meta' => [
                    'grade' => $grade,
                    'section' => $section,
                    'date' => $date,
                    'total' => $attendance->count(),
                    'present' => $attendance->where('status', 'Present')->count(),
                    'absent' => $attendance->where('status', 'Absent')->count()
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
                ->whereBetween('date', [$request->from_date, $request->to_date]);

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

            $summary = [
                'total_records' => $records->count(),
                'present' => $records->where('status', 'Present')->count(),
                'absent' => $records->where('status', 'Absent')->count(),
                'late' => $records->where('status', 'Late')->count(),
                'percentage' => $records->count() > 0
                    ? round(($records->where('status', 'Present')->count() / $records->count()) * 100, 2)
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

            $attendance = DB::table('student_attendance')
                ->where('student_id', $studentId)
                ->whereBetween('date', [$from_date, $to_date])
                ->orderBy('date', 'desc')
                ->get();

            $summary = [
                'total_days' => $attendance->count(),
                'present' => $attendance->where('status', 'Present')->count(),
                'absent' => $attendance->where('status', 'Absent')->count(),
                'late' => $attendance->where('status', 'Late')->count(),
                'leaves' => $attendance->whereIn('status', ['Sick Leave', 'Leave'])->count(),
                'percentage' => $attendance->count() > 0
                    ? round(($attendance->where('status', 'Present')->count() / $attendance->count()) * 100, 2)
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
        return $this->getStudentAttendance($id);
    }

    public function update(Request $request, $id) {
        return response()->json(['message' => 'Use markBulk or store methods'], 400);
    }

    public function destroy($id) {
        return response()->json(['message' => 'Attendance deletion not allowed'], 400);
    }
}
