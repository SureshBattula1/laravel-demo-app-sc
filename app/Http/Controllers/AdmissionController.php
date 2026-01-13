<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\AdmissionApplication;
use App\Models\Branch;
use App\Models\Grade;
use App\Models\User;
use App\Models\Role;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class AdmissionController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all admission applications with filters
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('admission_applications')
                ->leftJoin('branches', 'admission_applications.branch_id', '=', 'branches.id')
                ->leftJoin('grades', 'admission_applications.applying_for_grade', '=', 'grades.value')
                ->leftJoin('students', 'admission_applications.student_id', '=', 'students.id')
                ->select(
                    'admission_applications.*',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                    'grades.label as grade_label',
                    'students.admission_number'
                );

            // Branch filtering
            $user = $request->user();
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('admission_applications.branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Apply filters
            if ($request->has('status')) {
                $query->where('admission_applications.application_status', $request->status);
            }

            if ($request->has('grade')) {
                $query->where('admission_applications.applying_for_grade', $request->grade);
            }

            if ($request->has('academic_year')) {
                $query->where('admission_applications.academic_year', $request->academic_year);
            }

            if ($request->has('branch_id')) {
                $query->where('admission_applications.branch_id', $request->branch_id);
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $search = preg_replace('/[^\w\s@.-]/', '', $search);
                
                $query->where(function($q) use ($search) {
                    $q->where('admission_applications.application_number', 'like', "{$search}%")
                      ->orWhere('admission_applications.first_name', 'like', "{$search}%")
                      ->orWhere('admission_applications.last_name', 'like', "{$search}%")
                      ->orWhere('admission_applications.email', 'like', "{$search}%")
                      ->orWhere('admission_applications.phone', 'like', "{$search}%")
                      ->orWhereRaw('CONCAT(admission_applications.first_name, " ", admission_applications.last_name) LIKE ?', ["{$search}%"]);
                });
            }

            // Sorting
            $sortableColumns = [
                'application_number', 'application_date', 'first_name', 'last_name',
                'application_status', 'applying_for_grade', 'academic_year'
            ];

            $query = $this->applySorting($query, $request, $sortableColumns, 'application_date', 'desc');

            // Pagination
            $perPage = $request->get('per_page', 15);
            $applications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $applications->items(),
                'meta' => [
                    'current_page' => $applications->currentPage(),
                    'per_page' => $applications->perPage(),
                    'total' => $applications->total(),
                    'last_page' => $applications->lastPage(),
                    'from' => $applications->firstItem(),
                    'to' => $applications->lastItem(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get admission applications error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch admission applications',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store new admission application
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'academic_year' => 'required|string',
                'applying_for_grade' => 'required|string',
                'first_name' => 'required|string|max:100',
                'last_name' => 'required|string|max:100',
                'date_of_birth' => 'required|date',
                'gender' => 'required|in:Male,Female,Other',
                'email' => 'required|email|max:255',
                'phone' => 'required|string|max:20',
                'father_name' => 'required|string|max:100',
                'father_phone' => 'required|string|max:20',
                'mother_name' => 'required|string|max:100',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Generate application number
            $branch = Branch::findOrFail($request->branch_id);
            $year = date('Y');
            $lastApplication = AdmissionApplication::where('branch_id', $request->branch_id)
                ->whereYear('application_date', $year)
                ->orderBy('id', 'desc')
                ->first();
            
            $sequence = $lastApplication ? (int)substr($lastApplication->application_number, -4) + 1 : 1;
            $applicationNumber = $branch->code . '/ADM/' . $year . '/' . str_pad($sequence, 4, '0', STR_PAD_LEFT);

            $application = AdmissionApplication::create([
                'branch_id' => $request->branch_id,
                'application_number' => $applicationNumber,
                'application_date' => now(),
                'academic_year' => $request->academic_year,
                'applying_for_grade' => $request->applying_for_grade,
                'applying_for_section' => $request->applying_for_section,
                'first_name' => strip_tags($request->first_name),
                'last_name' => strip_tags($request->last_name),
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'blood_group' => $request->blood_group,
                'religion' => $request->religion,
                'nationality' => $request->nationality,
                'category' => $request->category,
                'mother_tongue' => $request->mother_tongue,
                'email' => $request->email,
                'phone' => $request->phone,
                'alternate_phone' => $request->alternate_phone,
                'current_address' => $request->current_address,
                'current_city' => $request->current_city,
                'current_state' => $request->current_state,
                'current_country' => $request->current_country ?? 'India',
                'current_pincode' => $request->current_pincode,
                'permanent_address' => $request->permanent_address ?? $request->current_address,
                'permanent_city' => $request->permanent_city ?? $request->current_city,
                'permanent_state' => $request->permanent_state ?? $request->current_state,
                'permanent_country' => $request->permanent_country ?? $request->current_country ?? 'India',
                'permanent_pincode' => $request->permanent_pincode ?? $request->current_pincode,
                'father_name' => strip_tags($request->father_name),
                'father_phone' => $request->father_phone,
                'father_email' => $request->father_email,
                'father_occupation' => $request->father_occupation,
                'father_qualification' => $request->father_qualification,
                'father_annual_income' => $request->father_annual_income,
                'mother_name' => strip_tags($request->mother_name),
                'mother_phone' => $request->mother_phone,
                'mother_email' => $request->mother_email,
                'mother_occupation' => $request->mother_occupation,
                'mother_qualification' => $request->mother_qualification,
                'mother_annual_income' => $request->mother_annual_income,
                'guardian_name' => $request->guardian_name,
                'guardian_relation' => $request->guardian_relation,
                'guardian_phone' => $request->guardian_phone,
                'guardian_email' => $request->guardian_email,
                'guardian_address' => $request->guardian_address,
                'previous_school' => $request->previous_school,
                'previous_grade' => $request->previous_grade,
                'previous_school_board' => $request->previous_school_board,
                'previous_percentage' => $request->previous_percentage,
                'transfer_certificate_number' => $request->transfer_certificate_number,
                'tc_date' => $request->tc_date,
                'application_status' => 'Applied',
                'application_fee_paid' => $request->boolean('application_fee_paid', false),
                'application_fee_amount' => $request->application_fee_amount,
                'entrance_test_required' => $request->boolean('entrance_test_required', false),
                'interview_required' => $request->boolean('interview_required', false),
                'remarks' => $request->remarks,
                'documents' => $request->documents ?? [],
                'created_by' => $request->user()->id,
            ]);

            DB::commit();

            Log::info('Admission application created', ['application_id' => $application->id]);

            return response()->json([
                'success' => true,
                'message' => 'Admission application created successfully',
                'data' => $application
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create admission application error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create admission application',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single admission application
     */
    public function show($id)
    {
        try {
            $application = AdmissionApplication::with(['branch', 'student', 'createdBy', 'updatedBy'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $application
            ]);

        } catch (\Exception $e) {
            Log::error('Get admission application error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Admission application not found',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 404);
        }
    }

    /**
     * Update admission application
     */
    public function update(Request $request, $id)
    {
        try {
            $application = AdmissionApplication::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:100',
                'last_name' => 'sometimes|required|string|max:100',
                'email' => 'sometimes|required|email|max:255',
                'phone' => 'sometimes|required|string|max:20',
                'entrance_test_score' => 'nullable|numeric|min:0|max:999.99',
                'interview_score' => 'nullable|numeric|min:0|max:999.99',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Prepare update data
            $updateData = $request->only([
                'academic_year', 'applying_for_grade', 'applying_for_section',
                'first_name', 'last_name', 'date_of_birth', 'gender', 'blood_group',
                'religion', 'nationality', 'category', 'mother_tongue',
                'email', 'phone', 'alternate_phone',
                'current_address', 'current_city', 'current_state', 'current_country', 'current_pincode',
                'permanent_address', 'permanent_city', 'permanent_state', 'permanent_country', 'permanent_pincode',
                'father_name', 'father_phone', 'father_email', 'father_occupation', 'father_qualification', 'father_annual_income',
                'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_qualification', 'mother_annual_income',
                'guardian_name', 'guardian_relation', 'guardian_phone', 'guardian_email', 'guardian_address',
                'previous_school', 'previous_grade', 'previous_school_board', 'previous_percentage',
                'transfer_certificate_number', 'tc_date',
                'application_status', 'application_fee_paid', 'application_fee_amount', 'application_fee_payment_date',
                'entrance_test_required', 'entrance_test_date', 'entrance_test_result',
                'interview_required', 'interview_date', 'interview_result',
                'admission_decision', 'admission_decision_date', 'admission_offer_letter', 'admission_validity_date',
                'registration_fee_paid', 'registration_fee_amount', 'registration_fee_payment_date',
                'admission_confirmed', 'admission_confirmed_date', 'student_id', 'remarks', 'documents'
            ]);

            // Validate and sanitize score values
            if ($request->has('entrance_test_score')) {
                $score = $request->entrance_test_score;
                if ($score !== null && $score !== '') {
                    $score = (float) $score;
                    if ($score > 999.99) {
                        $score = 999.99; // Cap at max value
                    }
                    if ($score < 0) {
                        $score = 0;
                    }
                    $updateData['entrance_test_score'] = round($score, 2);
                } else {
                    $updateData['entrance_test_score'] = null;
                }
            }

            if ($request->has('interview_score')) {
                $score = $request->interview_score;
                if ($score !== null && $score !== '') {
                    $score = (float) $score;
                    if ($score > 999.99) {
                        $score = 999.99; // Cap at max value
                    }
                    if ($score < 0) {
                        $score = 0;
                    }
                    $updateData['interview_score'] = round($score, 2);
                } else {
                    $updateData['interview_score'] = null;
                }
            }

            $application->update(array_merge(
                $updateData,
                ['updated_by' => $request->user()->id]
            ));

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Admission application updated successfully',
                'data' => $application->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update admission application error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update admission application',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete admission application
     */
    public function destroy($id)
    {
        try {
            $application = AdmissionApplication::findOrFail($id);
            $application->delete();

            return response()->json([
                'success' => true,
                'message' => 'Admission application deleted successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Delete admission application error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete admission application',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update application status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $application = AdmissionApplication::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:Applied,Shortlisted,Rejected,Admitted,Waitlisted',
                'remarks' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $application->update([
                'application_status' => $request->status,
                'remarks' => $request->remarks,
                'updated_by' => $request->user()->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application status updated successfully',
                'data' => $application->fresh()
            ]);

        } catch (\Exception $e) {
            Log::error('Update application status error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update application status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Convert admission application to student
     */
    public function convertToStudent(Request $request, $id)
    {
        try {
            $application = AdmissionApplication::findOrFail($id);
            
            // Check 1: Verify application status (must be "Admitted" or "Approved")
            if ($application->application_status !== 'Admitted' && $application->admission_decision !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Application must be approved/admitted before converting to student',
                    'current_status' => $application->application_status,
                    'current_decision' => $application->admission_decision
                ], 400);
            }
            
            // Check 2: Verify registration fee is paid
            if (!$application->registration_fee_paid) {
                return response()->json([
                    'success' => false,
                    'message' => 'Registration fee must be paid before converting to student'
                ], 400);
            }
            
            // Check if already converted
            if ($application->student_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This application has already been converted to a student',
                    'data' => ['student_id' => $application->student_id]
                ], 400);
            }
            
            DB::beginTransaction();
            
            // Generate unique admission number for student
            $branch = Branch::findOrFail($application->branch_id);
            $year = date('Y');
            $lastStudent = DB::table('students')
                ->where('branch_id', $application->branch_id)
                ->whereYear('admission_date', $year)
                ->orderBy('id', 'desc')
                ->first();
            
            $sequence = $lastStudent ? (int)substr($lastStudent->admission_number, -4) + 1 : 1;
            $admissionNumber = $branch->code . '/STU/' . $year . '/' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
            
            // Check if email already exists
            $existingUser = User::where('email', $application->email)->first();
            if ($existingUser) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Email already exists in system. Please use a different email.',
                    'existing_user_id' => $existingUser->id
                ], 400);
            }
            
            // Generate default password (can be changed later)
            $defaultPassword = $request->get('password', Str::random(12));
            
            // Step 3: Create User account (User is created FIRST, then Student is linked to it)
            $user = User::create([
                'first_name' => $application->first_name,
                'last_name' => $application->last_name,
                'email' => $application->email,
                'phone' => $application->phone,
                'password' => Hash::make($defaultPassword),
                'role' => 'Student',
                'user_type' => 'Student',
                'branch_id' => $application->branch_id,
                'is_active' => true
            ]);
            
            // Assign Student role
            $studentRole = Role::where('slug', 'student')->first();
            if ($studentRole) {
                $user->roles()->attach($studentRole->id, [
                    'is_primary' => true,
                    'branch_id' => $application->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            // Step 4: Create Student record (Student is created AFTER User, and linked via user_id)
            $studentData = [
            'user_id' => $user->id,
            'branch_id' => $application->branch_id,
            'admission_number' => $admissionNumber,
            'admission_date' => $application->admission_confirmed_date ?? $application->admission_decision_date ?? now(),
            'admission_type' => 'Regular',
            'grade' => $application->applying_for_grade,
            'section' => $application->applying_for_section,
            'academic_year' => $application->academic_year,
            'date_of_birth' => $application->date_of_birth,
            'gender' => $application->gender,
            'blood_group' => $application->blood_group,
            'religion' => $application->religion,
            'nationality' => $application->nationality ?? 'Indian',
            'mother_tongue' => $application->mother_tongue,
            'category' => $application->category,
            'current_address' => $application->current_address,
            'city' => $application->current_city,
            'state' => $application->current_state,
            'country' => $application->current_country ?? 'India',
            'pincode' => $application->current_pincode,
            'permanent_address' => $application->permanent_address ?? $application->current_address,
            'father_name' => $application->father_name,
            'father_phone' => $application->father_phone,
            'father_email' => $application->father_email,
            'father_occupation' => $application->father_occupation,
            'father_qualification' => $application->father_qualification,
            'mother_name' => $application->mother_name,
            'mother_phone' => $application->mother_phone,
            'mother_email' => $application->mother_email,
            'mother_occupation' => $application->mother_occupation,
            'mother_qualification' => $application->mother_qualification,
            'guardian_name' => $application->guardian_name,
            'guardian_relation' => $application->guardian_relation,
            'guardian_phone' => $application->guardian_phone,
            'emergency_contact_name' => $application->guardian_name ?? $application->father_name ?? $application->mother_name,
            'emergency_contact_phone' => $application->guardian_phone ?? $application->father_phone ?? $application->mother_phone,
            'emergency_contact_relation' => $application->guardian_relation ?? 'Father',
            'previous_school' => $application->previous_school,
            'previous_grade' => $application->previous_grade,
            'student_status' => 'Active',
            'admission_status' => 'Admitted',
            'created_at' => now(),
            'updated_at' => now()
            ];
            
            $studentId = DB::table('students')->insertGetId($studentData);
            
            // Link user to student (user_type_id points to student record)
            $user->update(['user_type_id' => $studentId]);
            
            // Step 5: Link application to student
            // Step 6: Update application status
            $application->update([
                'student_id' => $studentId,
                'application_status' => 'Admitted',
                'admission_confirmed' => true,
                'admission_confirmed_date' => now(),
                'updated_by' => $request->user()->id
            ]);
            
            DB::commit();
            
            Log::info('Admission application converted to student', [
                'application_id' => $application->id,
                'student_id' => $studentId,
                'user_id' => $user->id,
                'admission_number' => $admissionNumber
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Admission application successfully converted to student',
                'data' => [
                    'student_id' => $studentId,
                    'user_id' => $user->id,
                    'admission_number' => $admissionNumber,
                    'email' => $user->email,
                    'default_password' => app()->environment('local') ? $defaultPassword : null // Only return in development
                ]
            ], 201);
        
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Convert to student error', [
                'application_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to convert application to student',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Export admission applications
     */
    public function export(Request $request)
    {
        try {
            $query = AdmissionApplication::query();

            // Apply same filters as index
            if ($request->has('status')) {
                $query->where('application_status', $request->status);
            }

            if ($request->has('grade')) {
                $query->where('applying_for_grade', $request->grade);
            }

            if ($request->has('academic_year')) {
                $query->where('academic_year', $request->academic_year);
            }

            $applications = $query->with(['branch', 'student'])->get();

            return response()->json([
                'success' => true,
                'data' => $applications,
                'count' => $applications->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Export admission applications error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export admission applications',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}

