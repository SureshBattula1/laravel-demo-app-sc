<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubjectController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Subject::with(['department', 'teacher', 'branch'])
                ->leftJoin('grades', 'subjects.grade_level', '=', 'grades.value')
                ->select('subjects.*', 'grades.label as grade_label');

            if ($request->branch_id) {
                $query->where('subjects.branch_id', $request->branch_id);
            }

            if ($request->department_id) {
                $query->where('subjects.department_id', $request->department_id);
            }

            if ($request->grade_level) {
                $query->where('subjects.grade_level', strip_tags($request->grade_level));
            }

            if ($request->type) {
                $query->where('subjects.type', strip_tags($request->type));
            }

            if ($request->search) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('subjects.name', 'like', '%' . $search . '%')
                      ->orWhere('subjects.code', 'like', '%' . $search . '%');
                });
            }

            $subjects = $query->orderBy('subjects.name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $subjects,
                'count' => $subjects->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get subjects error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subjects',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255|regex:/^[a-zA-Z0-9\s\-&]+$/',
                'code' => 'required|string|max:50|unique:subjects|regex:/^[A-Z0-9\-]+$/',
                'department_id' => 'required|exists:departments,id',
                'grade_level' => 'required|string|in:1,2,3,4,5,6,7,8,9,10,11,12',
                'type' => 'required|in:Core,Elective,Language,Lab,Activity',
                'branch_id' => 'required|exists:branches,id',
                'teacher_id' => 'nullable|exists:users,id',
                'credits' => 'nullable|integer|min:0|max:10',
                'description' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $sanitizedData = [
                'name' => strip_tags($request->name),
                'code' => strtoupper(strip_tags($request->code)),
                'description' => strip_tags($request->description),
                'department_id' => $request->department_id,
                'teacher_id' => $request->teacher_id,
                'grade_level' => $request->grade_level,
                'credits' => $request->credits ?? 0,
                'type' => $request->type,
                'branch_id' => $request->branch_id,
                'is_active' => $request->is_active ?? true
            ];

            $subject = Subject::create($sanitizedData);

            DB::commit();

            Log::info('Subject created', ['subject_id' => $subject->id]);

            return response()->json([
                'success' => true,
                'message' => 'Subject created successfully',
                'data' => $subject->load(['department', 'teacher'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create subject error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create subject',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid subject ID'
                ], 400);
            }

            $subject = Subject::with(['department', 'teacher', 'exams'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $subject
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get subject error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch subject',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid subject ID'
                ], 400);
            }

            $subject = Subject::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255|regex:/^[a-zA-Z0-9\s\-&]+$/',
                'code' => 'sometimes|string|max:50|unique:subjects,code,' . $id . '|regex:/^[A-Z0-9\-]+$/',
                'department_id' => 'sometimes|exists:departments,id',
                'grade_level' => 'sometimes|string|in:1,2,3,4,5,6,7,8,9,10,11,12',
                'type' => 'sometimes|in:Core,Elective,Language,Lab,Activity',
                'teacher_id' => 'nullable|exists:users,id',
                'credits' => 'nullable|integer|min:0|max:10',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = [];
            if ($request->has('name')) {
                $updateData['name'] = strip_tags($request->name);
            }
            if ($request->has('code')) {
                $updateData['code'] = strtoupper(strip_tags($request->code));
            }
            if ($request->has('description')) {
                $updateData['description'] = strip_tags($request->description);
            }
            
            $updateData = array_merge($updateData, $request->only([
                'department_id', 'teacher_id', 'grade_level', 
                'credits', 'type', 'is_active'
            ]));

            $subject->update($updateData);

            DB::commit();

            Log::info('Subject updated', ['subject_id' => $subject->id]);

            return response()->json([
                'success' => true,
                'message' => 'Subject updated successfully',
                'data' => $subject->load(['department', 'teacher'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update subject error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update subject',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid subject ID'
                ], 400);
            }

            DB::beginTransaction();

            $subject = Subject::findOrFail($id);
            
            // Check if subject has exams
            if ($subject->exams()->count() > 0) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete subject with existing exams'
                ], 400);
            }

            $subject->delete();

            DB::commit();

            Log::info('Subject deleted', ['subject_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Subject deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Subject not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete subject error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete subject',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
