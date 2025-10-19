<?php

namespace App\Http\Controllers;

use App\Models\ClassModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClassController extends Controller
{
    /**
     * Get all classes
     */
    public function index(Request $request)
    {
        try {
            $query = ClassModel::with(['branch', 'classTeacher']);

            // Filters
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('grade')) {
                $query->where('grade', $request->grade);
            }

            if ($request->has('section')) {
                $query->where('section', $request->section);
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
                    $q->where('class_name', 'like', '%' . $search . '%')
                      ->orWhere('grade', 'like', '%' . $search . '%')
                      ->orWhere('section', 'like', '%' . $search . '%')
                      ->orWhere('room_number', 'like', '%' . $search . '%');
                });
            }

            $classes = $query->orderBy('grade', 'asc')
                            ->orderBy('section', 'asc')
                            ->get();

            return response()->json([
                'success' => true,
                'data' => $classes,
                'count' => $classes->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get classes error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch classes',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new class
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'grade' => 'required|string|max:20',
                'section' => 'nullable|string|max:10',
                'academic_year' => 'required|string|max:20',
                'class_teacher_id' => 'nullable|exists:users,id',
                'capacity' => 'required|integer|min:1|max:100',
                'room_number' => 'nullable|string|max:50',
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

            // Generate class name
            $className = 'Grade ' . $request->grade;
            if ($request->section) {
                $className .= '-' . $request->section;
            }

            // Check for duplicate
            $exists = ClassModel::where('branch_id', $request->branch_id)
                ->where('grade', $request->grade)
                ->where('section', $request->section)
                ->where('academic_year', $request->academic_year)
                ->exists();

            if ($exists) {
                return response()->json([
                    'success' => false,
                    'message' => 'A class with this grade and section already exists for this branch and academic year'
                ], 400);
            }

            $class = ClassModel::create([
                'branch_id' => $request->branch_id,
                'grade' => strip_tags($request->grade),
                'section' => $request->section ? strip_tags($request->section) : null,
                'class_name' => $className,
                'academic_year' => strip_tags($request->academic_year),
                'class_teacher_id' => $request->class_teacher_id,
                'capacity' => $request->capacity,
                'current_strength' => 0,
                'room_number' => $request->room_number ? strip_tags($request->room_number) : null,
                'description' => $request->description ? strip_tags($request->description) : null,
                'is_active' => $request->boolean('is_active', true)
            ]);

            DB::commit();

            Log::info('Class created', ['class_id' => $class->id]);

            return response()->json([
                'success' => true,
                'message' => 'Class created successfully',
                'data' => $class->load(['branch', 'classTeacher'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create class error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create class',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single class
     */
    public function show($id)
    {
        try {
            $class = ClassModel::with(['branch', 'classTeacher'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $class
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get class error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch class',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update class
     */
    public function update(Request $request, $id)
    {
        try {
            $class = ClassModel::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'grade' => 'sometimes|string|max:20',
                'section' => 'nullable|string|max:10',
                'academic_year' => 'sometimes|string|max:20',
                'class_teacher_id' => 'nullable|exists:users,id',
                'capacity' => 'sometimes|integer|min:1|max:100',
                'room_number' => 'nullable|string|max:50',
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
                'grade', 'section', 'academic_year', 'class_teacher_id', 
                'capacity', 'room_number', 'description', 'is_active'
            ]);

            // Sanitize strings
            foreach (['grade', 'section', 'academic_year', 'room_number', 'description'] as $field) {
                if (isset($updateData[$field])) {
                    $updateData[$field] = strip_tags($updateData[$field]);
                }
            }

            // Update class name if grade or section changed
            if (isset($updateData['grade']) || isset($updateData['section'])) {
                $grade = $updateData['grade'] ?? $class->grade;
                $section = $updateData['section'] ?? $class->section;
                $updateData['class_name'] = 'Grade ' . $grade . ($section ? '-' . $section : '');
            }

            $class->update($updateData);

            DB::commit();

            Log::info('Class updated', ['class_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Class updated successfully',
                'data' => $class->fresh(['branch', 'classTeacher'])
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update class error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update class'
            ], 500);
        }
    }

    /**
     * Delete class
     */
    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $class = ClassModel::findOrFail($id);
            
            // Check if class has students
            if ($class->current_strength > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete class with enrolled students'
                ], 400);
            }

            $class->delete();

            DB::commit();

            Log::info('Class deleted', ['class_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Class deleted successfully'
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Class not found'
            ], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete class error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete class'
            ], 500);
        }
    }

    /**
     * Get available grades with statistics
     */
    public function getGrades(Request $request)
    {
        try {
            // Get grades from the grades table
            $gradesFromDb = DB::table('grades')->orderBy('value', 'asc')->get();
            
            $grades = [];
            $branchId = $request->query('branch_id');
            
            foreach ($gradesFromDb as $gradeRecord) {
                // Get classes for this grade with optional branch filter
                $classesQuery = ClassModel::where('grade', $gradeRecord->value)
                    ->where('is_active', true);
                
                if ($branchId) {
                    $classesQuery->where('branch_id', $branchId);
                }
                
                $classes = $classesQuery->get();
                
                // Get unique sections from both classes and sections tables
                $sectionsFromClasses = $classes->pluck('section')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                
                // Get sections from the sections table for this grade with optional branch filter
                $sectionsTableQuery = DB::table('sections')
                    ->where('grade_level', $gradeRecord->value)
                    ->where('is_active', true)
                    ->whereNull('deleted_at');
                
                if ($branchId) {
                    $sectionsTableQuery->where('branch_id', $branchId);
                }
                
                $sectionsFromSectionsTable = $sectionsTableQuery
                    ->pluck('name')
                    ->filter()
                    ->unique()
                    ->values()
                    ->toArray();
                
                // Merge and get unique sections from both sources
                $sections = collect(array_merge($sectionsFromClasses, $sectionsFromSectionsTable))
                    ->unique()
                    ->values()
                    ->toArray();
                
                // Count total students in this grade with optional branch filter
                $studentsQuery = DB::table('students')
                    ->where('grade', $gradeRecord->value)
                    ->where('student_status', 'Active');
                
                if ($branchId) {
                    $studentsQuery->where('branch_id', $branchId);
                }
                
                $studentsCount = $studentsQuery->count();
                
                $grades[] = [
                    'value' => $gradeRecord->value,
                    'label' => $gradeRecord->label,
                    'description' => $gradeRecord->description ?? null,
                    'students_count' => $studentsCount,
                    'sections' => $sections,
                    'classes_count' => $classes->count(),
                    'is_active' => (bool) $gradeRecord->is_active
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $grades,
                'count' => count($grades)
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
     * Get statistics for a specific grade
     */
    public function getGradeStats($grade)
    {
        try {
            // Get total students in this grade
            $totalStudents = DB::table('students')
                ->where('grade', $grade)
                ->where('student_status', 'Active')
                ->count();
            
            // Get total sections (classes) in this grade
            $totalSections = ClassModel::where('grade', $grade)
                ->where('is_active', true)
                ->count();
            
            // Get teachers teaching this grade
            $totalTeachers = ClassModel::where('grade', $grade)
                ->where('is_active', true)
                ->whereNotNull('class_teacher_id')
                ->distinct('class_teacher_id')
                ->count('class_teacher_id');
            
            // Calculate average attendance for this grade (last 30 days)
            $averageAttendance = DB::table('student_attendance')
                ->where('grade_level', $grade)
                ->where('date', '>=', now()->subDays(30))
                ->where('status', 'Present')
                ->count();
            
            $totalAttendanceRecords = DB::table('student_attendance')
                ->where('grade_level', $grade)
                ->where('date', '>=', now()->subDays(30))
                ->count();
            
            $attendancePercentage = $totalAttendanceRecords > 0 
                ? round(($averageAttendance / $totalAttendanceRecords) * 100, 2) 
                : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'total_students' => $totalStudents,
                    'total_sections' => $totalSections,
                    'total_teachers' => $totalTeachers,
                    'average_attendance' => $attendancePercentage,
                    'pass_percentage' => 0 // Placeholder - implement when exam results are available
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get grade stats error', [
                'grade' => $grade,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch grade statistics',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get available sections (with dynamic filtering by grade and branch)
     */
    public function getSections(Request $request)
    {
        try {
            $grade = $request->query('grade');
            $branchId = $request->query('branch_id');

            // If grade and/or branch specified, return sections from existing classes
            if ($grade || $branchId) {
                $query = ClassModel::select('section')
                    ->whereNotNull('section')
                    ->where('is_active', true)
                    ->distinct();

                if ($grade) {
                    $query->where('grade', $grade);
                }

                if ($branchId) {
                    $query->where('branch_id', $branchId);
                }

                $existingSections = $query->pluck('section')
                    ->filter()
                    ->unique()
                    ->sort()
                    ->values()
                    ->map(function ($section) {
                        return [
                            'value' => $section,
                            'label' => 'Section ' . $section
                        ];
                    });

                // If we found existing sections, return them
                if ($existingSections->isNotEmpty()) {
                    return response()->json([
                        'success' => true,
                        'data' => $existingSections
                    ]);
                }
            }

            // Default: Return all standard sections (A-F)
            $sections = [
                ['value' => 'A', 'label' => 'Section A'],
                ['value' => 'B', 'label' => 'Section B'],
                ['value' => 'C', 'label' => 'Section C'],
                ['value' => 'D', 'label' => 'Section D'],
                ['value' => 'E', 'label' => 'Section E'],
                ['value' => 'F', 'label' => 'Section F'],
            ];

            return response()->json([
                'success' => true,
                'data' => $sections
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
}


