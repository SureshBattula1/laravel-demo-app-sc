<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\ExamMark;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ExamMarkController extends Controller
{
    /**
     * Get marks for an exam schedule
     */
    public function getMarks(Request $request, $scheduleId)
    {
        try {
            // Tenant guard: only read marks for schedules in an accessible branch.
            $schedule = \App\Models\ExamSchedule::with('exam')->findOrFail($scheduleId);
            if (!$this->canAccessScheduleBranch($request, $schedule)) {
                return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
            }

            $marks = ExamMark::where('exam_schedule_id', $scheduleId)
                ->with(['student:id,first_name,last_name', 'subject:id,name'])
                ->get();

            return response()->json(['success' => true, 'data' => $marks]);
        } catch (\Exception $e) {
            Log::error('Get marks error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch marks'], 500);
        }
    }

    /**
     * Whether the current user can access the branch of an exam schedule.
     */
    protected function canAccessScheduleBranch(Request $request, \App\Models\ExamSchedule $schedule): bool
    {
        $exam = $schedule->exam;
        if (!$exam) {
            return false;
        }
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds === 'all') {
            $schoolId = $this->getCurrentSchoolId($request);
            return $schoolId ? ((int) $exam->school_id === (int) $schoolId) : true;
        }
        return in_array((int) $exam->branch_id, array_map('intval', (array) $accessibleBranchIds), true);
    }

    /**
     * Store marks for an exam schedule
     */
    public function storeMarks(Request $request, $scheduleId)
    {
        $validator = Validator::make($request->all(), [
            'marks' => 'required|array',
            'marks.*.student_id' => 'required|exists:users,id',
            'marks.*.marks_obtained' => 'required|numeric|min:0',
            'marks.*.is_absent' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $schedule = \App\Models\ExamSchedule::with('exam')->findOrFail($scheduleId);

            // Tenant guard: only enter marks for schedules in a branch the user can manage.
            if (!$this->canAccessScheduleBranch($request, $schedule)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Schedule not found'], 404);
            }

            $totalMarks = (float) $schedule->total_marks;
            // Use the actual passing marks (|| would coerce to a boolean 1/0).
            $passingMarks = (float) ($schedule->passing_marks ?? 0);

            foreach ($request->marks as $markData) {
                $marksObtained = $markData['is_absent'] ? 0 : $markData['marks_obtained'];
                $percentage = $totalMarks > 0 ? ($marksObtained / $totalMarks) * 100 : 0;
                $isPass = !$markData['is_absent'] && $marksObtained >= $passingMarks;
                
                ExamMark::updateOrCreate(
                    [
                        'exam_schedule_id' => $scheduleId,
                        'student_id' => $markData['student_id']
                    ],
                    [
                        'subject_id' => $schedule->subject_id,
                        'marks_obtained' => $marksObtained,
                        'total_marks' => $totalMarks,
                        'percentage' => $percentage,
                        'grade' => $this->calculateGrade($percentage),
                        'is_absent' => $markData['is_absent'] ?? false,
                        'is_pass' => $isPass,
                        'remarks' => $markData['remarks'] ?? null,
                        'status' => 'Draft',
                        'entered_by' => auth()->id()
                    ]
                );
            }
            
            DB::commit();
            return response()->json(['success' => true, 'message' => 'Marks saved successfully']);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Store marks error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to save marks'], 500);
        }
    }

    /**
     * Get marks for a specific student (only for exams in the student's branch/school)
     */
    public function getStudentMarks($studentId)
    {
        try {
            // studentId is user_id (users.id)
            $student = Student::where('user_id', $studentId)->first();
            if (!$student) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Only show marks for exams in the same branch as the student (company/school scoping)
            $marksQuery = ExamMark::where('exam_marks.student_id', $studentId)
                ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
                ->join('exams', 'exam_schedules.exam_id', '=', 'exams.id')
                ->where('exams.branch_id', $student->branch_id);

            $academicYearId = request()->attributes->get('academic_year_id') ?? request()->input('academic_year_id');
            if ($academicYearId && \Illuminate\Support\Facades\Schema::hasColumn('exams', 'academic_year_id')) {
                $marksQuery->where('exams.academic_year_id', (int) $academicYearId);
            } elseif ($academicYearId) {
                $academicYearName = \App\Models\AcademicYear::query()->where('id', $academicYearId)->value('name');
                if ($academicYearName) {
                    $marksQuery->where('exams.academic_year', $academicYearName);
                }
            }

            // Optional: filter by school if student has school_id
            if (!empty($student->school_id)) {
                $marksQuery->where('exams.school_id', $student->school_id);
            }

            $marks = $marksQuery->select('exam_marks.*')->get();

            if ($marks->isEmpty()) {
                return response()->json(['success' => true, 'data' => []]);
            }

            // Eager-load all related schedules in ONE query instead of one per mark (avoids N+1).
            $schedules = \App\Models\ExamSchedule::with(['exam.examTerm', 'subject'])
                ->whereIn('id', $marks->pluck('exam_schedule_id')->unique()->all())
                ->get()
                ->keyBy('id');

            $results = [];

            foreach ($marks as $mark) {
                $schedule = $schedules->get($mark->exam_schedule_id);
                $passingMarks = isset($schedule->passing_marks) ? (float)$schedule->passing_marks : (float)($schedule?->total_marks ?? 100) * 0.4;
                $isPass = !$mark->is_absent && (float)$mark->marks_obtained >= $passingMarks;

                $results[] = [
                    'id' => $mark->id,
                    'exam_id' => $schedule?->exam_id ?? null,
                    'exam_name' => $schedule?->exam?->name ?? 'Exam',
                    'exam_term_name' => $schedule?->exam?->examTerm?->name ?? null,
                    'subject_name' => $schedule?->subject?->name ?? 'Subject',
                    'exam_date' => $schedule?->exam_date ?? null,
                    'marks_obtained' => (float)$mark->marks_obtained,
                    'total_marks' => (float)$mark->total_marks,
                    'passing_marks' => $passingMarks,
                    'percentage' => (float)$mark->percentage,
                    'grade' => $mark->grade,
                    'is_pass' => $isPass,
                    'is_absent' => (bool)$mark->is_absent,
                    'remarks' => $mark->remarks
                ];
            }
            
            Log::info('Returning ' . count($results) . ' results');
            
            return response()->json(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            Log::error('Get student marks error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch student marks: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Exam marks overview for student header (all years vs toolbar academic year).
     * Separate from attendance — based on marks obtained ÷ total marks (non-absent only).
     */
    public function getStudentMarksOverview($studentId)
    {
        try {
            $student = Student::where('user_id', $studentId)->whereNull('deleted_at')->first();
            if (!$student) {
                return response()->json(['success' => false, 'message' => 'Student not found'], 404);
            }

            $overallSummary = $this->summarizeStudentExamMarks(
                $this->studentMarksBaseQuery($studentId, $student)
            );

            $currentYearQuery = $this->studentMarksBaseQuery($studentId, $student);
            $academicYearId = request()->attributes->get('academic_year_id') ?? request()->input('academic_year_id');
            $academicYearName = null;

            if ($academicYearId) {
                $year = AcademicYear::query()->find((int) $academicYearId);
                $academicYearName = $year?->name;
                if (Schema::hasColumn('exams', 'academic_year_id')) {
                    $currentYearQuery->where('exams.academic_year_id', (int) $academicYearId);
                } elseif ($academicYearName) {
                    $currentYearQuery->where('exams.academic_year', $academicYearName);
                }
            }

            $currentYearSummary = $this->summarizeStudentExamMarks($currentYearQuery);
            $byYear = $this->getMarksBreakdownByYear($studentId, $student);

            return response()->json([
                'success' => true,
                'data' => [
                    'overall' => $overallSummary,
                    'current_year' => array_merge($currentYearSummary, [
                        'academic_year_id' => $academicYearId ? (int) $academicYearId : null,
                        'academic_year_name' => $academicYearName,
                    ]),
                    'by_year' => $byYear,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get student marks overview error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student marks overview',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder
     */
    private function studentMarksBaseQuery($studentId, Student $student)
    {
        $query = ExamMark::query()
            ->where('exam_marks.student_id', $studentId)
            ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
            ->join('exams', 'exam_schedules.exam_id', '=', 'exams.id')
            ->where('exams.branch_id', $student->branch_id);

        if (!empty($student->school_id)) {
            $query->where('exams.school_id', $student->school_id);
        }

        return $query;
    }

    /**
     * Per academic year marks breakdown for overall ring popup.
     */
    private function getMarksBreakdownByYear($studentId, Student $student): array
    {
        $query = $this->studentMarksBaseQuery($studentId, $student)
            ->where('exam_marks.is_absent', false);

        if (Schema::hasColumn('exams', 'academic_year_id')) {
            $rows = (clone $query)
                ->leftJoin('academic_years', 'exams.academic_year_id', '=', 'academic_years.id')
                ->select(
                    'exams.academic_year_id',
                    DB::raw('COALESCE(MAX(academic_years.name), MAX(exams.academic_year), "Unassigned") as academic_year_name'),
                    DB::raw('SUM(exam_marks.marks_obtained) as marks_obtained'),
                    DB::raw('SUM(exam_marks.total_marks) as total_marks'),
                    DB::raw('COUNT(*) as subjects_count')
                )
                ->groupBy('exams.academic_year_id')
                ->get();
        } else {
            $rows = (clone $query)
                ->select(
                    DB::raw('COALESCE(exams.academic_year, "Unassigned") as academic_year_name'),
                    DB::raw('SUM(exam_marks.marks_obtained) as marks_obtained'),
                    DB::raw('SUM(exam_marks.total_marks) as total_marks'),
                    DB::raw('COUNT(*) as subjects_count')
                )
                ->groupBy('exams.academic_year')
                ->get();
        }

        return $rows
            ->map(function ($row) {
                $obtained = (float) ($row->marks_obtained ?? 0);
                $total = (float) ($row->total_marks ?? 0);

                return [
                    'academic_year_id' => isset($row->academic_year_id) ? (int) $row->academic_year_id : null,
                    'academic_year_name' => (string) ($row->academic_year_name ?? 'Unassigned'),
                    'marks_obtained' => $obtained,
                    'total_marks' => $total,
                    'subjects_count' => (int) ($row->subjects_count ?? 0),
                    'percentage' => $total > 0 ? round(($obtained / $total) * 100, 2) : 0,
                ];
            })
            ->sortByDesc('academic_year_name')
            ->values()
            ->all();
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    private function summarizeStudentExamMarks($query): array
    {
        $summary = (clone $query)
            ->where('exam_marks.is_absent', false)
            ->select(
                DB::raw('SUM(exam_marks.marks_obtained) as marks_obtained'),
                DB::raw('SUM(exam_marks.total_marks) as total_marks'),
                DB::raw('COUNT(*) as subjects_count')
            )
            ->first();

        $obtained = (float) ($summary->marks_obtained ?? 0);
        $total = (float) ($summary->total_marks ?? 0);

        return [
            'marks_obtained' => $obtained,
            'total_marks' => $total,
            'subjects_count' => (int) ($summary->subjects_count ?? 0),
            'percentage' => $total > 0 ? round(($obtained / $total) * 100, 2) : 0,
        ];
    }

    private function calculateGrade($percentage): string
    {
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B+';
        if ($percentage >= 60) return 'B';
        if ($percentage >= 50) return 'C+';
        if ($percentage >= 40) return 'C';
        return 'F';
    }
}
