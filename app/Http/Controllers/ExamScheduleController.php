<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExamScheduleController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            // Select only existing columns based on actual table structure
            $query = ExamSchedule::select([
                'id', 'exam_id', 'subject_id', 'grade', 'section',
                'exam_date', 'start_time', 'end_time', 'duration', 
                'total_marks', 'passing_marks', 'room_number', 'invigilator_id', 'created_at'
            ])->with([
                'exam:id,name,branch_id',
                'exam.branch:id,name,code',
                'subject:id,name,code'
            ]);

            // Filters
            if ($request->has('branch_id')) {
                $query->whereHas('exam', function($q) use ($request) {
                    $q->where('branch_id', $request->branch_id);
                });
            }

            if ($request->has('exam_id')) {
                $query->where('exam_id', $request->exam_id);
            }

            if ($request->has('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->has('exam_date')) {
                $query->whereDate('exam_date', $request->exam_date);
            }

            // Search filter - search across exam name, subject name, grade, room, and branch name
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('grade', 'like', $search . '%')
                      ->orWhere('section', 'like', $search . '%')
                      ->orWhere('room_number', 'like', $search . '%')
                      ->orWhereHas('exam', function($q) use ($search) {
                          $q->where('name', 'like', $search . '%')
                            ->orWhereHas('branch', function($q) use ($search) {
                                $q->where('name', 'like', $search . '%')
                                  ->orWhere('code', 'like', $search . '%');
                            });
                      })
                      ->orWhereHas('subject', function($q) use ($search) {
                          $q->where('name', 'like', $search . '%')
                            ->orWhere('code', 'like', $search . '%');
                      });
                });
            }

            $schedules = $this->paginateAndSort($query, $request, [
                'id', 'exam_date', 'start_time', 'grade', 'created_at'
            ], 'exam_date', 'asc');

            return response()->json([
                'success' => true,
                'data' => $schedules->items(),
                'meta' => [
                    'current_page' => $schedules->currentPage(),
                    'per_page' => $schedules->perPage(),
                    'total' => $schedules->total(),
                    'last_page' => $schedules->lastPage(),
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Get exam schedules error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch schedules'], 500);
        }
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'exam_id' => 'required|exists:exams,id',
            'subject_id' => 'required|exists:subjects,id',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'total_marks' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            // Prepare data properly - use only fields that exist in database
            $scheduleData = [
                'exam_id' => $request->exam_id,
                'subject_id' => $request->subject_id,
                'grade' => $request->grade_level || $request->grade,
                'section' => $request->section,
                'exam_date' => $request->exam_date,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'duration' => $request->duration,
                'total_marks' => $request->total_marks,
                'passing_marks' => $request->passing_marks,
                'room_number' => $request->room_number,
                'invigilator_id' => $request->invigilator_id,
            ];

            $schedule = ExamSchedule::create($scheduleData);
            DB::commit();
            return response()->json(['success' => true, 'data' => $schedule->load(['exam', 'subject']), 'message' => 'Schedule created'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create schedule error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $request->all()
            ]);
            return response()->json([
                'success' => false, 
                'message' => 'Failed to create schedule',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $schedule = ExamSchedule::with(['exam:id,name,branch_id', 'subject:id,name,code'])
                ->select([
                    'id', 'exam_id', 'subject_id', 'grade', 'section',
                    'exam_date', 'start_time', 'end_time', 'duration',
                    'total_marks', 'passing_marks', 'room_number', 'invigilator_id',
                    'created_at', 'updated_at'
                ])
                ->findOrFail($id);
            return response()->json(['success' => true, 'data' => $schedule]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $schedule = ExamSchedule::findOrFail($id);
            $schedule->update($request->all());
            return response()->json(['success' => true, 'data' => $schedule->fresh(['exam', 'subject']), 'message' => 'Schedule updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update schedule'], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $schedule = ExamSchedule::findOrFail($id);
            $schedule->delete();
            return response()->json(['success' => true, 'message' => 'Schedule deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete schedule'], 500);
        }
    }

    /**
     * Get students for an exam schedule
     */
    public function getStudents($id)
    {
        try {
            $schedule = ExamSchedule::with(['exam'])->findOrFail($id);
            
            $query = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->where('students.grade', $schedule->grade);
            
            // Add section filter if specified
            if ($schedule->section) {
                $query->where('students.section', $schedule->section);
            }
            
            // Add branch filter from exam
            if ($schedule->exam && $schedule->exam->branch_id) {
                $query->where('students.branch_id', $schedule->exam->branch_id);
            }
            
            $students = $query->select(
                    'users.id as student_id',
                    'students.roll_number',
                    'students.admission_number',
                    'users.first_name',
                    'users.last_name',
                    'users.email'
                )
                ->orderBy('students.roll_number')
                ->get();
            
            return response()->json(['success' => true, 'data' => $students]);
        } catch (\Exception $e) {
            Log::error('Get schedule students error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch students'], 500);
        }
    }
}

