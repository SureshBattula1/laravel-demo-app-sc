<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Models\Student;
use App\Exports\StudentsExport;
use App\Services\PdfExportService;
use App\Services\CsvExportService;
use Maatwebsite\Excel\Facades\Excel;

class StudentController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all students with filters and server-side pagination/sorting
     */
    public function index(Request $request)
    {
        try {
            // 🚀 OPTIMIZED: Select all necessary columns for complete student view
            $query = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->select(
                    'students.*',  // ✅ Select all student columns
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone',
                    'users.is_active',
                    'branches.id as branch_id_val',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                    'grades.label as grade_label'
                );

            // 🔥 APPLY BRANCH FILTERING - This restricts data based on user's branch access
            $user = $request->user();
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('students.branch_id', $accessibleBranchIds);
                } else {
                    // No accessible branches - return empty result
                    $query->whereRaw('1 = 0');
                }
            }

            // Apply filters
            if ($request->has('grade')) {
                $query->where('students.grade', $request->grade);
            }

            if ($request->has('section')) {
                $query->where('students.section', $request->section);
            }

            if ($request->has('status')) {
                $query->where('students.student_status', $request->status);
            }

            if ($request->has('gender')) {
                $query->where('students.gender', $request->gender);
            }

            if ($request->has('branch_id')) {
                $query->where('students.branch_id', $request->branch_id);
            }

            if ($request->has('search')) {
                // Sanitize search input to prevent SQL injection
                $search = strip_tags($request->search);
                $search = preg_replace('/[^\w\s@.-]/', '', $search);
                
                $query->where(function($q) use ($search) {
                    // Use FULLTEXT search if available, otherwise use optimized LIKE queries
                    // Remove leading wildcard for better index usage where possible
                    $q->where('users.first_name', 'like', "{$search}%")
                      ->orWhere('users.last_name', 'like', "{$search}%")
                      ->orWhere('users.email', 'like', "{$search}%")
                      ->orWhere('students.admission_number', 'like', "{$search}%")
                      ->orWhere('students.roll_number', 'like', "{$search}%")
                      // Safe concatenation with parameter binding to prevent SQL injection
                      ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ["{$search}%"]);
                });
            }

            // Define sortable columns (whitelisted for security)
            $sortableColumns = [
                'students.id',
                'students.admission_number',
                'students.roll_number',
                'students.grade',
                'students.section',
                'students.gender',
                'students.student_status',
                'students.created_at',
                'students.admission_date',
                'users.first_name',
                'users.last_name',
                'users.email',
                'branches.name'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by created_at desc)
            $students = $this->paginateAndSort($query, $request, $sortableColumns, 'students.created_at', 'desc');

            // 🚀 OPTIMIZED: Format branch data on backend instead of JSON parsing
            $studentsData = collect($students->items())->map(function($student) {
                $student->branch = [
                    'id' => $student->branch_id_val,
                    'name' => $student->branch_name,
                    'code' => $student->branch_code
                ];
                // Remove temporary fields
                unset($student->branch_id_val, $student->branch_name, $student->branch_code);
                return $student;
            })->toArray();

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Students retrieved successfully',
                'data' => $studentsData,
                'meta' => [
                    'current_page' => $students->currentPage(),
                    'per_page' => $students->perPage(),
                    'total' => $students->total(),
                    'last_page' => $students->lastPage(),
                    'from' => $students->firstItem(),
                    'to' => $students->lastItem(),
                    'has_more_pages' => $students->hasMorePages()
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
     * Get single student by ID
     */
    public function show(Request $request, $id)
    {
        try {
            // Build query
            $query = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->where('students.id', $id);
            
            // 🔥 APPLY BRANCH FILTERING - Prevent access to other branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('students.branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }
            
            // Select all student fields using students.*
            $student = $query->select(
                    'students.*',
                    'grades.label as grade_label',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone',
                    'users.is_active',
                    DB::raw('JSON_OBJECT("id", branches.id, "name", branches.name, "code", branches.code) as branch')
                )
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            // Convert to array and ensure all fields are present
            $studentData = (array) $student;
            
            // Parse JSON branch field
            if (isset($studentData['branch']) && is_string($studentData['branch'])) {
                $studentData['branch'] = json_decode($studentData['branch']);
            }
            
            // Decode JSON fields that are stored as JSON strings
            $jsonFields = ['sibling_details', 'vaccination_records', 'language_preferences', 
                          'hobbies_interests', 'extra_curricular_activities', 'achievements',
                          'sports_participation', 'cultural_activities'];
            foreach ($jsonFields as $field) {
                if (isset($studentData[$field]) && is_string($studentData[$field])) {
                    $decoded = json_decode($studentData[$field], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $studentData[$field] = $decoded;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'data' => $studentData
            ]);

        } catch (\Exception $e) {
            Log::error('Get student error', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get student by user_id (for logged-in student to view their own profile)
     */
    public function getByUserId($userId)
    {
        try {
            $student = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->leftJoin('grades', 'students.grade', '=', 'grades.value')
                ->where('students.user_id', $userId)
                ->select(
                    'students.id',
                    'students.user_id',
                    'students.admission_number',
                    'students.admission_date',
                    'students.roll_number',
                    'students.grade',
                    'grades.label as grade_label',
                    'students.section',
                    'students.academic_year',
                    'students.stream',
                    'students.date_of_birth',
                    'students.gender',
                    'students.blood_group',
                    'students.current_address',
                    'students.city',
                    'students.state',
                    'students.country',
                    'students.pincode',
                    'students.father_name',
                    'students.father_phone',
                    'students.father_email',
                    'students.father_occupation',
                    'students.mother_name',
                    'students.mother_phone',
                    'students.mother_email',
                    'students.mother_occupation',
                    'students.emergency_contact_name',
                    'students.emergency_contact_phone',
                    'students.emergency_contact_relation',
                    'students.previous_school',
                    'students.previous_grade',
                    'students.medical_history',
                    'students.allergies',
                    'students.student_status',
                    'students.created_at',
                    'students.updated_at',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone',
                    'users.is_active',
                    DB::raw('JSON_OBJECT("id", branches.id, "name", branches.name, "code", branches.code) as branch')
                )
                ->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student profile not found'
                ], 404);
            }

            // Convert to array and parse JSON fields
            $studentData = (array) $student;
            
            if (isset($studentData['branch']) && is_string($studentData['branch'])) {
                $studentData['branch'] = json_decode($studentData['branch']);
            }

            return response()->json([
                'success' => true,
                'data' => $studentData
            ]);

        } catch (\Exception $e) {
            Log::error('Get student by user_id error', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student profile',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new student
     */
    /**
     * Store a newly created student
     * 
     * NOTE: This method uses a two-phase validation approach:
     * - Phase 1 (store): Only core/required fields are validated here
     * - Phase 2 (prepareStudentData): Additional 100+ optional fields are handled without validation
     * 
     * This design allows flexible student creation where:
     * - Core fields (name, email, admission details, basic contact) are required during creation
     * - Extended fields (identity documents, detailed addresses, medical info, etc.) can be:
     *   a) Provided during creation (will be saved but not validated)
     *   b) Added later via the update() method (which uses 'sometimes' validation)
     * 
     * This pattern is intentional to support:
     * - Quick student registration with minimal data
     * - Gradual profile completion over time
     * - Bulk imports where some fields may be missing
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'phone' => 'nullable|string|max:20',
                'password' => 'required|string|min:8',
                'branch_id' => 'required|exists:branches,id',
                'admission_number' => 'required|string|unique:students,admission_number',
                'admission_date' => 'required|date',
                'grade' => 'required|string',
                'section' => 'nullable|string',
                'academic_year' => 'required|string',
                'date_of_birth' => 'required|date',
                'gender' => 'required|in:Male,Female,Other',
                'current_address' => 'required|string',
                'city' => 'required|string',
                'state' => 'required|string',
                'pincode' => 'required|string',
                'father_name' => 'required|string',
                'father_phone' => 'required|string',
                'mother_name' => 'required|string',
                'emergency_contact_name' => 'required|string',
                'emergency_contact_phone' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Create user
            $user = User::create([
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($request->password),
                'role' => 'Student',
                'user_type' => 'Student',
                'branch_id' => $request->branch_id,
                'is_active' => true
            ]);
            
            // Assign Student role via roles relationship
            $studentRole = \App\Models\Role::where('slug', 'student')->first();
            if ($studentRole) {
                $user->roles()->attach($studentRole->id, [
                    'is_primary' => true,
                    'branch_id' => $request->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Prepare student data using structured helper method
            $studentData = $this->prepareStudentData($request, $user->id);
            
            // Create student record
            $studentId = DB::table('students')->insertGetId($studentData);

            // Update user with student ID
            $user->update(['user_type_id' => $studentId]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student created successfully',
                'data' => [
                    'student_id' => $studentId,
                    'user_id' => $user->id,
                    'admission_number' => $request->admission_number
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create student error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update student
     */
    public function update(Request $request, $id)
    {
        try {
            $student = DB::table('students')->where('id', $id)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'section' => 'sometimes|string',
                'roll_number' => 'sometimes|string',
                'current_address' => 'sometimes|string',
                'city' => 'sometimes|string',
                'state' => 'sometimes|string',
                'pincode' => 'sometimes|string',
                'student_status' => 'sometimes|in:Active,Graduated,Left,Suspended,Expelled',
                'profile_picture' => 'sometimes|string|nullable' // Allow profile_picture path to be updated
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Get student model for file upload handling
            $studentModel = Student::findOrFail($id);

            // Prepare update data using structured helper method
            $updateData = $this->prepareStudentUpdateData($request);

            // Only update if there's data to update
            if (!empty($updateData)) {
                DB::table('students')->where('id', $id)->update($updateData);
                
                // If profile_picture was updated, also update user's avatar
                if (isset($updateData['profile_picture']) && $studentModel->user) {
                    $studentModel->user->update(['avatar' => $updateData['profile_picture']]);
                }
            }

            // Update user fields if provided
            $this->updateUserFields($request, $student->user_id);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update student error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete student (soft delete)
     */
    public function destroy($id)
    {
        try {
            $student = DB::table('students')->where('id', $id)->first();

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            DB::beginTransaction();

            // Soft delete - mark as inactive
            DB::table('students')
                ->where('id', $id)
                ->update([
                    'student_status' => 'Left',
                    'deleted_at' => now(),
                    'updated_at' => now()
                ]);

            // Deactivate user
            User::where('id', $student->user_id)->update(['is_active' => false]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Student deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete student error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Restore soft-deleted student (reactivate)
     */
    public function restore($id)
    {
        try {
            // Find student with trashed records
            $student = Student::withTrashed()->find($id);

            if (!$student) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student not found'
                ], 404);
            }

            if (!$student->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student is already active'
                ], 400);
            }

            // Restore the student
            $student->restore();

            return response()->json([
                'success' => true,
                'message' => 'Student restored successfully',
                'data' => $student->load('user', 'branch')
            ]);

        } catch (\Exception $e) {
            Log::error('Restore student error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to restore student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Promote students to next grade
     */
    public function promote(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_ids' => 'required|array',
                'student_ids.*' => 'exists:students,id',
                'from_grade' => 'required|string',
                'to_grade' => 'required|string',
                'academic_year' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $promoted = DB::table('students')
                ->whereIn('id', $request->student_ids)
                ->where('grade', $request->from_grade)
                ->update([
                    'grade' => $request->to_grade,
                    'academic_year' => $request->academic_year,
                    'section' => null, // Reset section for new grade
                    'updated_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => "Successfully promoted {$promoted} students to grade {$request->to_grade}",
                'data' => [
                    'promoted_count' => $promoted,
                    'to_grade' => $request->to_grade,
                    'academic_year' => $request->academic_year
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Promote students error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to promote students',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Promote students with fee handling and carry-forward
     */
    public function promoteWithFeeHandling(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_ids' => 'required|array',
                'student_ids.*' => 'exists:students,id',
                'from_grade' => 'required|string',
                'to_grade' => 'required|string',
                'academic_year' => 'required|string',
                'check_eligibility' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $feeCarryForwardService = new \App\Services\FeeCarryForwardService();
            $notificationService = new \App\Services\FeeNotificationService();
            $promotionService = new \App\Services\StudentPromotionService(
                $feeCarryForwardService,
                $notificationService
            );

            $result = $promotionService->promoteStudentsWithFeeHandling(
                $request->student_ids,
                $request->from_grade,
                $request->to_grade,
                $request->academic_year,
                $request->user()->id,
                $request->boolean('check_eligibility', false)
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully promoted {$result['promoted_count']} students with fee carry-forward",
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Promote with fee handling error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to promote students',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Preview promotion impact before executing
     */
    public function previewPromotion(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'student_ids' => 'required|array',
                'student_ids.*' => 'exists:students,id',
                'from_grade' => 'required|string',
                'to_grade' => 'required|string',
                'academic_year' => 'required|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $carryForwardService = new \App\Services\FeeCarryForwardService();
            $previews = [];

            foreach ($request->student_ids as $studentId) {
                $student = \App\Models\Student::with('user')->find($studentId);
                
                if (!$student || $student->grade !== $request->from_grade) {
                    continue;
                }

                $preview = $carryForwardService->getCarryForwardSummary(
                    $student->id, // Pass student id, service will handle both
                    $request->from_grade,
                    $request->to_grade,
                    $student->academic_year ?? $request->academic_year
                );

                $preview['student_id'] = $studentId;
                $student->load('user');
                $preview['student_name'] = ($student->user ? $student->user->first_name . ' ' . $student->user->last_name : 'N/A');
                $previews[] = $preview;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'previews' => $previews,
                    'summary' => [
                        'total_students' => count($previews),
                        'total_pending_amount' => array_sum(array_column($previews, 'total_pending_amount'))
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Preview promotion error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to preview promotion',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get promotion history for a student
     */
    public function getPromotionHistory($id)
    {
        try {
            $student = \App\Models\Student::findOrFail($id);
            
            $history = \App\Models\ClassUpgrade::where('student_id', $student->id)
                ->with('approvedBy')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            Log::error('Get promotion history error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get promotion history',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get student dues with fee_type breakdown
     */
    public function getStudentDues($id)
    {
        try {
            $student = \App\Models\Student::findOrFail($id);
            
            $duesService = new \App\Services\FeeDuesService();
            $result = $duesService->getStudentDues($student->user_id);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Get student dues error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to get student dues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Export students data
     * Supports Excel, PDF, and CSV formats with filtering
     */
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:excel,pdf,csv',
                'columns' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build query with same filters as index method
            $query = $this->buildStudentQuery($request);

            // Get all matching records (respecting filters but not pagination)
            $students = $query->get();

            // Transform data for export
            $exportData = collect($students)->map(function($student) {
                if (isset($student->branch) && is_string($student->branch)) {
                    $branch = json_decode($student->branch);
                    $student->branch_name = $branch->name ?? '';
                } else {
                    $student->branch_name = '';
                }
                return $student;
            });

            $format = $request->format;
            $columns = $request->columns; // Custom columns if provided

            return match($format) {
                'excel' => $this->exportExcel($exportData, $columns),
                'pdf' => $this->exportPdf($exportData, $columns),
                'csv' => $this->exportCsv($exportData, $columns),
            };

        } catch (\Exception $e) {
            Log::error('Export students error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export students',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Build student query with filters (reusable for index and export)
     */
    protected function buildStudentQuery(Request $request)
    {
        $query = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
            ->leftJoin('grades', 'students.grade', '=', 'grades.value')
            ->select(
                'students.id',
                'students.user_id',
                'students.branch_id',
                'students.admission_number',
                'students.admission_date',
                'students.roll_number',
                'students.grade',
                'grades.label as grade_label',
                'students.section',
                'students.academic_year',
                'students.date_of_birth',
                'students.gender',
                'students.blood_group',
                'students.current_address',
                'students.city',
                'students.state',
                'students.pincode',
                'students.father_name',
                'students.father_phone',
                'students.mother_name',
                'students.mother_phone',
                'students.student_status',
                'students.created_at',
                'users.first_name',
                'users.last_name',
                'users.email',
                'users.phone',
                'users.is_active',
                DB::raw('JSON_OBJECT("id", branches.id, "name", branches.name, "code", branches.code) as branch')
            );

        // Apply branch filtering
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $query->whereIn('students.branch_id', $accessibleBranchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Apply filters
        if ($request->has('grade')) {
            $query->where('students.grade', $request->grade);
        }

        if ($request->has('section')) {
            $query->where('students.section', $request->section);
        }

        if ($request->has('status')) {
            $query->where('students.student_status', $request->status);
        }

        if ($request->has('gender')) {
            $query->where('students.gender', $request->gender);
        }

        if ($request->has('branch_id')) {
            $query->where('students.branch_id', $request->branch_id);
        }

        if ($request->has('search')) {
            // Sanitize search input to prevent SQL injection
            $search = strip_tags($request->search);
            $search = preg_replace('/[^\w\s@.-]/', '', $search);
            
            $query->where(function($q) use ($search) {
                // Use FULLTEXT search if available, otherwise use optimized LIKE queries
                // Remove leading wildcard for better index usage where possible
                $q->where('users.first_name', 'like', "{$search}%")
                  ->orWhere('users.last_name', 'like', "{$search}%")
                  ->orWhere('users.email', 'like', "{$search}%")
                  ->orWhere('students.admission_number', 'like', "{$search}%")
                  ->orWhere('students.roll_number', 'like', "{$search}%")
                  // Safe concatenation with parameter binding to prevent SQL injection
                  ->orWhereRaw('CONCAT(users.first_name, " ", users.last_name) LIKE ?', ["{$search}%"]);
            });
        }

        return $query;
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, ?array $columns)
    {
        $export = new StudentsExport($data, $columns);
        $filename = (new \App\Services\ExportService('students'))->generateFilename('xlsx');
        
        return Excel::download($export, $filename);
    }

    /**
     * Export to PDF
     */
    protected function exportPdf($data, ?array $columns)
    {
        $pdfService = new PdfExportService('students');
        
        if ($columns) {
            $pdfService->setColumns($columns);
        }
        
        // Use A3 paper size for students to accommodate more columns
        $pdfService->setPaperSize('a3');
        $pdfService->setOrientation('landscape');
        
        $pdf = $pdfService->generate($data, 'Students Report');
        $filename = (new \App\Services\ExportService('students'))->generateFilename('pdf');
        
        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    protected function exportCsv($data, ?array $columns)
    {
        $csvService = new CsvExportService('students');
        
        if ($columns) {
            $csvService->setColumns($columns);
        }
        
        $filename = (new \App\Services\ExportService('students'))->generateFilename('csv');
        
        return $csvService->generate($data, $filename);
    }
    
    /**
     * Upload profile picture for student
     */
    public function uploadProfilePicture(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'profile_picture' => 'required|image|mimes:jpeg,jpg,png,gif,svg,webp,bmp|max:1024'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $student = Student::findOrFail($id);
            
            $filePath = $this->handleFileUpload($request, $student, 'profile_picture', 'profile_picture');
            
            if ($filePath) {
                // Update student profile_picture field with path
                $student->update(['profile_picture' => $filePath]);
                
                // Also update the user's avatar field
                if ($student->user) {
                    $student->user->update(['avatar' => $filePath]);
                }
                
                return response()->json([
                    'success' => true,
                    'message' => 'Profile picture uploaded successfully',
                    'data' => [
                        'file_path' => $filePath,
                        'file_url' => Storage::url($filePath)
                    ]
                ]);
            }
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture'
            ], 500);
            
        } catch (\Exception $e) {
            Log::error('Upload profile picture error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
    
    /**
     * Handle file upload for student documents
     */
    private function handleFileUpload(Request $request, Student $student, string $fieldName, string $documentType): ?string
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        try {
            $file = $request->file($fieldName);
            
            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('students/' . $student->id . '/' . $documentType, $fileName, 'public');
            
            return $filePath;
            
        } catch (\Exception $e) {
            Log::error('File upload error', ['error' => $e->getMessage(), 'field' => $fieldName]);
            return null;
        }
    }

    /**
     * Prepare student data for creation
     * Structured approach with explicit field mapping
     */
    /**
     * Prepare student data array from request
     * 
     * NOTE: This method handles 100+ optional fields without validation.
     * Fields provided here will be saved to the database if they exist in the request.
     * For proper validation of optional fields, use the update() method which validates
     * fields when they are provided using 'sometimes' rules.
     * 
     * This approach allows:
     * - Flexible student creation with partial data
     * - Bulk imports with varying field completeness
     * - Gradual profile completion
     * 
     * @param Request $request
     * @param int $userId
     * @return array
     */
    private function prepareStudentData(Request $request, int $userId): array
    {
        $data = [
            'user_id' => $userId,
            'branch_id' => $request->branch_id,
            'student_status' => 'Active',
            'created_at' => now(),
            'updated_at' => now()
        ];

        // Admission & Academic Fields
        $data['admission_number'] = $request->admission_number;
        $data['admission_date'] = $request->admission_date;
        $data['admission_type'] = $request->admission_type ?? 'Regular';
        $data['roll_number'] = $request->roll_number ?? null;
        $data['grade'] = $request->grade;
        $data['section'] = $request->section ?? null;
        $data['academic_year'] = $request->academic_year;
        $data['stream'] = $request->stream ?? null;
        $data['elective_subjects'] = $request->elective_subjects ?? null;
        $data['registration_number'] = $request->registration_number ?? null;

        // Personal Information
        $data['date_of_birth'] = $request->date_of_birth;
        $data['gender'] = $request->gender;
        $data['blood_group'] = $request->blood_group ?? null;
        $data['religion'] = $request->religion ?? null;
        $data['nationality'] = $request->nationality ?? null;
        $data['mother_tongue'] = $request->mother_tongue ?? null;
        $data['category'] = $request->category ?? null;
        $data['caste'] = $request->caste ?? null;
        $data['sub_caste'] = $request->sub_caste ?? null;

        // Identity Documents
        $data['aadhaar_number'] = $request->aadhaar_number ?? null;
        $data['pen_number'] = $request->pen_number ?? null;
        $data['birth_certificate_number'] = $request->birth_certificate_number ?? null;
        $data['passport_number'] = $request->passport_number ?? null;
        $data['passport_expiry'] = $request->passport_expiry ?? null;
        $data['student_id_card_number'] = $request->student_id_card_number ?? null;
        $data['voter_id'] = $request->voter_id ?? null;
        $data['ration_card_number'] = $request->ration_card_number ?? null;
        $data['domicile_certificate_number'] = $request->domicile_certificate_number ?? null;
        $data['income_certificate_number'] = $request->income_certificate_number ?? null;
        $data['caste_certificate_number'] = $request->caste_certificate_number ?? null;

        // Address Information
        $data['current_address'] = $request->current_address;
        $data['current_district'] = $request->current_district ?? null;
        $data['current_landmark'] = $request->current_landmark ?? null;
        $data['permanent_address'] = $request->permanent_address ?? $request->current_address;
        $data['permanent_district'] = $request->permanent_district ?? null;
        $data['permanent_landmark'] = $request->permanent_landmark ?? null;
        $data['correspondence_address'] = $request->correspondence_address ?? null;
        $data['city'] = $request->city;
        $data['state'] = $request->state;
        $data['country'] = $request->country ?? 'India';
        $data['pincode'] = $request->pincode;

        // Sibling Information
        $data['number_of_siblings'] = $request->number_of_siblings ?? 0;
        $data['sibling_details'] = $this->encodeJsonField($request->sibling_details);
        $data['sibling_discount_applicable'] = $request->sibling_discount_applicable ?? false;
        $data['sibling_discount_percentage'] = $request->sibling_discount_percentage ?? 0;

        // Father Information
        $data['father_name'] = $request->father_name;
        $data['father_phone'] = $request->father_phone;
        $data['father_email'] = $request->father_email ?? null;
        $data['father_occupation'] = $request->father_occupation ?? null;
        $data['father_qualification'] = $request->father_qualification ?? null;
        $data['father_organization'] = $request->father_organization ?? null;
        $data['father_designation'] = $request->father_designation ?? null;
        $data['father_annual_income'] = $request->father_annual_income ?? 0;
        $data['father_aadhaar'] = $request->father_aadhaar ?? null;

        // Mother Information
        $data['mother_name'] = $request->mother_name;
        $data['mother_phone'] = $request->mother_phone ?? null;
        $data['mother_email'] = $request->mother_email ?? null;
        $data['mother_occupation'] = $request->mother_occupation ?? null;
        $data['mother_qualification'] = $request->mother_qualification ?? null;
        $data['mother_organization'] = $request->mother_organization ?? null;
        $data['mother_designation'] = $request->mother_designation ?? null;
        $data['mother_annual_income'] = $request->mother_annual_income ?? 0;
        $data['mother_aadhaar'] = $request->mother_aadhaar ?? null;

        // Guardian Information
        $data['guardian_name'] = $request->guardian_name ?? null;
        $data['guardian_relation'] = $request->guardian_relation ?? null;
        $data['guardian_phone'] = $request->guardian_phone ?? null;
        $data['guardian_qualification'] = $request->guardian_qualification ?? null;
        $data['guardian_occupation'] = $request->guardian_occupation ?? null;
        $data['guardian_email'] = $request->guardian_email ?? null;
        $data['guardian_address'] = $request->guardian_address ?? null;
        $data['guardian_annual_income'] = $request->guardian_annual_income ?? 0;

        // Emergency Contact
        $data['emergency_contact_name'] = $request->emergency_contact_name;
        $data['emergency_contact_phone'] = $request->emergency_contact_phone;
        $data['emergency_contact_relation'] = $request->emergency_contact_relation ?? null;

        // Transport Details
        $data['transport_required'] = $request->transport_required ?? false;
        $data['transport_route'] = $request->transport_route ?? null;
        $data['pickup_point'] = $request->pickup_point ?? null;
        $data['drop_point'] = $request->drop_point ?? null;
        $data['vehicle_number'] = $request->vehicle_number ?? null;
        $data['pickup_time'] = $request->pickup_time ?? null;
        $data['drop_time'] = $request->drop_time ?? null;
        $data['transport_fee'] = $request->transport_fee ?? 0;

        // Hostel Details
        $data['hostel_required'] = $request->hostel_required ?? false;
        $data['hostel_name'] = $request->hostel_name ?? null;
        $data['hostel_room_number'] = $request->hostel_room_number ?? null;
        $data['hostel_fee'] = $request->hostel_fee ?? 0;

        // Library Information
        $data['library_card_number'] = $request->library_card_number ?? null;
        $data['library_card_issue_date'] = $request->library_card_issue_date ?? null;
        $data['library_card_expiry_date'] = $request->library_card_expiry_date ?? null;

        // Previous Education
        $data['previous_school'] = $request->previous_school ?? null;
        $data['previous_grade'] = $request->previous_grade ?? null;
        $data['previous_school_board'] = $request->previous_school_board ?? null;
        $data['previous_school_address'] = $request->previous_school_address ?? null;
        $data['previous_school_phone'] = $request->previous_school_phone ?? null;
        $data['previous_percentage'] = $request->previous_percentage ?? null;
        $data['transfer_certificate_number'] = $request->transfer_certificate_number ?? $request->tc_number ?? null;
        $data['tc_number'] = $request->tc_number ?? null;
        $data['tc_date'] = $request->tc_date ?? null;
        $data['previous_student_id'] = $request->previous_student_id ?? null;
        $data['medium_of_instruction'] = $request->medium_of_instruction ?? 'English';
        $data['language_preferences'] = $this->encodeJsonField($request->language_preferences);

        // Medical & Health Information
        $data['medical_history'] = $request->medical_history ?? null;
        $data['allergies'] = $request->allergies ?? null;
        $data['medications'] = $request->medications ?? $request->current_medications ?? null;
        $data['height_cm'] = $request->height_cm ?? null;
        $data['weight_kg'] = $request->weight_kg ?? null;
        $data['vision_status'] = $request->vision_status ?? 'Normal';
        $data['hearing_status'] = $request->hearing_status ?? 'Normal';
        $data['chronic_conditions'] = $request->chronic_conditions ?? null;
        $data['current_medications'] = $request->current_medications ?? null;
        $data['medical_insurance'] = $request->medical_insurance ?? false;
        $data['insurance_provider'] = $request->insurance_provider ?? null;
        $data['insurance_policy_number'] = $request->insurance_policy_number ?? null;
        $data['last_health_checkup'] = $request->last_health_checkup ?? null;
        $data['family_doctor_name'] = $request->family_doctor_name ?? null;
        $data['family_doctor_phone'] = $request->family_doctor_phone ?? null;
        $data['vaccination_status'] = $request->vaccination_status ?? 'Complete';
        $data['vaccination_records'] = $this->encodeJsonField($request->vaccination_records);
        $data['special_needs'] = $request->special_needs ?? false;
        $data['special_needs_details'] = $request->special_needs_details ?? null;

        // Fee & Scholarship
        $data['fee_concession_applicable'] = $request->fee_concession_applicable ?? false;
        $data['concession_type'] = $request->concession_type ?? null;
        $data['concession_percentage'] = $request->concession_percentage ?? 0;
        $data['scholarship_name'] = $request->scholarship_name ?? null;
        $data['scholarship_details'] = $request->scholarship_details ?? null;
        $data['economic_status'] = $request->economic_status ?? null;
        $data['family_annual_income'] = $request->family_annual_income ?? 0;

        // Additional Information
        $data['hobbies_interests'] = $this->encodeJsonField($request->hobbies_interests);
        $data['extra_curricular_activities'] = $this->encodeJsonField($request->extra_curricular_activities);
        $data['achievements'] = $this->encodeJsonField($request->achievements);
        $data['sports_participation'] = $this->encodeJsonField($request->sports_participation);
        $data['cultural_activities'] = $this->encodeJsonField($request->cultural_activities);
        $data['behavior_records'] = $request->behavior_records ?? null;
        $data['counselor_notes'] = $request->counselor_notes ?? null;
        $data['special_instructions'] = $request->special_instructions ?? null;

        // Admission & Leaving
        $data['leaving_date'] = $request->leaving_date ?? null;
        $data['leaving_reason'] = $request->leaving_reason ?? null;
        $data['tc_issued_number'] = $request->tc_issued_number ?? null;

        // Other Fields
        $data['remarks'] = $request->remarks ?? null;
        $data['profile_picture'] = $request->profile_picture ?? null;
        $data['parent_id'] = $request->parent_id ?? null;
        $data['admission_status'] = $request->admission_status ?? 'Admitted'; // Default to 'Admitted' for new students
        $data['documents'] = $this->encodeJsonField($request->documents); // Ensure documents is properly encoded as JSON

        return $data;
    }

    /**
     * Prepare student data for update
     * Only includes fields that are provided in the request
     */
    private function prepareStudentUpdateData(Request $request): array
    {
        $data = ['updated_at' => now()];

        // Admission & Academic Fields
        if ($request->has('admission_type')) $data['admission_type'] = $request->admission_type;
        if ($request->has('roll_number')) $data['roll_number'] = $request->roll_number ?: null;
        if ($request->has('section')) $data['section'] = $request->section ?: null;
        if ($request->has('stream')) $data['stream'] = $request->stream ?: null;
        if ($request->has('elective_subjects')) $data['elective_subjects'] = $request->elective_subjects ?: null;
        if ($request->has('registration_number')) $data['registration_number'] = $request->registration_number ?: null;

        // Personal Information
        if ($request->has('blood_group')) $data['blood_group'] = $request->blood_group ?: null;
        if ($request->has('religion')) $data['religion'] = $request->religion ?: null;
        if ($request->has('nationality')) $data['nationality'] = $request->nationality ?: null;
        if ($request->has('mother_tongue')) $data['mother_tongue'] = $request->mother_tongue ?: null;
        if ($request->has('category')) $data['category'] = $request->category ?: null;
        if ($request->has('caste')) $data['caste'] = $request->caste ?: null;
        if ($request->has('sub_caste')) $data['sub_caste'] = $request->sub_caste ?: null;

        // Identity Documents
        if ($request->has('aadhaar_number')) $data['aadhaar_number'] = $request->aadhaar_number ?: null;
        if ($request->has('pen_number')) $data['pen_number'] = $request->pen_number ?: null;
        if ($request->has('birth_certificate_number')) $data['birth_certificate_number'] = $request->birth_certificate_number ?: null;
        if ($request->has('passport_number')) $data['passport_number'] = $request->passport_number ?: null;
        if ($request->has('passport_expiry')) $data['passport_expiry'] = $request->passport_expiry ?: null;
        if ($request->has('student_id_card_number')) $data['student_id_card_number'] = $request->student_id_card_number ?: null;
        if ($request->has('voter_id')) $data['voter_id'] = $request->voter_id ?: null;
        if ($request->has('ration_card_number')) $data['ration_card_number'] = $request->ration_card_number ?: null;
        if ($request->has('domicile_certificate_number')) $data['domicile_certificate_number'] = $request->domicile_certificate_number ?: null;
        if ($request->has('income_certificate_number')) $data['income_certificate_number'] = $request->income_certificate_number ?: null;
        if ($request->has('caste_certificate_number')) $data['caste_certificate_number'] = $request->caste_certificate_number ?: null;

        // Address Information
        if ($request->has('current_address')) $data['current_address'] = $request->current_address;
        if ($request->has('current_district')) $data['current_district'] = $request->current_district ?: null;
        if ($request->has('current_landmark')) $data['current_landmark'] = $request->current_landmark ?: null;
        if ($request->has('permanent_address')) $data['permanent_address'] = $request->permanent_address ?: null;
        if ($request->has('permanent_district')) $data['permanent_district'] = $request->permanent_district ?: null;
        if ($request->has('permanent_landmark')) $data['permanent_landmark'] = $request->permanent_landmark ?: null;
        if ($request->has('correspondence_address')) $data['correspondence_address'] = $request->correspondence_address ?: null;
        if ($request->has('city')) $data['city'] = $request->city;
        if ($request->has('state')) $data['state'] = $request->state;
        if ($request->has('country')) $data['country'] = $request->country;
        if ($request->has('pincode')) $data['pincode'] = $request->pincode;

        // Sibling Information
        if ($request->has('number_of_siblings')) $data['number_of_siblings'] = $request->number_of_siblings ?? 0;
        if ($request->has('sibling_details')) $data['sibling_details'] = $this->encodeJsonField($request->sibling_details);
        if ($request->has('sibling_discount_applicable')) $data['sibling_discount_applicable'] = $request->sibling_discount_applicable ?? false;
        if ($request->has('sibling_discount_percentage')) $data['sibling_discount_percentage'] = $request->sibling_discount_percentage ?? 0;

        // Father Information
        if ($request->has('father_name')) $data['father_name'] = $request->father_name;
        if ($request->has('father_phone')) $data['father_phone'] = $request->father_phone;
        if ($request->has('father_email')) $data['father_email'] = $request->father_email ?: null;
        if ($request->has('father_occupation')) $data['father_occupation'] = $request->father_occupation ?: null;
        if ($request->has('father_qualification')) $data['father_qualification'] = $request->father_qualification ?: null;
        if ($request->has('father_organization')) $data['father_organization'] = $request->father_organization ?: null;
        if ($request->has('father_designation')) $data['father_designation'] = $request->father_designation ?: null;
        if ($request->has('father_annual_income')) $data['father_annual_income'] = $request->father_annual_income ?? 0;
        if ($request->has('father_aadhaar')) $data['father_aadhaar'] = $request->father_aadhaar ?: null;

        // Mother Information
        if ($request->has('mother_name')) $data['mother_name'] = $request->mother_name;
        if ($request->has('mother_phone')) $data['mother_phone'] = $request->mother_phone ?: null;
        if ($request->has('mother_email')) $data['mother_email'] = $request->mother_email ?: null;
        if ($request->has('mother_occupation')) $data['mother_occupation'] = $request->mother_occupation ?: null;
        if ($request->has('mother_qualification')) $data['mother_qualification'] = $request->mother_qualification ?: null;
        if ($request->has('mother_organization')) $data['mother_organization'] = $request->mother_organization ?: null;
        if ($request->has('mother_designation')) $data['mother_designation'] = $request->mother_designation ?: null;
        if ($request->has('mother_annual_income')) $data['mother_annual_income'] = $request->mother_annual_income ?? 0;
        if ($request->has('mother_aadhaar')) $data['mother_aadhaar'] = $request->mother_aadhaar ?: null;

        // Guardian Information
        if ($request->has('guardian_name')) $data['guardian_name'] = $request->guardian_name ?: null;
        if ($request->has('guardian_relation')) $data['guardian_relation'] = $request->guardian_relation ?: null;
        if ($request->has('guardian_phone')) $data['guardian_phone'] = $request->guardian_phone ?: null;
        if ($request->has('guardian_qualification')) $data['guardian_qualification'] = $request->guardian_qualification ?: null;
        if ($request->has('guardian_occupation')) $data['guardian_occupation'] = $request->guardian_occupation ?: null;
        if ($request->has('guardian_email')) $data['guardian_email'] = $request->guardian_email ?: null;
        if ($request->has('guardian_address')) $data['guardian_address'] = $request->guardian_address ?: null;
        if ($request->has('guardian_annual_income')) $data['guardian_annual_income'] = $request->guardian_annual_income ?? 0;

        // Emergency Contact
        if ($request->has('emergency_contact_name')) $data['emergency_contact_name'] = $request->emergency_contact_name;
        if ($request->has('emergency_contact_phone')) $data['emergency_contact_phone'] = $request->emergency_contact_phone;
        if ($request->has('emergency_contact_relation')) $data['emergency_contact_relation'] = $request->emergency_contact_relation ?: null;

        // Transport Details
        if ($request->has('transport_required')) $data['transport_required'] = $request->transport_required ?? false;
        if ($request->has('transport_route')) $data['transport_route'] = $request->transport_route ?: null;
        if ($request->has('pickup_point')) $data['pickup_point'] = $request->pickup_point ?: null;
        if ($request->has('drop_point')) $data['drop_point'] = $request->drop_point ?: null;
        if ($request->has('vehicle_number')) $data['vehicle_number'] = $request->vehicle_number ?: null;
        if ($request->has('pickup_time')) $data['pickup_time'] = $request->pickup_time ?: null;
        if ($request->has('drop_time')) $data['drop_time'] = $request->drop_time ?: null;
        if ($request->has('transport_fee')) $data['transport_fee'] = $request->transport_fee ?? 0;

        // Hostel Details
        if ($request->has('hostel_required')) $data['hostel_required'] = $request->hostel_required ?? false;
        if ($request->has('hostel_name')) $data['hostel_name'] = $request->hostel_name ?: null;
        if ($request->has('hostel_room_number')) $data['hostel_room_number'] = $request->hostel_room_number ?: null;
        if ($request->has('hostel_fee')) $data['hostel_fee'] = $request->hostel_fee ?? 0;

        // Library Information
        if ($request->has('library_card_number')) $data['library_card_number'] = $request->library_card_number ?: null;
        if ($request->has('library_card_issue_date')) $data['library_card_issue_date'] = $request->library_card_issue_date ?: null;
        if ($request->has('library_card_expiry_date')) $data['library_card_expiry_date'] = $request->library_card_expiry_date ?: null;

        // Previous Education
        if ($request->has('previous_school')) $data['previous_school'] = $request->previous_school ?: null;
        if ($request->has('previous_grade')) $data['previous_grade'] = $request->previous_grade ?: null;
        if ($request->has('previous_school_board')) $data['previous_school_board'] = $request->previous_school_board ?: null;
        if ($request->has('previous_school_address')) $data['previous_school_address'] = $request->previous_school_address ?: null;
        if ($request->has('previous_school_phone')) $data['previous_school_phone'] = $request->previous_school_phone ?: null;
        if ($request->has('previous_percentage')) $data['previous_percentage'] = $request->previous_percentage ?: null;
        if ($request->has('transfer_certificate_number')) $data['transfer_certificate_number'] = $request->transfer_certificate_number ?: null;
        if ($request->has('tc_number')) $data['tc_number'] = $request->tc_number ?: null;
        if ($request->has('tc_date')) $data['tc_date'] = $request->tc_date ?: null;
        if ($request->has('previous_student_id')) $data['previous_student_id'] = $request->previous_student_id ?: null;
        if ($request->has('medium_of_instruction')) $data['medium_of_instruction'] = $request->medium_of_instruction ?: null;
        if ($request->has('language_preferences')) $data['language_preferences'] = $this->encodeJsonField($request->language_preferences);

        // Medical & Health Information
        if ($request->has('medical_history')) $data['medical_history'] = $request->medical_history ?: null;
        if ($request->has('allergies')) $data['allergies'] = $request->allergies ?: null;
        if ($request->has('medications')) $data['medications'] = $request->medications ?: null;
        if ($request->has('current_medications')) $data['current_medications'] = $request->current_medications ?: null;
        if ($request->has('height_cm')) $data['height_cm'] = $request->height_cm ?: null;
        if ($request->has('weight_kg')) $data['weight_kg'] = $request->weight_kg ?: null;
        if ($request->has('vision_status')) $data['vision_status'] = $request->vision_status ?: null;
        if ($request->has('hearing_status')) $data['hearing_status'] = $request->hearing_status ?: null;
        if ($request->has('chronic_conditions')) $data['chronic_conditions'] = $request->chronic_conditions ?: null;
        if ($request->has('medical_insurance')) $data['medical_insurance'] = $request->medical_insurance ?? false;
        if ($request->has('insurance_provider')) $data['insurance_provider'] = $request->insurance_provider ?: null;
        if ($request->has('insurance_policy_number')) $data['insurance_policy_number'] = $request->insurance_policy_number ?: null;
        if ($request->has('last_health_checkup')) $data['last_health_checkup'] = $request->last_health_checkup ?: null;
        if ($request->has('family_doctor_name')) $data['family_doctor_name'] = $request->family_doctor_name ?: null;
        if ($request->has('family_doctor_phone')) $data['family_doctor_phone'] = $request->family_doctor_phone ?: null;
        if ($request->has('vaccination_status')) $data['vaccination_status'] = $request->vaccination_status ?: null;
        if ($request->has('vaccination_records')) $data['vaccination_records'] = $this->encodeJsonField($request->vaccination_records);
        if ($request->has('special_needs')) $data['special_needs'] = $request->special_needs ?? false;
        if ($request->has('special_needs_details')) $data['special_needs_details'] = $request->special_needs_details ?: null;

        // Fee & Scholarship
        if ($request->has('fee_concession_applicable')) $data['fee_concession_applicable'] = $request->fee_concession_applicable ?? false;
        if ($request->has('concession_type')) $data['concession_type'] = $request->concession_type ?: null;
        if ($request->has('concession_percentage')) $data['concession_percentage'] = $request->concession_percentage ?? 0;
        if ($request->has('scholarship_name')) $data['scholarship_name'] = $request->scholarship_name ?: null;
        if ($request->has('scholarship_details')) $data['scholarship_details'] = $request->scholarship_details ?: null;
        if ($request->has('economic_status')) $data['economic_status'] = $request->economic_status ?: null;
        if ($request->has('family_annual_income')) $data['family_annual_income'] = $request->family_annual_income ?? 0;

        // Additional Information
        if ($request->has('hobbies_interests')) $data['hobbies_interests'] = $this->encodeJsonField($request->hobbies_interests);
        if ($request->has('extra_curricular_activities')) $data['extra_curricular_activities'] = $this->encodeJsonField($request->extra_curricular_activities);
        if ($request->has('achievements')) $data['achievements'] = $this->encodeJsonField($request->achievements);
        if ($request->has('sports_participation')) $data['sports_participation'] = $this->encodeJsonField($request->sports_participation);
        if ($request->has('cultural_activities')) $data['cultural_activities'] = $this->encodeJsonField($request->cultural_activities);
        if ($request->has('behavior_records')) $data['behavior_records'] = $request->behavior_records ?: null;
        if ($request->has('counselor_notes')) $data['counselor_notes'] = $request->counselor_notes ?: null;
        if ($request->has('special_instructions')) $data['special_instructions'] = $request->special_instructions ?: null;

        // Admission & Leaving
        if ($request->has('student_status')) $data['student_status'] = $request->student_status;
        if ($request->has('admission_status')) $data['admission_status'] = $request->admission_status ?: null;
        if ($request->has('leaving_date')) $data['leaving_date'] = $request->leaving_date ?: null;
        if ($request->has('leaving_reason')) $data['leaving_reason'] = $request->leaving_reason ?: null;
        if ($request->has('tc_issued_number')) $data['tc_issued_number'] = $request->tc_issued_number ?: null;

        // Other Fields
        if ($request->has('remarks')) $data['remarks'] = $request->remarks ?: null;
        if ($request->has('profile_picture')) $data['profile_picture'] = $request->profile_picture ?: null;
        if ($request->has('parent_id')) $data['parent_id'] = $request->parent_id ?: null;
        if ($request->has('documents')) $data['documents'] = $request->documents ?: null;

        return $data;
    }

    /**
     * Update user fields (first_name, last_name, email, phone)
     */
    private function updateUserFields(Request $request, int $userId): void
    {
        $userUpdate = [];
        
        if ($request->has('first_name')) {
            $userUpdate['first_name'] = $request->first_name;
        }
        if ($request->has('last_name')) {
            $userUpdate['last_name'] = $request->last_name;
        }
        if ($request->has('email')) {
            $userUpdate['email'] = $request->email;
        }
        if ($request->has('phone')) {
            $userUpdate['phone'] = $request->phone;
        }
        
        if (!empty($userUpdate)) {
            User::where('id', $userId)->update($userUpdate);
        }
    }

    /**
     * Encode JSON fields (convert arrays to JSON strings)
     */
    private function encodeJsonField($value)
    {
        if (is_array($value)) {
            return json_encode($value);
        }
        if (is_string($value)) {
            // Try to decode first to check if it's already JSON
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $value; // Already JSON string
            }
        }
        return $value ?: null;
    }
}

