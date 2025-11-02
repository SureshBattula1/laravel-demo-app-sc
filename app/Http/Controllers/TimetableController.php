<?php

namespace App\Http\Controllers;

use App\Models\Timetable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TimetableController extends Controller
{
    /**
     * Get all timetables
     */
    public function index(Request $request)
    {
        try {
            $query = Timetable::with(['branch', 'subject', 'teacher', 'creator']);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->has('section')) {
                $query->where('section', $request->section);
            }

            if ($request->has('day')) {
                $query->where('day', $request->day);
            }

            if ($request->has('teacher_id')) {
                $query->where('teacher_id', $request->teacher_id);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            $timetables = $query->orderBy('day_order')
                                ->orderBy('start_time')
                                ->get();

            return response()->json([
                'success' => true,
                'data' => $timetables,
                'message' => 'Timetables retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Get timetables error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch timetables',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new timetable
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'grade' => 'required|string|max:50',
                'section' => 'required|string|max:10',
                'subject_id' => 'required|exists:subjects,id',
                'teacher_id' => 'required|exists:users,id',
                'day' => 'required|string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'day_order' => 'required|integer|min:1|max:7',
                'period_number' => 'required|integer|min:1|max:10',
                'start_time' => 'required|date_format:H:i',
                'end_time' => 'required|date_format:H:i|after:start_time',
                'room_number' => 'nullable|string|max:50',
                'academic_year' => 'required|string|max:20',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for conflicts
            $conflict = Timetable::where('branch_id', $request->branch_id)
                ->where('grade', $request->grade)
                ->where('section', $request->section)
                ->where('day', $request->day)
                ->where('period_number', $request->period_number)
                ->where('academic_year', $request->academic_year)
                ->first();

            if ($conflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Timetable conflict: This slot is already occupied'
                ], 422);
            }

            // Check teacher availability
            $teacherConflict = Timetable::where('teacher_id', $request->teacher_id)
                ->where('day', $request->day)
                ->where('period_number', $request->period_number)
                ->where('academic_year', $request->academic_year)
                ->first();

            if ($teacherConflict) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher is already assigned to another class at this time'
                ], 422);
            }

            $timetable = Timetable::create([
                'branch_id' => $request->branch_id,
                'grade' => $request->grade,
                'section' => $request->section,
                'subject_id' => $request->subject_id,
                'teacher_id' => $request->teacher_id,
                'day' => $request->day,
                'day_order' => $request->day_order,
                'period_number' => $request->period_number,
                'start_time' => $request->start_time,
                'end_time' => $request->end_time,
                'room_number' => $request->room_number,
                'academic_year' => $request->academic_year,
                'is_active' => $request->is_active ?? true,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            Log::info('Timetable created', ['timetable_id' => $timetable->id]);

            return response()->json([
                'success' => true,
                'message' => 'Timetable created successfully',
                'data' => $timetable->load(['branch', 'subject', 'teacher'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create timetable error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create timetable',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get specific timetable
     */
    public function show(string $id)
    {
        try {
            $timetable = Timetable::with(['branch', 'subject', 'teacher', 'creator'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $timetable
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Timetable not found'
            ], 404);
        }
    }

    /**
     * Update timetable
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $timetable = Timetable::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'subject_id' => 'exists:subjects,id',
                'teacher_id' => 'exists:users,id',
                'day' => 'string|in:Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday',
                'day_order' => 'integer|min:1|max:7',
                'period_number' => 'integer|min:1|max:10',
                'start_time' => 'date_format:H:i',
                'end_time' => 'date_format:H:i',
                'room_number' => 'nullable|string|max:50',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Check for conflicts if period or day changed
            if ($request->has('day') || $request->has('period_number')) {
                $conflict = Timetable::where('id', '!=', $id)
                    ->where('branch_id', $timetable->branch_id)
                    ->where('grade', $timetable->grade)
                    ->where('section', $timetable->section)
                    ->where('day', $request->day ?? $timetable->day)
                    ->where('period_number', $request->period_number ?? $timetable->period_number)
                    ->where('academic_year', $timetable->academic_year)
                    ->first();

                if ($conflict) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Timetable conflict: This slot is already occupied'
                    ], 422);
                }
            }

            $updateData = [];
            if ($request->has('subject_id')) $updateData['subject_id'] = $request->subject_id;
            if ($request->has('teacher_id')) $updateData['teacher_id'] = $request->teacher_id;
            if ($request->has('day')) $updateData['day'] = $request->day;
            if ($request->has('day_order')) $updateData['day_order'] = $request->day_order;
            if ($request->has('period_number')) $updateData['period_number'] = $request->period_number;
            if ($request->has('start_time')) $updateData['start_time'] = $request->start_time;
            if ($request->has('end_time')) $updateData['end_time'] = $request->end_time;
            if ($request->has('room_number')) $updateData['room_number'] = $request->room_number;
            if ($request->has('is_active')) $updateData['is_active'] = $request->is_active;
            
            $updateData['updated_by'] = $request->user()->id;

            $timetable->update($updateData);

            DB::commit();

            Log::info('Timetable updated', ['timetable_id' => $timetable->id]);

            return response()->json([
                'success' => true,
                'message' => 'Timetable updated successfully',
                'data' => $timetable->load(['branch', 'subject', 'teacher'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update timetable error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update timetable',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete timetable
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $timetable = Timetable::findOrFail($id);
            $timetable->delete();
            DB::commit();

            Log::info('Timetable deleted', ['timetable_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Timetable deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete timetable error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete timetable',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get timetable by class
     */
    public function getByClass(string $grade, string $section)
    {
        try {
            $academicYear = request()->get('academic_year', date('Y'));
            
            $timetables = Timetable::with(['subject', 'teacher'])
                ->where('grade', $grade)
                ->where('section', $section)
                ->where('academic_year', $academicYear)
                ->where('is_active', true)
                ->orderBy('day_order')
                ->orderBy('period_number')
                ->get();

            // Group by day
            $grouped = $timetables->groupBy('day')->map(function($daySchedule) {
                return $daySchedule->sortBy('period_number')->values();
            });

            return response()->json([
                'success' => true,
                'data' => [
                    'grade' => $grade,
                    'section' => $section,
                    'academic_year' => $academicYear,
                    'schedule' => $grouped
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get class timetable error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch class timetable',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
