<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class ExamController extends Controller
{
    /**
     * Display a listing of exams - OPTIMIZED with pagination
     */
    public function index(Request $request)
    {
        try {
            $query = Exam::with(['branch', 'examTerm', 'creator']);

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by exam term
            if ($request->has('exam_term_id')) {
                $query->where('exam_term_id', $request->exam_term_id);
            }

            // Filter by academic year
            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            // Filter by exam type
            if ($request->has('exam_type')) {
                $query->where('exam_type', $request->exam_type);
            }

            // OPTIMIZED Search by name - prefix search for better index usage
            if ($request->has('search') && !empty($request->search)) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', "{$search}%")  // ✅ Can use index
                      ->orWhere('exam_type', 'like', "{$search}%")
                      ->orWhere('academic_year', 'like', "{$search}%")
                      ->orWhereHas('branch', function($q) use ($search) {
                          $q->where('name', 'like', "{$search}%")
                            ->orWhere('code', 'like', "{$search}%");
                      })
                      ->orWhereHas('examTerm', function($q) use ($search) {
                          $q->where('name', 'like', "{$search}%")
                            ->orWhere('code', 'like', "{$search}%");
                      });
                });
            }

            // Filter by status
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // OPTIMIZED: Add pagination to prevent loading all exams
            $perPage = $request->get('per_page', 25);
            $page = $request->get('page', 1);
            
            $exams = $query->orderBy('created_at', 'desc')
                ->paginate($perPage, ['*'], 'page', $page);

            return response()->json([
                'success' => true,
                'data' => $exams->items(),
                'meta' => [
                    'current_page' => $exams->currentPage(),
                    'per_page' => $exams->perPage(),
                    'total' => $exams->total(),
                    'last_page' => $exams->lastPage(),
                    'from' => $exams->firstItem(),
                    'to' => $exams->lastItem(),
                ],
                'message' => 'Exams retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching exams: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching exams',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created exam
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|integer|exists:branches,id',
                'name' => 'required|string|max:255',
                'exam_term_id' => 'nullable|integer|exists:exam_terms,id',
                'exam_type' => 'nullable|string|in:Midterm,Final,Quiz,Assignment,Practical,Other',
                'academic_year' => 'required|string|max:20',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare data with proper date formatting
            $examData = $request->only([
                'exam_term_id', 'branch_id', 'name', 'exam_type', 'academic_year', 'description'
            ]);
            
            // Set default values for total_marks and passing_marks
            $examData['total_marks'] = 0;
            $examData['passing_marks'] = 0;
            
            // Format dates properly
            if ($request->has('start_date')) {
                $examData['start_date'] = Carbon::parse($request->start_date)->format('Y-m-d');
            }
            if ($request->has('end_date')) {
                $examData['end_date'] = Carbon::parse($request->end_date)->format('Y-m-d');
            }
            
            $exam = Exam::create([
                ...$examData,
                'created_by' => $request->user()->id,
                'is_active' => $request->has('is_active') ? $request->boolean('is_active') : true
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $exam->load(['branch', 'examTerm', 'creator']),
                'message' => 'Exam created successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating exam: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error creating exam',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified exam
     */
    public function show(string $id)
    {
        try {
            $exam = Exam::with(['branch', 'examTerm', 'results.student', 'creator', 'updater'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $exam,
                'message' => 'Exam retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching exam: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Exam not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Update the specified exam
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $exam = Exam::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'branch_id' => 'integer|exists:branches,id',
                'name' => 'string|max:255',
                'exam_term_id' => 'nullable|integer|exists:exam_terms,id',
                'exam_type' => 'nullable|string|in:Midterm,Final,Quiz,Assignment,Practical,Other',
                'academic_year' => 'string|max:20',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Prepare update data with proper date formatting
            $updateData = $request->only([
                'exam_term_id', 'branch_id', 'name', 'exam_type', 'academic_year', 'description', 'is_active'
            ]);
            
            // Format dates properly
            if ($request->has('start_date')) {
                $updateData['start_date'] = Carbon::parse($request->start_date)->format('Y-m-d');
            }
            if ($request->has('end_date')) {
                $updateData['end_date'] = Carbon::parse($request->end_date)->format('Y-m-d');
            }
            
            $exam->update([
                ...$updateData,
                'updated_by' => $request->user()->id
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $exam->load(['branch', 'examTerm', 'creator', 'updater']),
                'message' => 'Exam updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error updating exam: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error updating exam',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified exam
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $exam = Exam::findOrFail($id);
            
            // Check if exam has results
            if ($exam->results()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete exam with existing results. Please delete results first.'
                ], 422);
            }

            $exam->delete();
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Exam deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error deleting exam: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error deleting exam',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get exam statistics - OPTIMIZED with single query
     */
    public function statistics(string $id)
    {
        try {
            $exam = Exam::findOrFail($id);

            // OPTIMIZED: Single aggregated query instead of 5 separate queries
            $stats = DB::table('exam_results')
                ->where('exam_id', $id)
                ->select(
                    DB::raw('COUNT(*) as total_students'),
                    DB::raw('SUM(CASE WHEN marks_obtained >= ' . (float)$exam->passing_marks . ' THEN 1 ELSE 0 END) as passed_students'),
                    DB::raw('AVG(marks_obtained) as average_marks'),
                    DB::raw('MAX(marks_obtained) as highest_marks'),
                    DB::raw('MIN(marks_obtained) as lowest_marks')
                )
                ->first();

            $totalStudents = (int) ($stats->total_students ?? 0);
            $passedStudents = (int) ($stats->passed_students ?? 0);
            $failedStudents = $totalStudents - $passedStudents;
            $averageMarks = round((float) ($stats->average_marks ?? 0), 2);
            $highestMarks = (float) ($stats->highest_marks ?? 0);
            $lowestMarks = (float) ($stats->lowest_marks ?? 0);

            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => $exam,
                    'statistics' => [
                        'total_students' => $totalStudents,
                        'passed_students' => $passedStudents,
                        'failed_students' => $failedStudents,
                        'pass_percentage' => $totalStudents > 0 ? round(($passedStudents / $totalStudents) * 100, 2) : 0,
                        'average_marks' => $averageMarks,
                        'highest_marks' => $highestMarks,
                        'lowest_marks' => $lowestMarks
                    ]
                ],
                'message' => 'Exam statistics retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Error fetching exam statistics: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching exam statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store exam result with security and validation
     */
    public function storeResult(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'exam_id' => 'required|exists:exams,id',
                'student_id' => 'required|exists:users,id',
                'subject_id' => 'required|exists:subjects,id',
                'marks_obtained' => 'required|numeric|min:0',
                'grade' => 'nullable|string|max:5',
                'remarks' => 'nullable|string|max:500',
                'attendance' => 'nullable|string|in:Present,Absent',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $exam = Exam::findOrFail($request->exam_id);

            // Validate marks don't exceed total marks
            if ($request->marks_obtained > $exam->total_marks) {
                return response()->json([
                    'success' => false,
                    'message' => 'Marks obtained cannot exceed total marks'
                ], 422);
            }

            // Calculate grade if not provided
            $grade = $request->grade;
            if (!$grade) {
                $percentage = ($request->marks_obtained / $exam->total_marks) * 100;
                $grade = $this->calculateGrade($percentage);
            }

            // Determine pass/fail status
            $status = $request->marks_obtained >= $exam->passing_marks ? 'Pass' : 'Fail';

            $result = \App\Models\ExamResult::create([
                'exam_id' => $request->exam_id,
                'student_id' => $request->student_id,
                'subject_id' => $request->subject_id,
                'marks_obtained' => $request->marks_obtained,
                'grade' => $grade,
                'status' => $status,
                'remarks' => strip_tags($request->remarks ?? ''),
                'attendance' => $request->attendance ?? 'Present',
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            Log::info('Exam result created', ['result_id' => $result->id]);

            return response()->json([
                'success' => true,
                'message' => 'Result recorded successfully',
                'data' => $result->load(['exam', 'student', 'subject'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create result error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to record result',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get results for a specific exam
     */
    public function getResults(string $id)
    {
        try {
            $exam = Exam::findOrFail($id);
            $results = $exam->results()
                ->with(['student', 'subject'])
                ->orderBy('marks_obtained', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'exam' => $exam,
                    'results' => $results
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get exam results error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch results',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get all results for a specific student
     */
    public function getStudentResults(string $studentId)
    {
        try {
            $results = \App\Models\ExamResult::where('student_id', $studentId)
                ->with(['exam', 'subject'])
                ->orderBy('created_at', 'desc')
                ->get();

            // Group by exam
            $groupedResults = $results->groupBy('exam_id')->map(function ($examResults) {
                $exam = $examResults->first()->exam;
                $totalMarks = $examResults->sum('marks_obtained');
                $totalPossible = $examResults->count() * $exam->total_marks;
                $percentage = $totalPossible > 0 ? ($totalMarks / $totalPossible) * 100 : 0;

                return [
                    'exam' => $exam,
                    'results' => $examResults,
                    'summary' => [
                        'total_marks' => $totalMarks,
                        'total_possible' => $totalPossible,
                        'percentage' => round($percentage, 2),
                        'grade' => $this->calculateGrade($percentage),
                        'subjects_count' => $examResults->count()
                    ]
                ];
            })->values();

            return response()->json([
                'success' => true,
                'data' => $groupedResults
            ]);

        } catch (\Exception $e) {
            Log::error('Get student results error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student results',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Calculate grade based on percentage
     */
    private function calculateGrade(float $percentage): string
    {
        if ($percentage >= 90) return 'A+';
        if ($percentage >= 80) return 'A';
        if ($percentage >= 70) return 'B+';
        if ($percentage >= 60) return 'B';
        if ($percentage >= 50) return 'C';
        if ($percentage >= 40) return 'D';
        return 'F';
    }
}
