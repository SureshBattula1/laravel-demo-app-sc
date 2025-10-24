<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ExamScheduleController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            // 🚀 OPTIMIZED: Select only needed columns
            $query = ExamSchedule::select([
                'id', 'exam_id', 'subject_id', 'branch_id', 'grade_level', 'section',
                'exam_date', 'start_time', 'end_time', 'duration', 'total_marks',
                'passing_marks', 'room_number', 'invigilator_id', 'status', 'is_active', 'created_at'
            ])->with([
                'exam:id,name,exam_type',
                'subject:id,name,code',
                'branch:id,name,code',
                'invigilator:id,first_name,last_name'
            ]);

            // Filters
            if ($request->has('exam_id')) {
                $query->where('exam_id', $request->exam_id);
            }

            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('section')) {
                $query->where('section', $request->section);
            }

            if ($request->has('exam_date')) {
                $query->whereDate('exam_date', $request->exam_date);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            $schedules = $this->paginateAndSort($query, $request, [
                'id', 'exam_date', 'start_time', 'grade_level', 'section', 'status', 'created_at'
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
            'branch_id' => 'required|exists:branches,id',
            'grade_level' => 'required|string',
            'section' => 'nullable|string',
            'exam_date' => 'required|date',
            'start_time' => 'required',
            'end_time' => 'required',
            'duration' => 'required|integer|min:1',
            'total_marks' => 'required|numeric|min:0',
            'passing_marks' => 'required|numeric|min:0|lte:total_marks',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        DB::beginTransaction();
        try {
            $schedule = ExamSchedule::create($request->all());
            DB::commit();
            return response()->json(['success' => true, 'data' => $schedule->load(['exam', 'subject', 'branch']), 'message' => 'Schedule created'], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create schedule error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to create schedule'], 500);
        }
    }

    public function show($id)
    {
        try {
            $schedule = ExamSchedule::with(['exam', 'subject', 'branch', 'invigilator'])->findOrFail($id);
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
            if ($schedule->marks()->count() > 0) {
                return response()->json(['success' => false, 'message' => 'Cannot delete schedule with existing marks'], 400);
            }
            $schedule->delete();
            return response()->json(['success' => true, 'message' => 'Schedule deleted']);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete schedule'], 500);
        }
    }
}

