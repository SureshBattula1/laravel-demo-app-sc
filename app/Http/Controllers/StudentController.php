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
            // 🚀 OPTIMIZED: Use indexed columns and avoid JSON_OBJECT for better performance
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
                    'students.student_status',
                    'students.created_at',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone',
                    'users.is_active',
                    'branches.id as branch_id_val',
                    'branches.name as branch_name',
                    'branches.code as branch_code'
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
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    // Use FULLTEXT search if available, otherwise use optimized LIKE queries
                    // Remove leading wildcard for better index usage where possible
                    $q->where('users.first_name', 'like', "{$search}%")
                      ->orWhere('users.last_name', 'like', "{$search}%")
                      ->orWhere('users.email', 'like', "{$search}%")
                      ->orWhere('students.admission_number', 'like', "{$search}%")
                      ->orWhere('students.roll_number', 'like', "{$search}%")
                      // Also check for exact matches or contains (for flexibility)
                      ->orWhere(DB::raw('CONCAT(users.first_name, " ", users.last_name)'), 'like', "{$search}%");
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
            
            $student = $query->select(
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
                    'students.stream',
                    'students.date_of_birth',
                    'students.gender',
                    'students.blood_group',
                    'students.current_address',
                    'students.city',
                    'students.state',
                    'students.country',
                    'students.pincode',
                    'students.parent_id',
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
                    'message' => 'Student not found'
                ], 404);
            }

            // Convert to array and ensure all fields are present
            $studentData = (array) $student;
            
            // Parse JSON branch field
            if (isset($studentData['branch']) && is_string($studentData['branch'])) {
                $studentData['branch'] = json_decode($studentData['branch']);
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
     * Create new student
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

            // Create student record
            $studentId = DB::table('students')->insertGetId([
                'user_id' => $user->id,
                'branch_id' => $request->branch_id,
                'admission_number' => $request->admission_number,
                'admission_date' => $request->admission_date,
                'roll_number' => $request->roll_number ?? null,
                'grade' => $request->grade,
                'section' => $request->section ?? null,
                'academic_year' => $request->academic_year,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'blood_group' => $request->blood_group ?? null,
                'current_address' => $request->current_address,
                'permanent_address' => $request->permanent_address ?? $request->current_address,
                'city' => $request->city,
                'state' => $request->state,
                'pincode' => $request->pincode,
                'country' => $request->country ?? 'India',
                'father_name' => $request->father_name,
                'father_phone' => $request->father_phone,
                'father_email' => $request->father_email ?? null,
                'mother_name' => $request->mother_name,
                'mother_phone' => $request->mother_phone ?? null,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'student_status' => 'Active',
                'created_at' => now(),
                'updated_at' => now()
            ]);

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
                'student_status' => 'sometimes|in:Active,Graduated,Left,Suspended,Expelled'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update student
            $updateData = $request->only([
                'section', 'roll_number', 'current_address', 'city', 'state', 'pincode', 
                'student_status', 'blood_group', 'father_phone', 'mother_phone', 
                'emergency_contact_phone'
            ]);
            $updateData['updated_at'] = now();

            DB::table('students')->where('id', $id)->update($updateData);

            // Update user if needed
            if ($request->has('first_name') || $request->has('last_name') || $request->has('phone')) {
                $userUpdate = [];
                if ($request->has('first_name')) $userUpdate['first_name'] = $request->first_name;
                if ($request->has('last_name')) $userUpdate['last_name'] = $request->last_name;
                if ($request->has('phone')) $userUpdate['phone'] = $request->phone;
                
                User::where('id', $student->user_id)->update($userUpdate);
            }

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
            $search = $request->search;
            $query->where(function($q) use ($search) {
                // Use FULLTEXT search if available, otherwise use optimized LIKE queries
                // Remove leading wildcard for better index usage where possible
                $q->where('users.first_name', 'like', "{$search}%")
                  ->orWhere('users.last_name', 'like', "{$search}%")
                  ->orWhere('users.email', 'like', "{$search}%")
                  ->orWhere('students.admission_number', 'like', "{$search}%")
                  ->orWhere('students.roll_number', 'like', "{$search}%")
                  // Also check for exact matches or contains (for flexibility)
                  ->orWhere(DB::raw('CONCAT(users.first_name, " ", users.last_name)'), 'like', "{$search}%");
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
}

