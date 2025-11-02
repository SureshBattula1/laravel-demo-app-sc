<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\User;
use App\Models\Teacher;
use App\Models\TeacherAttachment;
use App\Exports\TeachersExport;
use App\Services\PdfExportService;
use App\Services\CsvExportService;
use App\Services\ExportService;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class TeacherController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all teachers with filters and server-side pagination/sorting
     */
    public function index(Request $request)
    {
        try {
            // 🚀 OPTIMIZED: Select only needed columns and limit eager loading
            $query = Teacher::select([
                'id', 'user_id', 'branch_id', 'department_id', 'employee_id',
                'category_type', 'designation', 'gender', 'date_of_birth',
                'joining_date', 'employee_type', 'teacher_status', 'created_at', 'updated_at'
            ])
            ->with([
                'user:id,first_name,last_name,email,phone,is_active',
                'branch:id,name,code',
                'department:id,name'
            ]);

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by department
            if ($request->has('department_id')) {
                $query->where('department_id', $request->department_id);
            }

            // Filter by category type
            if ($request->has('category_type')) {
                $query->where('category_type', $request->category_type);
            }

            // Filter by teacher status
            if ($request->has('teacher_status')) {
                $query->where('teacher_status', $request->teacher_status);
            }

            // Filter by active status (from user relationship)
            if ($request->has('is_active')) {
                $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
                $query->whereHas('user', function($q) use ($isActive) {
                    $q->where('is_active', $isActive);
                });
            }

            // OPTIMIZED Search functionality - removed leading wildcards for better index usage
            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('employee_id', 'like', $search . '%')
                      ->orWhere('designation', 'like', $search . '%')
                      ->orWhereHas('user', function($userQuery) use ($search) {
                          $userQuery->where('first_name', 'like', $search . '%')
                                    ->orWhere('last_name', 'like', $search . '%')
                                    ->orWhere('email', 'like', $search . '%')
                                    ->orWhere('phone', 'like', $search . '%');
                      });
                });
            }

            // OPTIMIZED Filter by specific fields - removed leading wildcards
            if ($request->has('employee_id')) {
                $query->where('employee_id', 'like', $request->employee_id . '%');
            }
            if ($request->has('designation')) {
                $query->where('designation', 'like', $request->designation . '%');
            }
            if ($request->has('gender')) {
                $query->where('gender', $request->gender);
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'employee_id',
                'category_type',
                'designation',
                'branch_id',
                'department_id',
                'teacher_status',
                'created_at',
                'updated_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by employee_id asc)
            $teachers = $this->paginateAndSort($query, $request, $sortableColumns, 'employee_id', 'asc');

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Teachers retrieved successfully',
                'data' => $teachers->items(),
                'meta' => [
                    'current_page' => $teachers->currentPage(),
                    'per_page' => $teachers->perPage(),
                    'total' => $teachers->total(),
                    'last_page' => $teachers->lastPage(),
                    'from' => $teachers->firstItem(),
                    'to' => $teachers->lastItem(),
                    'has_more_pages' => $teachers->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get teachers error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teachers',
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
                    'message' => 'Invalid teacher ID'
                ], 400);
            }

            $teacher = Teacher::with(['user', 'branch', 'department'])->findOrFail($id);
            
            return response()->json([
                'success' => true,
                'data' => $teacher
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get teacher error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teacher'
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                // Basic Information
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'preferred_name' => 'nullable|string|max:255',
                'title' => 'nullable|string|max:10',
                'suffix' => 'nullable|string|max:10',
                'email' => 'required|email|unique:users,email',
                'alternate_email' => 'nullable|email|different:email',
                'phone' => 'nullable|string|max:20',
                'alternate_phone' => 'nullable|string|max:20',
                'whatsapp_number' => 'nullable|string|max:20',
                'landline_number' => 'nullable|string|max:20',
                'password' => 'required|string|min:8',
                'branch_id' => 'required|exists:branches,id',
                'is_active' => 'nullable|boolean',
                
                // Teacher Specific
                'employee_id' => 'required|string|max:50|unique:teachers,employee_id',
                'category_type' => 'required|in:Teaching,Non-Teaching',
                'designation' => 'required|string|max:255',
                'department_id' => 'nullable|exists:departments,id',
                
                // Identity Documents
                'gender' => 'required|in:Male,Female,Other',
                'date_of_birth' => 'required|date|before:today',
                'place_of_birth' => 'nullable|string|max:255',
                'pan_number' => 'required|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
                'aadhaar_number' => 'nullable|string|size:12|regex:/^[0-9]{12}$/',
                'passport_number' => 'nullable|string|max:50',
                'passport_expiry' => 'nullable|date|after:today',
                'driving_license_number' => 'nullable|string|max:50',
                'driving_license_expiry' => 'nullable|date|after:today',
                'voter_id' => 'nullable|string|max:50',
                
                // Enhanced Personal Information
                'nationality' => 'nullable|string|max:100',
                'religion' => 'nullable|string|max:100',
                'caste' => 'nullable|string|max:100',
                'sub_caste' => 'nullable|string|max:100',
                'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                'mother_tongue' => 'nullable|string|max:100',
                'languages_known' => 'nullable|array',
                'handicap_status' => 'nullable|in:None,Physical,Visual,Hearing,Mental,Multiple',
                'handicap_details' => 'nullable|string',
                
                // Family Information
                'father_name' => 'nullable|string|max:255',
                'mother_name' => 'nullable|string|max:255',
                'spouse_name' => 'nullable|string|max:255',
                'spouse_date_of_birth' => 'nullable|date|before:today',
                'spouse_occupation' => 'nullable|string|max:255',
                'spouse_phone' => 'nullable|string|max:20',
                'spouse_email' => 'nullable|email',
                'number_of_children' => 'nullable|integer|min:0|max:20',
                'children_details' => 'nullable|array',
                
                // Address Details
                'current_address' => 'nullable|string',
                'current_city' => 'nullable|string|max:100',
                'current_state' => 'nullable|string|max:100',
                'current_pincode' => 'nullable|string|max:10',
                'current_country' => 'nullable|string|max:100',
                'permanent_address' => 'nullable|string',
                'permanent_city' => 'nullable|string|max:100',
                'permanent_state' => 'nullable|string|max:100',
                'permanent_pincode' => 'nullable|string|max:10',
                'permanent_country' => 'nullable|string|max:100',
                
                // Professional Details
                'joining_date' => 'nullable|date',
                'employee_type' => 'nullable|in:Permanent,Contract,Temporary',
                'employee_type_detail' => 'nullable|in:Full-time,Part-time,Consultant',
                'employment_status' => 'nullable|in:Active,On Leave,Suspended',
                'probation_end_date' => 'nullable|date|after:joining_date',
                'confirmation_date' => 'nullable|date|after:probation_end_date',
                'reporting_manager' => 'nullable|string|max:255',
                'reporting_manager_id' => 'nullable|integer',
                'subordinates' => 'nullable|array',
                
                // Educational Background
                'qualification' => 'nullable|string',
                'educational_qualifications' => 'nullable|array',
                'professional_certifications' => 'nullable|array',
                'training_programs' => 'nullable|array',
                'awards_recognitions' => 'nullable|array',
                'publications' => 'nullable|array',
                'research_projects' => 'nullable|array',
                
                // Skills and Competencies
                'experience_years' => 'nullable|numeric|min:0|max:50',
                'teaching_experience_years' => 'nullable|numeric|min:0|max:50',
                'industry_experience_years' => 'nullable|numeric|min:0|max:50',
                'technical_skills' => 'nullable|array',
                'soft_skills' => 'nullable|array',
                'subject_expertise' => 'nullable|array',
                'teaching_methodologies' => 'nullable|array',
                
                // Health and Medical
                'medical_history' => 'nullable|string',
                'allergies' => 'nullable|string',
                'current_medications' => 'nullable|string',
                'family_doctor_name' => 'nullable|string|max:255',
                'family_doctor_phone' => 'nullable|string|max:20',
                'family_doctor_address' => 'nullable|string',
                'last_medical_checkup' => 'nullable|date|before:today',
                'medical_insurance_details' => 'nullable|string',
                
                // Emergency Contacts
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'emergency_contact_number' => 'nullable|string|max:20',
                'emergency_contact_relation' => 'nullable|string|max:100',
                'emergency_contact_2_name' => 'nullable|string|max:255',
                'emergency_contact_2_phone' => 'nullable|string|max:20',
                'emergency_contact_2_relation' => 'nullable|string|max:100',
                'emergency_contact_2_address' => 'nullable|string',
                
                // Financial Information
                'epf_number' => 'nullable|string|max:50',
                'pf_number' => 'nullable|string|max:50',
                'esi_number' => 'nullable|string|max:50',
                'uan_number' => 'nullable|string|max:50',
                'gratuity_number' => 'nullable|string|max:50',
                'basic_salary' => 'nullable|numeric|min:0',
                'ctc' => 'nullable|numeric|min:0',
                'salary_components' => 'nullable|array',
                'deductions' => 'nullable|array',
                'income_tax_pan' => 'nullable|string|max:20',
                
                // Bank Account Information
                'bank_name' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_ifsc_code' => 'nullable|string|max:15',
                'account_title' => 'nullable|string|max:255',
                'bank_branch_name' => 'nullable|string|max:255',
                
                // Additional Professional Information
                'previous_employers' => 'nullable|array',
                'references' => 'nullable|array',
                'professional_memberships' => 'nullable|string',
                'professional_license' => 'nullable|string',
                
                // Performance and Evaluation
                'performance_reviews' => 'nullable|array',
                'appraisals' => 'nullable|array',
                'goals_objectives' => 'nullable|array',
                'training_needs' => 'nullable|array',
                
                // Additional Information
                'notes' => 'nullable|string',
                'hobbies_interests' => 'nullable|string',
                'volunteer_work' => 'nullable|string',
                'community_involvement' => 'nullable|string',
                'personal_statement' => 'nullable|string',
                'career_objectives' => 'nullable|string',
                'additional_notes' => 'nullable|string',
                
                // Profile Picture - accept as string path (already uploaded via upload endpoint)
                'profile_picture' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Create user first
            $user = User::create([
                'first_name' => strip_tags($request->first_name),
                'last_name' => strip_tags($request->last_name),
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt($request->password),
                'role' => 'Teacher',
                'user_type' => 'Teacher',
                'branch_id' => $request->branch_id,
                'is_active' => $request->is_active ?? true
            ]);
            
            // Assign Teacher role via roles relationship
            $teacherRole = \App\Models\Role::where('slug', 'teacher')->first();
            if ($teacherRole) {
                $user->roles()->attach($teacherRole->id, [
                    'is_primary' => true,
                    'branch_id' => $request->branch_id,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create teacher profile with fields that exist in DB
            $teacherData = $request->only([
                'employee_id', 'category_type', 'designation', 'department_id',
                'first_name', 'middle_name', 'last_name', 'preferred_name', 'title', 'suffix',
                'gender', 'date_of_birth', 'place_of_birth', 'pan_number',
                'aadhaar_number', 'passport_number', 'passport_expiry',
                'driving_license_number', 'driving_license_expiry', 'voter_id',
                'nationality', 'religion', 'caste', 'sub_caste', 'blood_group',
                'mother_tongue', 'languages_known', 'handicap_status',
                'handicap_details', 'father_name', 'mother_name', 'spouse_name',
                'spouse_date_of_birth', 'spouse_occupation', 'spouse_phone',
                'spouse_email', 'number_of_children', 'children_details',
                'email', 'alternate_email', 'phone', 'alternate_phone', 'whatsapp_number',
                'landline_number', 'current_address', 'current_city',
                'current_state', 'current_pincode', 'current_country',
                'permanent_address', 'permanent_city', 'permanent_state',
                'permanent_pincode', 'permanent_country', 'joining_date',
                'employee_type', 'employee_type_detail', 'employment_status',
                'probation_end_date', 'confirmation_date', 'reporting_manager',
                'reporting_manager_id', 'subordinates', 'qualification',
                'educational_qualifications', 'professional_certifications',
                'training_programs', 'awards_recognitions', 'publications',
                'research_projects', 'experience_years', 'teaching_experience_years',
                'industry_experience_years', 'technical_skills', 'soft_skills',
                'subject_expertise', 'teaching_methodologies', 'medical_history',
                'allergies', 'current_medications', 'family_doctor_name',
                'family_doctor_phone', 'family_doctor_address',
                'last_medical_checkup', 'medical_insurance_details',
                'emergency_contact_name', 'emergency_contact_phone',
                'emergency_contact_number', 'emergency_contact_relation',
                'emergency_contact_2_name', 'emergency_contact_2_phone',
                'emergency_contact_2_relation', 'emergency_contact_2_address',
                'epf_number', 'pf_number', 'esi_number', 'uan_number',
                'gratuity_number', 'basic_salary', 'ctc', 'salary_components',
                'deductions', 'income_tax_pan', 'bank_name', 'bank_account_number',
                'bank_ifsc_code', 'account_title', 'bank_branch_name', 'previous_employers',
                'references'
            ]);
            
            // Store fields that don't have columns in extended_profile JSON
            $extendedData = $request->only([
                'professional_memberships', 'professional_license',
                'performance_reviews', 'appraisals', 'goals_objectives',
                'training_needs', 'notes', 'hobbies_interests', 'volunteer_work',
                'community_involvement', 'personal_statement', 'career_objectives',
                'additional_notes'
            ]);
            
            // Only add extended_profile if there's data
            if (!empty(array_filter($extendedData))) {
                $teacherData['extended_profile'] = $extendedData;
            }

            $teacherData['user_id'] = $user->id;
            $teacherData['branch_id'] = $request->branch_id;
            $teacherData['teacher_status'] = 'Active';
            
            // Handle required 'address' field (legacy field, use current_address)
            $teacherData['address'] = $request->current_address ?? $request->permanent_address ?? '';

            $teacher = Teacher::create($teacherData);

            // Update profile completion percentage
            $teacher->updateProfileCompletion();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher created successfully',
                'data' => $teacher->load(['user', 'branch', 'department'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create teacher error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create teacher',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $teacher = Teacher::with(['user', 'branch', 'department'])->findOrFail($id);

            $validator = Validator::make($request->all(), [
                // Basic Information
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'middle_name' => 'nullable|string|max:255',
                'preferred_name' => 'nullable|string|max:255',
                'title' => 'nullable|string|max:10',
                'suffix' => 'nullable|string|max:10',
                'email' => 'sometimes|email|unique:users,email,' . $teacher->user_id,
                'alternate_email' => 'nullable|email|different:email',
                'phone' => 'nullable|string|max:20',
                'alternate_phone' => 'nullable|string|max:20',
                'whatsapp_number' => 'nullable|string|max:20',
                'landline_number' => 'nullable|string|max:20',
                'password' => 'nullable|string|min:8',
                'branch_id' => 'sometimes|exists:branches,id',
                'is_active' => 'nullable|boolean',
                
                // Teacher Specific
                'employee_id' => 'sometimes|string|max:50|unique:teachers,employee_id,' . $id,
                'category_type' => 'sometimes|in:Teaching,Non-Teaching',
                'designation' => 'sometimes|string|max:255',
                'department_id' => 'nullable|exists:departments,id',
                
                // Identity Documents
                'gender' => 'sometimes|in:Male,Female,Other',
                'date_of_birth' => 'sometimes|date|before:today',
                'place_of_birth' => 'nullable|string|max:255',
                'pan_number' => 'sometimes|string|max:20|regex:/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/',
                'aadhaar_number' => 'nullable|string|size:12|regex:/^[0-9]{12}$/',
                'passport_number' => 'nullable|string|max:50',
                'passport_expiry' => 'nullable|date|after:today',
                'driving_license_number' => 'nullable|string|max:50',
                'driving_license_expiry' => 'nullable|date|after:today',
                'voter_id' => 'nullable|string|max:50',
                
                // Enhanced Personal Information
                'nationality' => 'nullable|string|max:100',
                'religion' => 'nullable|string|max:100',
                'caste' => 'nullable|string|max:100',
                'sub_caste' => 'nullable|string|max:100',
                'blood_group' => 'nullable|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                'mother_tongue' => 'nullable|string|max:100',
                'languages_known' => 'nullable|array',
                'handicap_status' => 'nullable|in:None,Physical,Visual,Hearing,Mental,Multiple',
                'handicap_details' => 'nullable|string',
                
                // Family Information
                'father_name' => 'nullable|string|max:255',
                'mother_name' => 'nullable|string|max:255',
                'spouse_name' => 'nullable|string|max:255',
                'spouse_date_of_birth' => 'nullable|date|before:today',
                'spouse_occupation' => 'nullable|string|max:255',
                'spouse_phone' => 'nullable|string|max:20',
                'spouse_email' => 'nullable|email',
                'number_of_children' => 'nullable|integer|min:0|max:20',
                'children_details' => 'nullable|array',
                
                // Address Details
                'current_address' => 'nullable|string',
                'current_city' => 'nullable|string|max:100',
                'current_state' => 'nullable|string|max:100',
                'current_pincode' => 'nullable|string|max:10',
                'current_country' => 'nullable|string|max:100',
                'permanent_address' => 'nullable|string',
                'permanent_city' => 'nullable|string|max:100',
                'permanent_state' => 'nullable|string|max:100',
                'permanent_pincode' => 'nullable|string|max:10',
                'permanent_country' => 'nullable|string|max:100',
                
                // Professional Details
                'joining_date' => 'nullable|date',
                'employee_type' => 'nullable|in:Permanent,Contract,Temporary',
                'employee_type_detail' => 'nullable|in:Full-time,Part-time,Consultant',
                'employment_status' => 'nullable|in:Active,On Leave,Suspended',
                'probation_end_date' => 'nullable|date|after:joining_date',
                'confirmation_date' => 'nullable|date|after:probation_end_date',
                'reporting_manager' => 'nullable|string|max:255',
                'reporting_manager_id' => 'nullable|integer|exists:teachers,id',
                'subordinates' => 'nullable|array',
                
                // Educational Background
                'qualification' => 'nullable|string',
                'educational_qualifications' => 'nullable|array',
                'professional_certifications' => 'nullable|array',
                'training_programs' => 'nullable|array',
                'awards_recognitions' => 'nullable|array',
                'publications' => 'nullable|array',
                'research_projects' => 'nullable|array',
                
                // Skills and Competencies
                'experience_years' => 'nullable|numeric|min:0|max:50',
                'teaching_experience_years' => 'nullable|numeric|min:0|max:50',
                'industry_experience_years' => 'nullable|numeric|min:0|max:50',
                'technical_skills' => 'nullable|array',
                'soft_skills' => 'nullable|array',
                'subject_expertise' => 'nullable|array',
                'teaching_methodologies' => 'nullable|array',
                
                // Health and Medical
                'medical_history' => 'nullable|string',
                'allergies' => 'nullable|string',
                'current_medications' => 'nullable|string',
                'family_doctor_name' => 'nullable|string|max:255',
                'family_doctor_phone' => 'nullable|string|max:20',
                'family_doctor_address' => 'nullable|string',
                'last_medical_checkup' => 'nullable|date|before:today',
                'medical_insurance_details' => 'nullable|string',
                
                // Emergency Contacts
                'emergency_contact_name' => 'nullable|string|max:255',
                'emergency_contact_phone' => 'nullable|string|max:20',
                'emergency_contact_number' => 'nullable|string|max:20',
                'emergency_contact_relation' => 'nullable|string|max:100',
                'emergency_contact_2_name' => 'nullable|string|max:255',
                'emergency_contact_2_phone' => 'nullable|string|max:20',
                'emergency_contact_2_relation' => 'nullable|string|max:100',
                'emergency_contact_2_address' => 'nullable|string',
                
                // Financial Information
                'epf_number' => 'nullable|string|max:50',
                'pf_number' => 'nullable|string|max:50',
                'esi_number' => 'nullable|string|max:50',
                'uan_number' => 'nullable|string|max:50',
                'gratuity_number' => 'nullable|string|max:50',
                'basic_salary' => 'nullable|numeric|min:0',
                'ctc' => 'nullable|numeric|min:0',
                'salary_components' => 'nullable|array',
                'deductions' => 'nullable|array',
                'income_tax_pan' => 'nullable|string|max:20',
                
                // Bank Account Information
                'bank_name' => 'nullable|string|max:255',
                'bank_account_number' => 'nullable|string|max:50',
                'bank_ifsc_code' => 'nullable|string|max:15',
                'account_title' => 'nullable|string|max:255',
                'bank_branch_name' => 'nullable|string|max:255',
                
                // Additional Professional Information
                'previous_employers' => 'nullable|array',
                'references' => 'nullable|array',
                'professional_memberships' => 'nullable|string',
                'professional_license' => 'nullable|string',
                
                // Performance and Evaluation
                'performance_reviews' => 'nullable|array',
                'appraisals' => 'nullable|array',
                'goals_objectives' => 'nullable|array',
                'training_needs' => 'nullable|array',
                
                // Additional Information
                'notes' => 'nullable|string',
                'hobbies_interests' => 'nullable|string',
                'volunteer_work' => 'nullable|string',
                'community_involvement' => 'nullable|string',
                'personal_statement' => 'nullable|string',
                'career_objectives' => 'nullable|string',
                'additional_notes' => 'nullable|string',
                
                // Profile Picture - accept as string path (already uploaded via upload endpoint)
                'profile_picture' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Update user information
            $userData = $request->only(['first_name', 'last_name', 'email', 'phone', 'branch_id', 'is_active']);
            
            foreach (['first_name', 'last_name'] as $field) {
                if (isset($userData[$field])) {
                    $userData[$field] = strip_tags($userData[$field]);
                }
            }

            if ($request->filled('password')) {
                $userData['password'] = bcrypt($request->password);
            }

            $teacher->user->update($userData);

            // Update teacher profile with fields that exist in DB
            $teacherData = $request->only([
                'employee_id', 'category_type', 'designation', 'department_id',
                'first_name', 'middle_name', 'last_name', 'preferred_name', 'title', 'suffix',
                'gender', 'date_of_birth', 'place_of_birth', 'pan_number',
                'aadhaar_number', 'passport_number', 'passport_expiry',
                'driving_license_number', 'driving_license_expiry', 'voter_id',
                'nationality', 'religion', 'caste', 'sub_caste', 'blood_group',
                'mother_tongue', 'languages_known', 'handicap_status',
                'handicap_details', 'father_name', 'mother_name', 'spouse_name',
                'spouse_date_of_birth', 'spouse_occupation', 'spouse_phone',
                'spouse_email', 'number_of_children', 'children_details',
                'email', 'alternate_email', 'phone', 'alternate_phone', 'whatsapp_number',
                'landline_number', 'current_address', 'current_city',
                'current_state', 'current_pincode', 'current_country',
                'permanent_address', 'permanent_city', 'permanent_state',
                'permanent_pincode', 'permanent_country', 'joining_date',
                'employee_type', 'employee_type_detail', 'employment_status',
                'probation_end_date', 'confirmation_date', 'reporting_manager',
                'reporting_manager_id', 'subordinates', 'qualification',
                'educational_qualifications', 'professional_certifications',
                'training_programs', 'awards_recognitions', 'publications',
                'research_projects', 'experience_years', 'teaching_experience_years',
                'industry_experience_years', 'technical_skills', 'soft_skills',
                'subject_expertise', 'teaching_methodologies', 'medical_history',
                'allergies', 'current_medications', 'family_doctor_name',
                'family_doctor_phone', 'family_doctor_address',
                'last_medical_checkup', 'medical_insurance_details',
                'emergency_contact_name', 'emergency_contact_phone',
                'emergency_contact_number', 'emergency_contact_relation',
                'emergency_contact_2_name', 'emergency_contact_2_phone',
                'emergency_contact_2_relation', 'emergency_contact_2_address',
                'epf_number', 'pf_number', 'esi_number', 'uan_number',
                'gratuity_number', 'basic_salary', 'ctc', 'salary_components',
                'deductions', 'income_tax_pan', 'bank_name', 'bank_account_number',
                'bank_ifsc_code', 'account_title', 'bank_branch_name', 'previous_employers',
                'references'
            ]);
            
            // Store fields that don't have columns in extended_profile JSON
            $extendedData = $request->only([
                'professional_memberships', 'professional_license',
                'performance_reviews', 'appraisals', 'goals_objectives',
                'training_needs', 'notes', 'hobbies_interests', 'volunteer_work',
                'community_involvement', 'personal_statement', 'career_objectives',
                'additional_notes'
            ]);
            
            // Merge with existing extended_profile data
            if (!empty(array_filter($extendedData))) {
                $existingExtended = $teacher->extended_profile ?? [];
                $teacherData['extended_profile'] = array_merge($existingExtended, $extendedData);
            }

            // Handle required 'address' field (legacy field, use current_address)
            if ($request->has('current_address')) {
                $teacherData['address'] = $request->current_address;
            } elseif ($request->has('permanent_address') && !isset($teacherData['address'])) {
                $teacherData['address'] = $request->permanent_address;
            }

            $teacher->update($teacherData);

            // Update profile completion percentage
            $teacher->updateProfileCompletion();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher updated successfully',
                'data' => $teacher->fresh(['user', 'branch', 'department'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update teacher error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update teacher',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            DB::beginTransaction();

            $teacher = User::where('role', 'Teacher')->findOrFail($id);
            $teacher->update(['is_active' => false]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher deactivated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete teacher error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete teacher'
            ], 500);
        }
    }

    /**
     * Handle file upload for teacher documents
     */
    private function handleFileUpload(Request $request, Teacher $teacher, string $fieldName, string $documentType): ?string
    {
        if (!$request->hasFile($fieldName)) {
            return null;
        }

        try {
            $file = $request->file($fieldName);
            
            // Generate unique filename
            $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('teachers/' . $teacher->id . '/' . $documentType, $fileName, 'public');
            
            // Deactivate old attachments of same type
            TeacherAttachment::where('teacher_id', $teacher->id)
                ->where('document_type', $documentType)
                ->update(['is_active' => false]);
            
            // Create new attachment record
            TeacherAttachment::create([
                'teacher_id' => $teacher->id,
                'document_type' => $documentType,
                'file_name' => $fileName,
                'file_path' => $filePath,
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
                'original_name' => $file->getClientOriginalName(),
                'is_active' => true,
                'uploaded_by' => auth()->id()
            ]);
            
            return $filePath;
            
        } catch (\Exception $e) {
            Log::error('File upload error', ['error' => $e->getMessage(), 'field' => $fieldName]);
            return null;
        }
    }

    /**
     * Upload profile picture for teacher
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

            $teacher = Teacher::findOrFail($id);
            
            $filePath = $this->handleFileUpload($request, $teacher, 'profile_picture', 'profile_picture');
            
            if ($filePath) {
                // Update teacher profile_picture field with path
                $teacher->update(['profile_picture' => $filePath]);
                
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
     * Export teachers data
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
            $query = $this->buildTeacherQuery($request);

            // Get all matching records (no pagination for export)
            $teachers = $query->get();

            // Transform data for export
            $exportData = collect($teachers)->map(function($teacher) {
                // Flatten nested relationships for easier access
                return [
                    'id' => $teacher->id,
                    'employee_id' => $teacher->employee_id,
                    'first_name' => $teacher->user->first_name ?? '',
                    'last_name' => $teacher->user->last_name ?? '',
                    'email' => $teacher->user->email ?? '',
                    'phone' => $teacher->user->phone ?? '',
                    'category_type' => $teacher->category_type,
                    'designation' => $teacher->designation,
                    'department_name' => $teacher->department->name ?? '',
                    'branch_name' => $teacher->branch->name ?? '',
                    'gender' => $teacher->gender,
                    'date_of_birth' => $teacher->date_of_birth,
                    'joining_date' => $teacher->joining_date,
                    'employee_type' => $teacher->employee_type,
                    'blood_group' => $teacher->blood_group,
                    'pan_number' => $teacher->pan_number,
                    'aadhaar_number' => $teacher->aadhaar_number,
                    'basic_salary' => $teacher->basic_salary,
                    'current_address' => $teacher->current_address,
                    'current_city' => $teacher->current_city,
                    'current_state' => $teacher->current_state,
                    'current_pincode' => $teacher->current_pincode,
                    'emergency_contact_name' => $teacher->emergency_contact_name,
                    'emergency_contact_phone' => $teacher->emergency_contact_phone,
                    'teacher_status' => $teacher->teacher_status,
                    'is_active' => $teacher->user->is_active ?? false,
                    'created_at' => $teacher->created_at,
                ];
            });

            $format = $request->format;
            $columns = $request->columns; // Custom columns if provided

            return match($format) {
                'excel' => $this->exportExcel($exportData, $columns),
                'pdf' => $this->exportPdf($exportData, $columns),
                'csv' => $this->exportCsv($exportData, $columns),
            };

        } catch (\Exception $e) {
            Log::error('Export teachers error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export teachers',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Build teacher query with filters (reusable for index and export)
     */
    protected function buildTeacherQuery(Request $request)
    {
        $query = Teacher::with(['user', 'branch', 'department']);

        // Apply branch filtering - Restrict to accessible branches
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);
        if ($accessibleBranchIds !== 'all') {
            if (!empty($accessibleBranchIds)) {
                $query->whereIn('branch_id', $accessibleBranchIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        // Filter by branch
        if ($request->has('branch_id') && $request->branch_id !== '') {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by department
        if ($request->has('department_id') && $request->department_id !== '') {
            $query->where('department_id', $request->department_id);
        }

        // Filter by category type
        if ($request->has('category_type') && $request->category_type !== '') {
            $query->where('category_type', $request->category_type);
        }

        // OPTIMIZED Filter by designation
        if ($request->has('designation') && $request->designation !== '') {
            $query->where('designation', 'like', $request->designation . '%');
        }

        // Filter by employee type
        if ($request->has('employee_type') && $request->employee_type !== '') {
            $query->where('employee_type', $request->employee_type);
        }

        // Filter by teacher status
        if ($request->has('teacher_status') && $request->teacher_status !== '') {
            $query->where('teacher_status', $request->teacher_status);
        }

        // Filter by gender
        if ($request->has('gender') && $request->gender !== '') {
            $query->where('gender', $request->gender);
        }

        // OPTIMIZED Filter by employee_id
        if ($request->has('employee_id') && $request->employee_id !== '') {
            $query->where('employee_id', 'like', $request->employee_id . '%');
        }

        // Filter by active status
        if ($request->has('is_active')) {
            $query->whereHas('user', function($q) use ($request) {
                $q->where('is_active', $request->is_active);
            });
        }

        // OPTIMIZED Global search across multiple fields - removed leading wildcards
        if ($request->has('search') && $request->search !== '') {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('employee_id', 'like', $searchTerm . '%')
                  ->orWhere('designation', 'like', $searchTerm . '%')
                  ->orWhereHas('user', function($userQuery) use ($searchTerm) {
                      $userQuery->where('first_name', 'like', $searchTerm . '%')
                                ->orWhere('last_name', 'like', $searchTerm . '%')
                                ->orWhere('email', 'like', $searchTerm . '%')
                                ->orWhere('phone', 'like', $searchTerm . '%');
                  })
                  ->orWhereHas('department', function($deptQuery) use ($searchTerm) {
                      $deptQuery->where('name', 'like', $searchTerm . '%');
                  });
            });
        }

        return $query;
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, ?array $columns)
    {
        $export = new TeachersExport($data, $columns);
        $filename = (new ExportService('teachers'))->generateFilename('xlsx');
        
        return Excel::download($export, $filename);
    }

    /**
     * Export to PDF
     */
    protected function exportPdf($data, ?array $columns)
    {
        $pdfService = new PdfExportService('teachers');
        
        if ($columns) {
            $pdfService->setColumns($columns);
        }
        
        // Use A3 paper size for teachers to accommodate more columns
        $pdfService->setPaperSize('a3');
        $pdfService->setOrientation('landscape');
        
        $pdf = $pdfService->generate($data, 'Teachers Report');
        $filename = (new ExportService('teachers'))->generateFilename('pdf');
        
        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    protected function exportCsv($data, ?array $columns)
    {
        $csvService = new CsvExportService('teachers');
        
        if ($columns) {
            $csvService->setColumns($columns);
        }
        
        $filename = (new ExportService('teachers'))->generateFilename('csv');
        
        return $csvService->generate($data, $filename);
    }
}

