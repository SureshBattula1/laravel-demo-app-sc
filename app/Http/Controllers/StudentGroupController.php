<?php

namespace App\Http\Controllers;

use App\Models\StudentGroup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentGroupController extends Controller
{
    /**
     * Get all student groups
     */
    public function index(Request $request)
    {
        try {
            $query = StudentGroup::with(['branch', 'members']);

            // Filters
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%');
                });
            }

            $groups = $query->orderBy('name', 'asc')->get();

            // Add member count
            $groups->each(function($group) {
                $group->member_count = $group->members()->where('is_active', true)->count();
            });

            return response()->json([
                'success' => true,
                'data' => $groups,
                'count' => $groups->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get student groups error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student groups',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new student group
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:255',
                'code' => 'required|string|max:50|unique:student_groups',
                'type' => 'required|in:Academic,Sports,Cultural,Club',
                'academic_year' => 'required|string|max:20',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $group = StudentGroup::create([
                'branch_id' => $request->branch_id,
                'name' => strip_tags($request->name),
                'code' => strtoupper($request->code),
                'type' => $request->type,
                'academic_year' => $request->academic_year,
                'description' => strip_tags($request->description),
                'is_active' => $request->boolean('is_active', true)
            ]);

            DB::commit();

            Log::info('Student group created', ['group_id' => $group->id]);

            return response()->json([
                'success' => true,
                'message' => 'Student group created successfully',
                'data' => $group->load('branch')
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student group',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single student group
     */
    public function show($id)
    {
        try {
            $group = StudentGroup::with(['branch', 'members.student'])
                ->findOrFail($id);

            $group->member_count = $group->members()->where('is_active', true)->count();

            return response()->json([
                'success' => true,
                'data' => $group
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student group'
            ], 500);
        }
    }

    /**
     * Update student group
     */
    public function update(Request $request, $id)
    {
        try {
            $group = StudentGroup::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:255',
                'code' => 'sometimes|string|max:50|unique:student_groups,code,' . $id,
                'type' => 'sometimes|in:Academic,Sports,Cultural,Club',
                'academic_year' => 'sometimes|string|max:20',
                'description' => 'nullable|string',
                'is_active' => 'sometimes|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only(['name', 'code', 'type', 'academic_year', 'description', 'is_active']);
            
            if (isset($updateData['name'])) $updateData['name'] = strip_tags($updateData['name']);
            if (isset($updateData['code'])) $updateData['code'] = strtoupper($updateData['code']);
            if (isset($updateData['description'])) $updateData['description'] = strip_tags($updateData['description']);

            $group->update($updateData);

            DB::commit();

            Log::info('Student group updated', ['group_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Student group updated successfully',
                'data' => $group->fresh(['branch'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student group'
            ], 500);
        }
    }

    /**
     * Delete student group
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $group = StudentGroup::findOrFail($id);
            $group->delete();

            DB::commit();

            Log::info('Student group deleted', ['group_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Student group deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Student group not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete student group error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student group'
            ], 500);
        }
    }

    /**
     * Add student to group
     */
    public function addMember(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:students,id',
                'role' => 'sometimes|in:Member,Leader',
                'joined_date' => 'sometimes|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $group = StudentGroup::findOrFail($id);

            // Check if already a member
            $exists = DB::table('student_group_members')
                ->where('group_id', $id)
                ->where('student_id', $request->student_id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is already a member of this group'
                ], 400);
            }

            DB::table('student_group_members')->insert([
                'group_id' => $id,
                'student_id' => $request->student_id,
                'joined_date' => $request->joined_date ?? now(),
                'role' => $request->role ?? 'Member',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info('Student added to group', ['group_id' => $id, 'student_id' => $request->student_id]);

            return response()->json([
                'success' => true,
                'message' => 'Student added to group successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Add group member error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to add student to group'
            ], 500);
        }
    }

    /**
     * Remove student from group
     */
    public function removeMember($id, $studentId)
    {
        try {
            DB::table('student_group_members')
                ->where('group_id', $id)
                ->where('student_id', $studentId)
                ->delete();

            Log::info('Student removed from group', ['group_id' => $id, 'student_id' => $studentId]);

            return response()->json([
                'success' => true,
                'message' => 'Student removed from group successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove student from group'
            ], 500);
        }
    }
}

