<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GradeController extends Controller
{
    /**
     * Get all grades (stored in a configuration table or cached)
     */
    public function index(Request $request)
    {
        try {
            // Get grades from the grades table or return predefined list
            $grades = DB::table('grades')
                ->orderBy('value', 'asc')
                ->get()
                ->map(function ($grade) {
                    return [
                        'value' => $grade->value,
                        'label' => $grade->label,
                        'description' => $grade->description ?? null,
                        'is_active' => (bool) $grade->is_active,
                        'created_at' => $grade->created_at,
                        'updated_at' => $grade->updated_at
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $grades,
                'count' => $grades->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get grades error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grades',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single grade
     */
    public function show($value)
    {
        try {
            $grade = DB::table('grades')
                ->where('value', $value)
                ->first();

            if (!$grade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description ?? null,
                    'is_active' => (bool) $grade->is_active
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new grade
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'value' => 'required|string|max:20|unique:grades,value',
                'label' => 'required|string|max:100',
                'description' => 'nullable|string|max:500',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $gradeId = DB::table('grades')->insertGetId([
                'value' => strip_tags($request->value),
                'label' => strip_tags($request->label),
                'description' => $request->description ? strip_tags($request->description) : null,
                'is_active' => $request->boolean('is_active', true),
                'created_at' => now(),
                'updated_at' => now()
            ]);

            $grade = DB::table('grades')->where('id', $gradeId)->first();

            DB::commit();

            Log::info('Grade created', ['grade_value' => $grade->value]);

            return response()->json([
                'success' => true,
                'message' => 'Grade created successfully',
                'data' => [
                    'value' => $grade->value,
                    'label' => $grade->label,
                    'description' => $grade->description,
                    'is_active' => (bool) $grade->is_active
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update grade
     */
    public function update(Request $request, $value)
    {
        try {
            $grade = DB::table('grades')->where('value', $value)->first();

            if (!$grade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'label' => 'sometimes|required|string|max:100',
                'description' => 'nullable|string|max:500',
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
            if ($request->has('label')) {
                $updateData['label'] = strip_tags($request->label);
            }
            if ($request->has('description')) {
                $updateData['description'] = $request->description ? strip_tags($request->description) : null;
            }
            if ($request->has('is_active')) {
                $updateData['is_active'] = $request->boolean('is_active');
            }
            $updateData['updated_at'] = now();

            DB::table('grades')
                ->where('value', $value)
                ->update($updateData);

            $updatedGrade = DB::table('grades')->where('value', $value)->first();

            DB::commit();

            Log::info('Grade updated', ['grade_value' => $value]);

            return response()->json([
                'success' => true,
                'message' => 'Grade updated successfully',
                'data' => [
                    'value' => $updatedGrade->value,
                    'label' => $updatedGrade->label,
                    'description' => $updatedGrade->description,
                    'is_active' => (bool) $updatedGrade->is_active
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete grade
     */
    public function destroy($value)
    {
        try {
            DB::beginTransaction();

            $grade = DB::table('grades')->where('value', $value)->first();
            
            if (!$grade) {
                return response()->json([
                    'success' => false,
                    'message' => 'Grade not found'
                ], 404);
            }

            // Check if grade has students or classes
            $studentsCount = DB::table('students')->where('grade', $value)->count();
            $classesCount = DB::table('classes')->where('grade', $value)->count();

            if ($studentsCount > 0 || $classesCount > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete grade with existing students or classes. Please deactivate it instead.'
                ], 400);
            }

            DB::table('grades')->where('value', $value)->delete();

            DB::commit();

            Log::info('Grade deleted', ['grade_value' => $value]);

            return response()->json([
                'success' => true,
                'message' => 'Grade deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete grade error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete grade',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

