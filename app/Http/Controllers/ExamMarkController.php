<?php

namespace App\Http\Controllers;

use App\Models\ExamMark;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExamMarkController extends Controller
{
    /**
     * Get marks for an exam schedule
     */
    public function getMarks($scheduleId)
    {
        try {
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
            $totalMarks = $schedule->total_marks;
            $passingMarks = $schedule->passing_marks || 0;
            
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
     * Get marks for a specific student
     */
    public function getStudentMarks($studentId)
    {
        try {
            Log::info('Fetching marks for student ID: ' . $studentId);
            
            $marks = ExamMark::where('student_id', $studentId)->get();
            
            Log::info('Found ' . $marks->count() . ' marks for student');
            
            if ($marks->isEmpty()) {
                return response()->json(['success' => true, 'data' => []]);
            }
            
            $results = [];
            
            foreach ($marks as $mark) {
                $schedule = \App\Models\ExamSchedule::with(['exam', 'subject'])->find($mark->exam_schedule_id);
                
                $results[] = [
                    'id' => $mark->id,
                    'exam_name' => $schedule?->exam?->name ?? 'Exam',
                    'subject_name' => $schedule?->subject?->name ?? 'Subject',
                    'exam_date' => $schedule?->exam_date ?? null,
                    'marks_obtained' => (float)$mark->marks_obtained,
                    'total_marks' => (float)$mark->total_marks,
                    'passing_marks' => isset($schedule->passing_marks) ? (float)$schedule->passing_marks : (float)$schedule?->total_marks * 0.4, // Default to 40% if not set
                    'percentage' => (float)$mark->percentage,
                    'grade' => $mark->grade,
                    'is_pass' => (bool)$mark->is_pass,
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
