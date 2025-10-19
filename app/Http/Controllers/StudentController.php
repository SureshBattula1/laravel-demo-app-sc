<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentController extends Controller
{
    /**
     * Get all students with filters
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->select(
                    'students.*',
                    'users.first_name',
                    'users.last_name',
                    'users.email',
                    'users.phone',
                    'users.is_active',
                    DB::raw('JSON_OBJECT("id", branches.id, "name", branches.name, "code", branches.code) as branch')
                );

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

            if ($request->has('branch_id')) {
                $query->where('students.branch_id', $request->branch_id);
            }

            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('users.first_name', 'like', "%{$search}%")
                      ->orWhere('users.last_name', 'like', "%{$search}%")
                      ->orWhere('users.email', 'like', "%{$search}%")
                      ->orWhere('students.admission_number', 'like', "%{$search}%")
                      ->orWhere('students.roll_number', 'like', "%{$search}%");
                });
            }

            $perPage = $request->get('per_page', 10);
            $students = $query->orderBy('students.created_at', 'desc')
                             ->paginate($perPage);

            // Parse JSON branch field for each student
            $studentsData = collect($students->items())->map(function($student) {
                if (isset($student->branch) && is_string($student->branch)) {
                    $student->branch = json_decode($student->branch);
                }
                return $student;
            })->toArray();

            return response()->json([
                'success' => true,
                'data' => $studentsData,
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
     * Get single student by ID
     */
    public function show($id)
    {
        try {
            $student = DB::table('students')
                ->join('users', 'students.user_id', '=', 'users.id')
                ->leftJoin('branches', 'students.branch_id', '=', 'branches.id')
                ->where('students.id', $id)
                ->select(
                    'students.*', 
                    'users.first_name', 
                    'users.last_name', 
                    'users.email', 
                    'users.phone',
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
            
            // Parse JSON fields if they exist
            if (isset($studentData['elective_subjects']) && is_string($studentData['elective_subjects'])) {
                $studentData['elective_subjects'] = json_decode($studentData['elective_subjects'], true);
            }
            
            if (isset($studentData['documents']) && is_string($studentData['documents'])) {
                $studentData['documents'] = json_decode($studentData['documents'], true);
            }
            
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
}

