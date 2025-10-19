<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SectionController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all sections with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $query = Section::with(['branch', 'classTeacher', 'class']);

            // Filters
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('grade_level')) {
                $query->where('grade_level', $request->grade_level);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('name', 'like', '%' . $search . '%')
                      ->orWhere('code', 'like', '%' . $search . '%')
                      ->orWhere('room_number', 'like', '%' . $search . '%');
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'code',
                'name',
                'branch_id',
                'grade_level',
                'capacity',
                'current_strength',
                'room_number',
                'is_active',
                'created_at',
                'updated_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by name asc)
            $sections = $this->paginateAndSort($query, $request, $sortableColumns, 'name', 'asc');

            // Enhance each section with grade details and actual student count
            $sections->getCollection()->transform(function ($section) {
                // Append grade_details accessor data
                $section->append('grade_details');
                // Override current_strength with actual count from students table
                $section->current_strength = $section->actual_strength;
                return $section;
            });

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Sections retrieved successfully',
                'data' => $sections->items(),
                'meta' => [
                    'current_page' => $sections->currentPage(),
                    'per_page' => $sections->perPage(),
                    'total' => $sections->total(),
                    'last_page' => $sections->lastPage(),
                    'from' => $sections->firstItem(),
                    'to' => $sections->lastItem(),
                    'has_more_pages' => $sections->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get sections error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch sections',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new section
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'name' => 'required|string|max:50',
                'code' => 'required|string|max:50|unique:sections',
                'grade_level' => 'nullable|string|max:20',
                'capacity' => 'required|integer|min:1|max:100',
                'room_number' => 'nullable|string|max:50',
                'class_teacher_id' => 'nullable|exists:users,id',
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

            // Check for duplicate
            $exists = Section::where('branch_id', $request->branch_id)
                ->where('name', $request->name)
                ->where('grade_level', $request->grade_level)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A section with this name already exists for this branch and grade'
                ], 400);
            }

            $section = Section::create([
                'branch_id' => $request->branch_id,
                'name' => strtoupper(strip_tags($request->name)),
                'code' => strtoupper(strip_tags($request->code)),
                'grade_level' => $request->grade_level ? strip_tags($request->grade_level) : null,
                'capacity' => $request->capacity,
                'current_strength' => 0,
                'room_number' => $request->room_number ? strip_tags($request->room_number) : null,
                'class_teacher_id' => $request->class_teacher_id,
                'description' => $request->description ? strip_tags($request->description) : null,
                'is_active' => $request->boolean('is_active', true)
            ]);

            DB::commit();

            Log::info('Section created', ['section_id' => $section->id]);

            return response()->json([
                'success' => true,
                'message' => 'Section created successfully',
                'data' => $section->load(['branch', 'classTeacher'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create section error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create section',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single section
     */
    public function show($id)
    {
        try {
            $section = Section::with(['branch', 'classTeacher', 'class'])
                ->findOrFail($id);

            // Append grade details and update current_strength with actual count
            $section->append('grade_details');
            $section->current_strength = $section->actual_strength;

            return response()->json([
                'success' => true,
                'data' => $section
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get section error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch section',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update section
     */
    public function update(Request $request, $id)
    {
        try {
            $section = Section::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|string|max:50',
                'code' => 'sometimes|string|max:50|unique:sections,code,' . $id,
                'grade_level' => 'nullable|string|max:20',
                'capacity' => 'sometimes|integer|min:1|max:100',
                'room_number' => 'nullable|string|max:50',
                'class_teacher_id' => 'nullable|exists:users,id',
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

            $updateData = $request->only([
                'name', 'code', 'grade_level', 'capacity', 'room_number', 
                'class_teacher_id', 'description', 'is_active'
            ]);

            // Sanitize strings
            foreach (['name', 'code', 'grade_level', 'room_number', 'description'] as $field) {
                if (isset($updateData[$field])) {
                    $updateData[$field] = strip_tags($updateData[$field]);
                    if (in_array($field, ['name', 'code'])) {
                        $updateData[$field] = strtoupper($updateData[$field]);
                    }
                }
            }

            $section->update($updateData);

            DB::commit();

            Log::info('Section updated', ['section_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Section updated successfully',
                'data' => $section->fresh(['branch', 'classTeacher'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update section error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update section'
            ], 500);
        }
    }

    /**
     * Delete section
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $section = Section::findOrFail($id);
            
            // Check if section has students
            if ($section->current_strength > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete section with enrolled students'
                ], 400);
            }

            $section->delete();

            DB::commit();

            Log::info('Section deleted', ['section_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Section deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Section not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete section error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete section'
            ], 500);
        }
    }

    /**
     * Toggle section status
     */
    public function toggleStatus($id)
    {
        try {
            $section = Section::findOrFail($id);
            $section->is_active = !$section->is_active;
            $section->save();

            return response()->json([
                'success' => true,
                'message' => 'Section status updated successfully',
                'data' => $section
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update section status'
            ], 500);
        }
    }
}

