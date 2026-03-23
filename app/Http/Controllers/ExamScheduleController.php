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
                'total_marks', 'passing_marks', 'room_number', 'invigilator_id', 'instructions', 'created_at'
            ])->with([
                'exam:id,name,exam_term_id,branch_id,school_id,academic_year,academic_year_id',
                'exam.examTerm:id,name',
                'exam.branch:id,name,code',
                'subject:id,name,code',
                'invigilator:id,first_name,last_name'
            ]);

            // Apply company/school/branch scoping - show only accessible data:
            // - Super admin: all branches of company (or all if no company context)
            // - Branch admin / cross-branch: branches they can access
            // - Regular user: only their branch's exams
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds === 'all') {
                $schoolId = $this->getCurrentSchoolId($request);
                if ($schoolId) {
                    $query->whereHas('exam', function($q) use ($schoolId) {
                        $q->where('school_id', $schoolId);
                    });
                }
            } elseif (!empty($accessibleBranchIds)) {
                $query->whereHas('exam', function($q) use ($accessibleBranchIds) {
                    $q->whereIn('branch_id', $accessibleBranchIds);
                });
            } else {
                $query->whereRaw('1 = 0');
            }

            // Student view: show only schedules for this student's branch, grade, and section
            if ($request->has('student_id')) {
                $student = \App\Models\Student::find($request->student_id);
                if ($student) {
                    $query->whereHas('exam', function($q) use ($student) {
                        $q->where('branch_id', $student->branch_id);
                    });
                    $query->where('grade', $student->grade);
                    if ($student->section) {
                        $query->where(function($q) use ($student) {
                            $q->where('section', $student->section)->orWhereNull('section');
                        });
                    } else {
                        $query->whereNull('section');
                    }
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Upcoming only: exam date today or in the future
            if ($request->boolean('upcoming')) {
                $query->whereDate('exam_date', '>=', Carbon::today()->toDateString());
            }

            // Optional filters (user-selected, must be within accessible branches)
            if ($request->has('branch_id')) {
                $query->whereHas('exam', function($q) use ($request) {
                    $q->where('branch_id', $request->branch_id);
                });
            }

            if ($request->has('exam_id')) {
                $query->where('exam_id', $request->exam_id);
            }

            // Filter by academic year (schedules belong to exams; scope by exam's academic year)
            // Use toolbar context (X-Academic-Year-Id) when no explicit param provided
            $academicYearId = $request->attributes->get('academic_year_id') ?? $request->input('academic_year_id');
            if ($academicYearId) {
                $query->whereHas('exam', function ($q) use ($academicYearId) {
                    $q->where('academic_year_id', (int) $academicYearId);
                });
            } elseif ($request->filled('academic_year')) {
                $query->whereHas('exam', function ($q) use ($request) {
                    $q->where('academic_year', $request->academic_year);
                });
            }

            if ($request->has('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->filled('section')) {
                $query->where('section', $request->section);
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
                'instructions' => $request->instructions,
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

    public function show(Request $request, $id)
    {
        try {
            $schedule = ExamSchedule::with(['exam:id,name,branch_id,school_id', 'subject:id,name,code'])
                ->select([
                    'id', 'exam_id', 'subject_id', 'grade', 'section',
                    'exam_date', 'start_time', 'end_time', 'duration',
                    'total_marks', 'passing_marks', 'room_number', 'invigilator_id',
                    'instructions', 'created_at', 'updated_at'
                ])
                ->findOrFail($id);
            if (!$this->canAccessSchedule($request, $schedule)) {
                return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
            }
            return response()->json(['success' => true, 'data' => $schedule]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $schedule = ExamSchedule::with('exam')->findOrFail($id);
            if (!$this->canAccessSchedule($request, $schedule)) {
                return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
            }
            $data = $request->all();
            // Map grade_level to grade (form sends grade_level; DB column is grade)
            if (array_key_exists('grade_level', $data)) {
                $data['grade'] = $data['grade_level'];
                unset($data['grade_level']);
            }
            $schedule->update($data);
            return response()->json(['success' => true, 'data' => $schedule->fresh(['exam', 'subject']), 'message' => 'Schedule updated']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update schedule'], 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        try {
            $schedule = ExamSchedule::with('exam')->findOrFail($id);
            if (!$this->canAccessSchedule($request, $schedule)) {
                return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
            }
            $schedule->delete();
            return response()->json(['success' => true, 'message' => 'Schedule deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete schedule'], 500);
        }
    }

    /**
     * Get students for an exam schedule
     */
    public function getStudents(Request $request, $id)
    {
        try {
            $schedule = ExamSchedule::with(['exam'])->findOrFail($id);
            if (!$this->canAccessSchedule($request, $schedule)) {
                return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
            }
            
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

    /**
     * Check if the current user can access the given exam schedule (company/branch scoping)
     */
    protected function canAccessSchedule(Request $request, ExamSchedule $schedule): bool
    {
        $exam = $schedule->exam;
        if (!$exam) {
            return false;
        }
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds === 'all') {
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                return $exam->school_id == $schoolId;
            }
            return true;
        }
        return in_array($exam->branch_id, $accessibleBranchIds);
    }
}

