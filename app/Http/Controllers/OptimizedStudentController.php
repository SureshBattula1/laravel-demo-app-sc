<?php

namespace App\Http\Controllers;

use App\Services\StudentService;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Resources\StudentResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OptimizedStudentController extends Controller
{
    protected $studentService;

    public function __construct(StudentService $studentService)
    {
        $this->studentService = $studentService;
    }

    /**
     * Get all students - Optimized with service layer
     */
    public function index(Request $request)
    {
        try {
            $filters = $request->only(['grade', 'section', 'status', 'branch_id', 'search']);
            $perPage = $request->get('per_page', 10);

            $students = $this->studentService->getStudents($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => StudentResource::collection($students->items()),
                'meta' => [
                    'current_page' => $students->currentPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                    'last_page' => $students->lastPage()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get students error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch students',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new student - Using Form Request validation
     */
    public function store(StoreStudentRequest $request)
    {
        try {
            $data = $request->validated();
            $result = $this->studentService->createStudent($data);

            return response()->json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => $result
            ], 201);

        } catch (\Exception $e) {
            Log::error('Create student error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Promote students - Using service
     */
    public function promote(Request $request)
    {
        try {
            $request->validate([
                'student_ids' => 'required|array',
                'student_ids.*' => 'exists:students,id',
                'from_grade' => 'required|string',
                'to_grade' => 'required|string',
                'academic_year' => 'required|string'
            ]);

            $promoted = $this->studentService->promoteStudents(
                $request->student_ids,
                $request->from_grade,
                $request->to_grade,
                $request->academic_year
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully promoted {$promoted} students",
                'data' => [
                    'promoted_count' => $promoted,
                    'to_grade' => $request->to_grade,
                    'academic_year' => $request->academic_year
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Promote students error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to promote students',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

