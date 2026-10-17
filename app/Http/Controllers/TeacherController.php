<?php

namespace App\Http\Controllers;

use App\Exports\TeachersExport;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Branch;
use App\Models\Teacher;
use App\Models\TeacherAttachment;
use App\Models\User;
use App\Services\CsvExportService;
use App\Services\ExportService;
use App\Services\PdfExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class TeacherController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all teachers with filters and server-side pagination/sorting
     */
    public function index(Request $request)
    {
        try {
            // 🚀 OPTIMIZED: Select only columns needed for teacher list view
            $query = Teacher::withTrashed()->select([
                'id',
                'user_id',
                'branch_id',
                'department_id',
                'employee_id',
                'category_type',
                'designation',
                'gender',
                'joining_date',
                'teacher_status',
                'blood_group',
                'religion',
                'nationality',
                'qualification',
                'experience_years',
                'class_teacher_of_grade',
                'class_teacher_of_section',
                // NOTE: aadhar_number, pan_number and basic_salary are intentionally NOT
                // selected for the list — they are sensitive PII/financial fields and belong
                // only on the detail/View screen, not in a list payload.
                'city',
                'state',
                'emergency_contact_name',
                'emergency_contact_phone',
                'created_at',
                'updated_at',
                'deleted_at',
            ])
                ->with([
                    'user:id,first_name,last_name,email,phone,is_active',
                    'branch:id,name,code',
                    'department:id,name',  // ✅ Removed 'code' - doesn't exist in departments table
                ]);

            // 🔥 APPLY SCHOOL FILTERING - School-level isolation
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            $user = $request->user();

            // 🔒 A Teacher may only ever see their OWN record (own-record isolation)
            if ($user && $user->role === 'Teacher') {
                $query->where('user_id', $user->id);
            }

            if ($user && $user->role === 'SuperAdmin' && ! empty($user->company_id) && ! $request->filled('branch_id')) {
                // SuperAdmin company context: filter by company via SQL subquery (fast, avoids big whereIn lists).
                $query->whereIn('branch_id', function ($q) use ($user) {
                    $q->select('branches.id')
                        ->from('branches')
                        ->join('schools', 'branches.school_id', '=', 'schools.id')
                        ->where('schools.company_id', (int) $user->company_id)
                        ->whereNull('schools.deleted_at')
                        ->whereNull('branches.deleted_at');
                });
            } elseif ($accessibleBranchIds !== 'all') {
                if (! empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            // Filter by branch
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            // Filter by class teacher grade / section (admin list filters)
            if ($request->filled('grade')) {
                $query->where('class_teacher_of_grade', $request->grade);
            }
            if ($request->filled('section')) {
                $query->where('class_teacher_of_section', $request->section);
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
                $query->whereHas('user', function ($q) use ($isActive) {
                    $q->where('is_active', $isActive);
                });
            }

            if ($request->filled('email')) {
                $email = strip_tags($request->email);
                $query->whereHas('user', function ($userQuery) use ($email) {
                    $userQuery->where('email', 'like', "{$email}%");
                });
            }

            // OPTIMIZED Search functionality - removed leading wildcards for better index usage
            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('employee_id', 'like', $search.'%')
                        ->orWhere('designation', 'like', $search.'%')
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('first_name', 'like', $search.'%')
                                ->orWhere('last_name', 'like', $search.'%')
                                ->orWhere('email', 'like', $search.'%')
                                ->orWhere('phone', 'like', $search.'%');
                        });
                });
            }

            // OPTIMIZED Filter by specific fields - removed leading wildcards
            if ($request->has('employee_id')) {
                $query->where('employee_id', 'like', $request->employee_id.'%');
            }
            if ($request->has('designation')) {
                $query->where('designation', 'like', $request->designation.'%');
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
                'updated_at',
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
                    'has_more_pages' => $teachers->hasMorePages(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('Get teachers error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teachers',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            if (! is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid teacher ID',
                ], 400);
            }

            $teacher = Teacher::with(['user', 'branch', 'department'])->findOrFail($id);

            // 🔒 A Teacher may only ever view their OWN record (own-record isolation)
            $authUser = auth()->user();
            if ($authUser && $authUser->role === 'Teacher' && (int) $teacher->user_id !== (int) $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $teacher,
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Teacher not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Get teacher error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch teacher',
            ], 500);
        }
    }

    /**
     * Store a newly created teacher/staff member
     * Aligned with StudentController architecture:
     * - Phase 1: Core fields validated
     * - Phase 2 (prepareTeacherData): Extended 80+ fields normalized and persisted
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'branch_id' => 'required|exists:branches,id',
                'employee_id' => 'required|string|max:50|unique:teachers,employee_id',
                'category_type' => 'required|in:Teaching,Staff,Account,Non-Teaching',
                'designation' => 'required|string|max:255',
                'gender' => 'required|in:Male,Female,Other',
                'date_of_birth' => 'required|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Map category_type to user role: Teaching→Teacher, Staff/Non-Teaching→Staff, Account→Accountant
            $roleMap = [
                'Teaching' => 'Teacher',
                'Staff' => 'Staff',
                'Non-Teaching' => 'Staff',
                'Account' => 'Accountant',
            ];
            $userRole = $roleMap[$request->category_type] ?? 'Teacher';
            $roleSlugMap = [
                'Teacher' => 'teacher',
                'Staff' => 'staff',
                'Accountant' => 'accountant',
            ];
            $roleSlug = $roleSlugMap[$userRole] ?? 'teacher';

            // Create user
            $user = User::create([
                'first_name' => strip_tags((string) $request->first_name),
                'last_name' => strip_tags((string) $request->last_name),
                'email' => $request->email,
                'phone' => $request->phone ?: null,
                'password' => bcrypt($request->password),
                'role' => $userRole,
                'user_type' => 'Teacher',
                'branch_id' => $request->branch_id,
                'avatar' => $request->profile_picture ?: null,
                'is_active' => $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true,
            ]);

            // Assign role via user_roles relationship
            $role = \App\Models\Role::where('slug', $roleSlug)->first();
            if ($role) {
                $user->roles()->attach($role->id, [
                    'is_primary' => true,
                    'branch_id' => $request->branch_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Prepare all teacher data using structured helper
            $teacherData = $this->prepareTeacherData($request, $user->id);

            // Create teacher record
            $teacher = Teacher::create($teacherData);

            // Link user with teacher ID
            $user->update(['user_type_id' => $teacher->id]);

            // Update profile completion percentage
            $teacher->updateProfileCompletion();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Teacher created successfully',
                'data' => $teacher->fresh()->load(['user', 'branch', 'department']),
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create teacher error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create teacher',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Ensure JSON body is merged for PUT requests
            $content = $request->getContent();
            if (! empty($content) && (str_contains($request->header('Content-Type', ''), 'application/json') || $request->isJson())) {
                $decoded = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $request->merge($decoded);
                }
            }

            $teacher = Teacher::with(['user', 'branch', 'department'])->findOrFail($id);

            // 🔒 A Teacher may only ever update their OWN record (own-record isolation)
            $authUser = $request->user();
            if ($authUser && $authUser->role === 'Teacher' && (int) $teacher->user_id !== (int) $authUser->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|string|max:255',
                'last_name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,'.$teacher->user_id,
                'employee_id' => 'sometimes|string|max:50|unique:teachers,employee_id,'.$id,
                'category_type' => 'sometimes|in:Teaching,Staff,Account,Non-Teaching',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            // Update user record if user fields are present
            $userData = [];
            if ($request->has('first_name')) {
                $userData['first_name'] = strip_tags((string) $request->first_name);
            }
            if ($request->has('last_name')) {
                $userData['last_name'] = strip_tags((string) $request->last_name);
            }
            if ($request->has('email')) {
                $userData['email'] = $request->email;
            }
            if ($request->has('phone')) {
                $userData['phone'] = $request->phone;
            }
            if ($request->has('branch_id')) {
                $userData['branch_id'] = $request->branch_id;
            }
            if ($request->has('is_active')) {
                $userData['is_active'] = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            }
            if ($request->filled('password')) {
                $userData['password'] = bcrypt($request->password);
            }
            if ($request->has('profile_picture')) {
                $userData['avatar'] = $request->profile_picture;
            }

            // Handle category_type to role mapping
            $newRole = null;
            if ($request->has('category_type')) {
                $categoryType = trim((string) $request->input('category_type'));
                $categoryToRole = [
                    'Teaching' => 'Teacher',
                    'Staff' => 'Staff',
                    'Non-Teaching' => 'Staff',
                    'Account' => 'Accountant',
                ];
                $newRole = $categoryToRole[$categoryType] ?? ($teacher->user ? $teacher->user->role : 'Teacher');
                $userData['role'] = $newRole;
            }

            if ($teacher->user && ! empty($userData)) {
                $teacher->user->update($userData);
            }

            // Sync user_roles when category_type (and thus role) changes
            if ($newRole !== null && $teacher->user) {
                $roleSlugMap = [
                    'Teacher' => 'teacher',
                    'Staff' => 'staff',
                    'Accountant' => 'accountant',
                ];
                $newRoleSlug = $roleSlugMap[$newRole] ?? 'teacher';
                $roleModel = \App\Models\Role::where('slug', $newRoleSlug)->first();
                if ($roleModel) {
                    $teacher->user->roles()->sync([$roleModel->id => [
                        'is_primary' => true,
                        'branch_id' => $teacher->user->branch_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]]);
                }
                DB::table('users')->where('id', $teacher->user->id)->update(['role' => $newRole]);
            }

            // Prepare update data for teacher table
            $teacherData = $this->prepareTeacherUpdateData($request, $teacher);

            if (! empty($teacherData)) {
                $teacher->update($teacherData);
            }

            // Update profile completion percentage
            $teacher->updateProfileCompletion();

            DB::commit();

            // Return fresh instance with relations
            $freshTeacher = Teacher::with(['user', 'branch', 'department'])->find($teacher->id);

            return response()->json([
                'success' => true,
                'message' => 'Teacher updated successfully',
                'data' => $freshTeacher,
            ]);

        } catch (\Exception $e) {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            Log::error('Teacher update error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update teacher',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Prepare full teacher data for creation (Phase 2)
     */
    private function prepareTeacherData(Request $request, int $userId): array
    {
        $branch = Branch::find($request->branch_id);
        $schoolId = $branch ? $branch->school_id : null;

        $data = [
            'user_id' => $userId,
            'branch_id' => $request->branch_id,
            'school_id' => $schoolId ?? $this->getCurrentSchoolId($request),
            'department_id' => $request->department_id ?: null,
            'reporting_manager_id' => $request->reporting_manager_id ?: null,
            'reporting_manager' => $request->reporting_manager ?: null,
            'employee_id' => $request->employee_id,
            'category_type' => $request->category_type ?? 'Teaching',
            'designation' => $request->designation,
            'employee_type' => $request->employee_type ?? 'Permanent',
            'employee_type_detail' => $request->employee_type_detail ?? 'Full-time',
            'employment_status' => $request->employment_status ?? 'Active',
            'teacher_status' => $request->teacher_status ?? 'Active',
            'joining_date' => $this->normalizeDate($request->joining_date),
            'leaving_date' => $this->normalizeDate($request->leaving_date),
            'probation_end_date' => $this->normalizeDate($request->probation_end_date),
            'confirmation_date' => $this->normalizeDate($request->confirmation_date),

            // Identity & Personal Information
            'middle_name' => $request->middle_name ? strip_tags((string) $request->middle_name) : null,
            'preferred_name' => $request->preferred_name ? strip_tags((string) $request->preferred_name) : null,
            'title' => $request->title ?: null,
            'suffix' => $request->suffix ?: null,
            'gender' => $request->gender,
            'date_of_birth' => $this->normalizeDate($request->date_of_birth),
            'place_of_birth' => $request->place_of_birth ?: null,
            'blood_group' => $request->blood_group ?: null,
            'religion' => $request->religion ?: null,
            'nationality' => ! empty(trim((string) ($request->nationality ?? ''))) ? trim($request->nationality) : 'Indian',
            'caste' => $request->caste ?: null,
            'sub_caste' => $request->sub_caste ?: null,
            'mother_tongue' => $request->mother_tongue ?: null,
            'languages_known' => $this->encodeJsonField($request->languages_known),
            'handicap_status' => $request->handicap_status ?: 'None',
            'handicap_details' => $request->handicap_details ?: null,

            // Identity Documents
            'pan_number' => $request->pan_number ? strtoupper(trim((string) $request->pan_number)) : null,
            'aadhaar_number' => $request->aadhaar_number ?: $request->aadhar_number ?: null,
            'aadhar_number' => $request->aadhaar_number ?: $request->aadhar_number ?: null,
            'passport_number' => $request->passport_number ?: null,
            'passport_expiry' => $this->normalizeDate($request->passport_expiry),
            'driving_license_number' => $request->driving_license_number ?: null,
            'driving_license_expiry' => $this->normalizeDate($request->driving_license_expiry),
            'voter_id' => $request->voter_id ?: null,

            // Family Information
            'father_name' => $request->father_name ?: null,
            'mother_name' => $request->mother_name ?: null,
            'spouse_name' => $request->spouse_name ?: null,
            'spouse_date_of_birth' => $this->normalizeDate($request->spouse_date_of_birth),
            'spouse_occupation' => $request->spouse_occupation ?: null,
            'spouse_phone' => $request->spouse_phone ?: null,
            'spouse_email' => $request->spouse_email ?: null,
            'number_of_children' => $request->filled('number_of_children') ? (int) $request->number_of_children : 0,
            'children_details' => $this->encodeJsonField($request->children_details),

            // Contact & Address
            'alternate_email' => $request->alternate_email ?: null,
            'alternate_phone' => $request->alternate_phone ?: null,
            'whatsapp_number' => $request->whatsapp_number ?: null,
            'landline_number' => $request->landline_number ?: null,
            'current_address' => $request->current_address ?: $request->address ?: null,
            'current_city' => $request->current_city ?: $request->city ?: null,
            'current_state' => $request->current_state ?: $request->state ?: null,
            'current_pincode' => $request->current_pincode ?: $request->pincode ?: null,
            'current_country' => $request->current_country ?: 'India',
            'permanent_address' => $request->permanent_address ?: $request->current_address ?: $request->address ?: null,
            'permanent_city' => $request->permanent_city ?: $request->current_city ?: $request->city ?: null,
            'permanent_state' => $request->permanent_state ?: $request->current_state ?: $request->state ?: null,
            'permanent_pincode' => $request->permanent_pincode ?: $request->current_pincode ?: $request->pincode ?: null,
            'permanent_country' => $request->permanent_country ?: 'India',
            'city' => $request->current_city ?: $request->city ?: null,
            'state' => $request->current_state ?: $request->state ?: null,
            'pincode' => $request->current_pincode ?: $request->pincode ?: null,

            // Professional & Educational
            'qualification' => is_array($request->qualification) ? json_encode($request->qualification) : ($request->qualification ?: null),
            'educational_qualifications' => $this->encodeJsonField($request->educational_qualifications),
            'professional_certifications' => $this->encodeJsonField($request->professional_certifications),
            'training_programs' => $this->encodeJsonField($request->training_programs),
            'awards_recognitions' => $this->encodeJsonField($request->awards_recognitions),
            'publications' => $this->encodeJsonField($request->publications),
            'research_projects' => $this->encodeJsonField($request->research_projects),
            'specialization' => $request->specialization ?: null,
            'registration_number' => $request->registration_number ?: null,
            'salary_grade' => $request->salary_grade ?: null,
            'subordinates' => $this->encodeJsonField($request->subordinates),

            // Teaching Assignment
            'subjects' => $this->encodeJsonField($request->subjects),
            'classes_assigned' => $this->encodeJsonField($request->classes_assigned),
            'is_class_teacher' => filter_var($request->is_class_teacher, FILTER_VALIDATE_BOOLEAN),
            'class_teacher_of_grade' => $request->class_teacher_of_grade ?: null,
            'class_teacher_of_section' => $request->class_teacher_of_section ?: null,

            // Skills & Competencies
            'experience_years' => $request->filled('experience_years') ? (float) $request->experience_years : 0,
            'teaching_experience_years' => $request->filled('teaching_experience_years') ? (float) $request->teaching_experience_years : 0,
            'industry_experience_years' => $request->filled('industry_experience_years') ? (float) $request->industry_experience_years : 0,
            'technical_skills' => $this->encodeJsonField($request->technical_skills),
            'soft_skills' => $this->encodeJsonField($request->soft_skills),
            'subject_expertise' => $this->encodeJsonField($request->subject_expertise),
            'teaching_methodologies' => $this->encodeJsonField($request->teaching_methodologies),

            // Health & Medical
            'medical_history' => $request->medical_history ?: null,
            'allergies' => $request->allergies ?: null,
            'current_medications' => $request->current_medications ?: null,
            'family_doctor_name' => $request->family_doctor_name ?: null,
            'family_doctor_phone' => $request->family_doctor_phone ?: null,
            'family_doctor_address' => $request->family_doctor_address ?: null,
            'last_medical_checkup' => $this->normalizeDate($request->last_medical_checkup),
            'medical_insurance_details' => $request->medical_insurance_details ?: null,

            // Emergency Contacts
            'emergency_contact_name' => $request->emergency_contact_name ?: null,
            'emergency_contact_phone' => $request->emergency_contact_phone ?: null,
            'emergency_contact_number' => $request->emergency_contact_number ?: $request->emergency_contact_phone ?: null,
            'emergency_contact_relation' => $request->emergency_contact_relation ?: null,
            'emergency_contact_2_name' => $request->emergency_contact_2_name ?: null,
            'emergency_contact_2_phone' => $request->emergency_contact_2_phone ?: null,
            'emergency_contact_2_relation' => $request->emergency_contact_2_relation ?: null,
            'emergency_contact_2_address' => $request->emergency_contact_2_address ?: null,

            // Financial & Bank Details
            'epf_number' => $request->epf_number ?: null,
            'pf_number' => $request->pf_number ?: null,
            'esi_number' => $request->esi_number ?: null,
            'uan_number' => $request->uan_number ?: null,
            'gratuity_number' => $request->gratuity_number ?: null,
            'basic_salary' => $request->filled('basic_salary') ? (float) $request->basic_salary : 0,
            'ctc' => $request->filled('ctc') ? (float) $request->ctc : 0,
            'salary_components' => $this->encodeJsonField($request->salary_components),
            'deductions' => $this->encodeJsonField($request->deductions),
            'income_tax_pan' => $request->income_tax_pan ?: null,
            'bank_name' => $request->bank_name ?: null,
            'bank_account_number' => $request->bank_account_number ?: null,
            'bank_ifsc_code' => $request->bank_ifsc_code ?: null,
            'account_title' => $request->account_title ?: null,
            'bank_branch_name' => $request->bank_branch_name ?: null,

            // Additional Professional & Performance
            'previous_employers' => $this->encodeJsonField($request->previous_employers),
            'references' => $this->encodeJsonField($request->references),
            'professional_memberships' => $request->professional_memberships ?: null,
            'professional_license' => $request->professional_license ?: null,
            'performance_reviews' => $this->encodeJsonField($request->performance_reviews),
            'appraisals' => $this->encodeJsonField($request->appraisals),
            'goals_objectives' => $this->encodeJsonField($request->goals_objectives),
            'training_needs' => $this->encodeJsonField($request->training_needs),

            // Additional Notes & Documents
            'notes' => $request->notes ?: null,
            'hobbies_interests' => $request->hobbies_interests ?: null,
            'volunteer_work' => $request->volunteer_work ?: null,
            'community_involvement' => $request->community_involvement ?: null,
            'personal_statement' => $request->personal_statement ?: null,
            'career_objectives' => $request->career_objectives ?: null,
            'additional_notes' => $request->additional_notes ?: null,
            'remarks' => $request->remarks ?: null,
            'profile_picture' => $request->profile_picture ?: null,
            'documents' => $this->encodeJsonField($request->documents),
        ];

        return $data;
    }

    /**
     * Prepare teacher data for update (updating only submitted fields)
     */
    private function prepareTeacherUpdateData(Request $request, Teacher $teacher): array
    {
        $data = [];

        if ($request->filled('branch_id')) {
            $data['branch_id'] = $request->branch_id;
            $branch = Branch::find($request->branch_id);
            $data['school_id'] = $branch ? $branch->school_id : null;
        }

        if ($request->has('department_id')) {
            $data['department_id'] = $request->department_id ?: null;
        }
        if ($request->has('reporting_manager_id')) {
            $data['reporting_manager_id'] = $request->reporting_manager_id ?: null;
        }
        if ($request->has('reporting_manager')) {
            $data['reporting_manager'] = $request->reporting_manager ?: null;
        }
        if ($request->has('employee_id')) {
            $data['employee_id'] = $request->employee_id;
        }
        if ($request->has('category_type')) {
            $data['category_type'] = $request->category_type;
        }
        if ($request->has('designation')) {
            $data['designation'] = $request->designation;
        }
        if ($request->has('employee_type')) {
            $data['employee_type'] = $request->employee_type;
        }
        if ($request->has('employee_type_detail')) {
            $data['employee_type_detail'] = $request->employee_type_detail;
        }
        if ($request->has('employment_status')) {
            $data['employment_status'] = $request->employment_status;
        }
        if ($request->has('teacher_status')) {
            $data['teacher_status'] = $request->teacher_status;
        }

        // Dates
        if ($request->has('joining_date')) {
            $data['joining_date'] = $this->normalizeDate($request->joining_date);
        }
        if ($request->has('leaving_date')) {
            $data['leaving_date'] = $this->normalizeDate($request->leaving_date);
        }
        if ($request->has('probation_end_date')) {
            $data['probation_end_date'] = $this->normalizeDate($request->probation_end_date);
        }
        if ($request->has('confirmation_date')) {
            $data['confirmation_date'] = $this->normalizeDate($request->confirmation_date);
        }
        if ($request->has('date_of_birth')) {
            $data['date_of_birth'] = $this->normalizeDate($request->date_of_birth);
        }
        if ($request->has('spouse_date_of_birth')) {
            $data['spouse_date_of_birth'] = $this->normalizeDate($request->spouse_date_of_birth);
        }
        if ($request->has('passport_expiry')) {
            $data['passport_expiry'] = $this->normalizeDate($request->passport_expiry);
        }
        if ($request->has('driving_license_expiry')) {
            $data['driving_license_expiry'] = $this->normalizeDate($request->driving_license_expiry);
        }
        if ($request->has('last_medical_checkup')) {
            $data['last_medical_checkup'] = $this->normalizeDate($request->last_medical_checkup);
        }

        // Personal & Identity
        if ($request->has('middle_name')) {
            $data['middle_name'] = $request->middle_name ? strip_tags((string) $request->middle_name) : null;
        }
        if ($request->has('preferred_name')) {
            $data['preferred_name'] = $request->preferred_name ? strip_tags((string) $request->preferred_name) : null;
        }
        if ($request->has('title')) {
            $data['title'] = $request->title ?: null;
        }
        if ($request->has('suffix')) {
            $data['suffix'] = $request->suffix ?: null;
        }
        if ($request->has('gender')) {
            $data['gender'] = $request->gender;
        }
        if ($request->has('place_of_birth')) {
            $data['place_of_birth'] = $request->place_of_birth ?: null;
        }
        if ($request->has('blood_group')) {
            $data['blood_group'] = $request->blood_group ?: null;
        }
        if ($request->has('religion')) {
            $data['religion'] = $request->religion ?: null;
        }
        if ($request->has('nationality')) {
            $data['nationality'] = ! empty(trim((string) $request->nationality)) ? trim($request->nationality) : 'Indian';
        }
        if ($request->has('caste')) {
            $data['caste'] = $request->caste ?: null;
        }
        if ($request->has('sub_caste')) {
            $data['sub_caste'] = $request->sub_caste ?: null;
        }
        if ($request->has('mother_tongue')) {
            $data['mother_tongue'] = $request->mother_tongue ?: null;
        }
        if ($request->has('languages_known')) {
            $data['languages_known'] = $this->encodeJsonField($request->languages_known);
        }
        if ($request->has('handicap_status')) {
            $data['handicap_status'] = $request->handicap_status ?: 'None';
        }
        if ($request->has('handicap_details')) {
            $data['handicap_details'] = $request->handicap_details ?: null;
        }

        // Identity Documents
        if ($request->has('pan_number')) {
            $data['pan_number'] = $request->pan_number ? strtoupper(trim((string) $request->pan_number)) : null;
        }
        if ($request->has('aadhaar_number') || $request->has('aadhar_number')) {
            $aadhaar = $request->aadhaar_number ?: $request->aadhar_number ?: null;
            $data['aadhaar_number'] = $aadhaar;
            $data['aadhar_number'] = $aadhaar;
        }
        if ($request->has('passport_number')) {
            $data['passport_number'] = $request->passport_number ?: null;
        }
        if ($request->has('driving_license_number')) {
            $data['driving_license_number'] = $request->driving_license_number ?: null;
        }
        if ($request->has('voter_id')) {
            $data['voter_id'] = $request->voter_id ?: null;
        }

        // Family Information
        if ($request->has('father_name')) {
            $data['father_name'] = $request->father_name ?: null;
        }
        if ($request->has('mother_name')) {
            $data['mother_name'] = $request->mother_name ?: null;
        }
        if ($request->has('spouse_name')) {
            $data['spouse_name'] = $request->spouse_name ?: null;
        }
        if ($request->has('spouse_occupation')) {
            $data['spouse_occupation'] = $request->spouse_occupation ?: null;
        }
        if ($request->has('spouse_phone')) {
            $data['spouse_phone'] = $request->spouse_phone ?: null;
        }
        if ($request->has('spouse_email')) {
            $data['spouse_email'] = $request->spouse_email ?: null;
        }
        if ($request->has('number_of_children')) {
            $data['number_of_children'] = (int) $request->number_of_children;
        }
        if ($request->has('children_details')) {
            $data['children_details'] = $this->encodeJsonField($request->children_details);
        }

        // Contact & Address
        if ($request->has('alternate_email')) {
            $data['alternate_email'] = $request->alternate_email ?: null;
        }
        if ($request->has('alternate_phone')) {
            $data['alternate_phone'] = $request->alternate_phone ?: null;
        }
        if ($request->has('whatsapp_number')) {
            $data['whatsapp_number'] = $request->whatsapp_number ?: null;
        }
        if ($request->has('landline_number')) {
            $data['landline_number'] = $request->landline_number ?: null;
        }
        if ($request->has('current_address')) {
            $data['current_address'] = $request->current_address ?: null;
        }
        if ($request->has('current_city')) {
            $data['current_city'] = $request->current_city ?: null;
            $data['city'] = $request->current_city ?: null;
        } elseif ($request->has('city')) {
            $data['current_city'] = $request->city ?: null;
            $data['city'] = $request->city ?: null;
        }
        if ($request->has('current_state')) {
            $data['current_state'] = $request->current_state ?: null;
            $data['state'] = $request->current_state ?: null;
        } elseif ($request->has('state')) {
            $data['current_state'] = $request->state ?: null;
            $data['state'] = $request->state ?: null;
        }
        if ($request->has('current_pincode')) {
            $data['current_pincode'] = $request->current_pincode ?: null;
            $data['pincode'] = $request->current_pincode ?: null;
        } elseif ($request->has('pincode')) {
            $data['current_pincode'] = $request->pincode ?: null;
            $data['pincode'] = $request->pincode ?: null;
        }
        if ($request->has('current_country')) {
            $data['current_country'] = $request->current_country ?: 'India';
        }

        if ($request->has('permanent_address')) {
            $data['permanent_address'] = $request->permanent_address ?: null;
        }
        if ($request->has('permanent_city')) {
            $data['permanent_city'] = $request->permanent_city ?: null;
        }
        if ($request->has('permanent_state')) {
            $data['permanent_state'] = $request->permanent_state ?: null;
        }
        if ($request->has('permanent_pincode')) {
            $data['permanent_pincode'] = $request->permanent_pincode ?: null;
        }
        if ($request->has('permanent_country')) {
            $data['permanent_country'] = $request->permanent_country ?: 'India';
        }

        // Education & Credentials
        if ($request->has('qualification')) {
            $data['qualification'] = is_array($request->qualification) ? json_encode($request->qualification) : ($request->qualification ?: null);
        }
        if ($request->has('educational_qualifications')) {
            $data['educational_qualifications'] = $this->encodeJsonField($request->educational_qualifications);
        }
        if ($request->has('professional_certifications')) {
            $data['professional_certifications'] = $this->encodeJsonField($request->professional_certifications);
        }
        if ($request->has('training_programs')) {
            $data['training_programs'] = $this->encodeJsonField($request->training_programs);
        }
        if ($request->has('awards_recognitions')) {
            $data['awards_recognitions'] = $this->encodeJsonField($request->awards_recognitions);
        }
        if ($request->has('publications')) {
            $data['publications'] = $this->encodeJsonField($request->publications);
        }
        if ($request->has('research_projects')) {
            $data['research_projects'] = $this->encodeJsonField($request->research_projects);
        }
        if ($request->has('specialization')) {
            $data['specialization'] = $request->specialization ?: null;
        }
        if ($request->has('registration_number')) {
            $data['registration_number'] = $request->registration_number ?: null;
        }
        if ($request->has('salary_grade')) {
            $data['salary_grade'] = $request->salary_grade ?: null;
        }
        if ($request->has('subordinates')) {
            $data['subordinates'] = $this->encodeJsonField($request->subordinates);
        }

        // Teaching Assignment
        if ($request->has('subjects')) {
            $data['subjects'] = $this->encodeJsonField($request->subjects);
        }
        if ($request->has('classes_assigned')) {
            $data['classes_assigned'] = $this->encodeJsonField($request->classes_assigned);
        }
        if ($request->has('is_class_teacher')) {
            $data['is_class_teacher'] = filter_var($request->is_class_teacher, FILTER_VALIDATE_BOOLEAN);
        }
        if ($request->has('class_teacher_of_grade')) {
            $data['class_teacher_of_grade'] = $request->class_teacher_of_grade ?: null;
        }
        if ($request->has('class_teacher_of_section')) {
            $data['class_teacher_of_section'] = $request->class_teacher_of_section ?: null;
        }

        // Skills & Competencies
        if ($request->has('experience_years')) {
            $data['experience_years'] = (float) $request->experience_years;
        }
        if ($request->has('teaching_experience_years')) {
            $data['teaching_experience_years'] = (float) $request->teaching_experience_years;
        }
        if ($request->has('industry_experience_years')) {
            $data['industry_experience_years'] = (float) $request->industry_experience_years;
        }
        if ($request->has('technical_skills')) {
            $data['technical_skills'] = $this->encodeJsonField($request->technical_skills);
        }
        if ($request->has('soft_skills')) {
            $data['soft_skills'] = $this->encodeJsonField($request->soft_skills);
        }
        if ($request->has('subject_expertise')) {
            $data['subject_expertise'] = $this->encodeJsonField($request->subject_expertise);
        }
        if ($request->has('teaching_methodologies')) {
            $data['teaching_methodologies'] = $this->encodeJsonField($request->teaching_methodologies);
        }

        // Health & Medical
        if ($request->has('medical_history')) {
            $data['medical_history'] = $request->medical_history ?: null;
        }
        if ($request->has('allergies')) {
            $data['allergies'] = $request->allergies ?: null;
        }
        if ($request->has('current_medications')) {
            $data['current_medications'] = $request->current_medications ?: null;
        }
        if ($request->has('family_doctor_name')) {
            $data['family_doctor_name'] = $request->family_doctor_name ?: null;
        }
        if ($request->has('family_doctor_phone')) {
            $data['family_doctor_phone'] = $request->family_doctor_phone ?: null;
        }
        if ($request->has('family_doctor_address')) {
            $data['family_doctor_address'] = $request->family_doctor_address ?: null;
        }
        if ($request->has('last_medical_checkup')) {
            $data['last_medical_checkup'] = $this->normalizeDate($request->last_medical_checkup);
        }
        if ($request->has('medical_insurance_details')) {
            $data['medical_insurance_details'] = $request->medical_insurance_details ?: null;
        }

        // Emergency Contacts
        if ($request->has('emergency_contact_name')) {
            $data['emergency_contact_name'] = $request->emergency_contact_name ?: null;
        }
        if ($request->has('emergency_contact_phone')) {
            $data['emergency_contact_phone'] = $request->emergency_contact_phone ?: null;
        }
        if ($request->has('emergency_contact_number')) {
            $data['emergency_contact_number'] = $request->emergency_contact_number ?: $request->emergency_contact_phone ?: null;
        }
        if ($request->has('emergency_contact_relation')) {
            $data['emergency_contact_relation'] = $request->emergency_contact_relation ?: null;
        }
        if ($request->has('emergency_contact_2_name')) {
            $data['emergency_contact_2_name'] = $request->emergency_contact_2_name ?: null;
        }
        if ($request->has('emergency_contact_2_phone')) {
            $data['emergency_contact_2_phone'] = $request->emergency_contact_2_phone ?: null;
        }
        if ($request->has('emergency_contact_2_relation')) {
            $data['emergency_contact_2_relation'] = $request->emergency_contact_2_relation ?: null;
        }
        if ($request->has('emergency_contact_2_address')) {
            $data['emergency_contact_2_address'] = $request->emergency_contact_2_address ?: null;
        }

        // Financial & Bank Details
        if ($request->has('epf_number')) {
            $data['epf_number'] = $request->epf_number ?: null;
        }
        if ($request->has('pf_number')) {
            $data['pf_number'] = $request->pf_number ?: null;
        }
        if ($request->has('esi_number')) {
            $data['esi_number'] = $request->esi_number ?: null;
        }
        if ($request->has('uan_number')) {
            $data['uan_number'] = $request->uan_number ?: null;
        }
        if ($request->has('gratuity_number')) {
            $data['gratuity_number'] = $request->gratuity_number ?: null;
        }
        if ($request->has('basic_salary')) {
            $data['basic_salary'] = (float) $request->basic_salary;
        }
        if ($request->has('ctc')) {
            $data['ctc'] = (float) $request->ctc;
        }
        if ($request->has('salary_components')) {
            $data['salary_components'] = $this->encodeJsonField($request->salary_components);
        }
        if ($request->has('deductions')) {
            $data['deductions'] = $this->encodeJsonField($request->deductions);
        }
        if ($request->has('income_tax_pan')) {
            $data['income_tax_pan'] = $request->income_tax_pan ?: null;
        }
        if ($request->has('bank_name')) {
            $data['bank_name'] = $request->bank_name ?: null;
        }
        if ($request->has('bank_account_number')) {
            $data['bank_account_number'] = $request->bank_account_number ?: null;
        }
        if ($request->has('bank_ifsc_code')) {
            $data['bank_ifsc_code'] = $request->bank_ifsc_code ?: null;
        }
        if ($request->has('account_title')) {
            $data['account_title'] = $request->account_title ?: null;
        }
        if ($request->has('bank_branch_name')) {
            $data['bank_branch_name'] = $request->bank_branch_name ?: null;
        }

        // Additional Professional & Performance
        if ($request->has('previous_employers')) {
            $data['previous_employers'] = $this->encodeJsonField($request->previous_employers);
        }
        if ($request->has('references')) {
            $data['references'] = $this->encodeJsonField($request->references);
        }
        if ($request->has('professional_memberships')) {
            $data['professional_memberships'] = $request->professional_memberships ?: null;
        }
        if ($request->has('professional_license')) {
            $data['professional_license'] = $request->professional_license ?: null;
        }
        if ($request->has('performance_reviews')) {
            $data['performance_reviews'] = $this->encodeJsonField($request->performance_reviews);
        }
        if ($request->has('appraisals')) {
            $data['appraisals'] = $this->encodeJsonField($request->appraisals);
        }
        if ($request->has('goals_objectives')) {
            $data['goals_objectives'] = $this->encodeJsonField($request->goals_objectives);
        }
        if ($request->has('training_needs')) {
            $data['training_needs'] = $this->encodeJsonField($request->training_needs);
        }

        // Additional Notes & Documents
        if ($request->has('notes')) {
            $data['notes'] = $request->notes ?: null;
        }
        if ($request->has('hobbies_interests')) {
            $data['hobbies_interests'] = $request->hobbies_interests ?: null;
        }
        if ($request->has('volunteer_work')) {
            $data['volunteer_work'] = $request->volunteer_work ?: null;
        }
        if ($request->has('community_involvement')) {
            $data['community_involvement'] = $request->community_involvement ?: null;
        }
        if ($request->has('personal_statement')) {
            $data['personal_statement'] = $request->personal_statement ?: null;
        }
        if ($request->has('career_objectives')) {
            $data['career_objectives'] = $request->career_objectives ?: null;
        }
        if ($request->has('additional_notes')) {
            $data['additional_notes'] = $request->additional_notes ?: null;
        }
        if ($request->has('remarks')) {
            $data['remarks'] = $request->remarks ?: null;
        }
        if ($request->has('profile_picture')) {
            $data['profile_picture'] = $request->profile_picture ?: null;
        }
        if ($request->has('documents')) {
            $data['documents'] = $this->encodeJsonField($request->documents);
        }

        return $data;
    }

    /**
     * Safely parse a date into Y-m-d format
     */
    private function normalizeDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            return \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Encode/clean JSON array fields
     */
    private function encodeJsonField($value)
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            return $value;
        }
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return $value;
    }

    public function destroy($id)
    {
        try {
            $teacher = Teacher::findOrFail($id);

            // Soft delete the teacher (sets deleted_at timestamp)
            $teacher->delete();

            // Also deactivate the user account
            $teacher->user()->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Teacher deactivated successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Delete teacher error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate teacher',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Restore soft-deleted teacher (reactivate)
     */
    public function restore($id)
    {
        try {
            // Find teacher with trashed records
            $teacher = Teacher::withTrashed()->find($id);

            if (! $teacher) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher not found',
                ], 404);
            }

            if (! $teacher->trashed()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Teacher is already active',
                ], 400);
            }

            // Restore the teacher
            $teacher->restore();

            // Reactivate the user account
            $teacher->user()->update(['is_active' => true]);

            return response()->json([
                'success' => true,
                'message' => 'Teacher restored successfully',
                'data' => $teacher->load('user', 'branch', 'department'),
            ]);

        } catch (\Exception $e) {
            Log::error('Restore teacher error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to restore teacher',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Handle file upload for teacher documents
     */
    private function handleFileUpload(Request $request, Teacher $teacher, string $fieldName, string $documentType): ?string
    {
        if (! $request->hasFile($fieldName)) {
            return null;
        }

        try {
            $file = $request->file($fieldName);

            // Generate unique filename
            $fileName = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $filePath = $file->storeAs('teachers/'.$teacher->id.'/'.$documentType, $fileName, 'public');

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
                'uploaded_by' => auth()->id(),
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
                'profile_picture' => 'required|image|mimes:jpeg,jpg,png,gif,svg,webp,bmp|max:1024',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $teacher = Teacher::findOrFail($id);

            $filePath = $this->handleFileUpload($request, $teacher, 'profile_picture', 'profile_picture');

            if ($filePath) {
                // Save profile_picture to extended_profile (teachers table has no profile_picture column)
                $extendedProfile = $teacher->extended_profile ?? [];
                $extendedProfile['profile_picture'] = $filePath;
                $teacher->update(['extended_profile' => $extendedProfile]);

                return response()->json([
                    'success' => true,
                    'message' => 'Profile picture uploaded successfully',
                    'data' => [
                        'file_path' => $filePath,
                        'file_url' => Storage::url($filePath),
                    ],
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
            ], 500);

        } catch (\Exception $e) {
            Log::error('Upload profile picture error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload profile picture',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
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
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Build query with same filters as index method
            $query = $this->buildTeacherQuery($request);

            // Get all matching records (no pagination for export)
            $teachers = $query->get();

            // Transform data for export
            $exportData = collect($teachers)->map(function ($teacher) {
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

            return match ($format) {
                'excel' => $this->exportExcel($exportData, $columns),
                'pdf' => $this->exportPdf($exportData, $columns),
                'csv' => $this->exportCsv($exportData, $columns),
            };

        } catch (\Exception $e) {
            Log::error('Export teachers error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export teachers',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Build teacher query with filters (reusable for index and export)
     */
    protected function buildTeacherQuery(Request $request)
    {
        $query = Teacher::with(['user', 'branch', 'department']);

        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $query->where('school_id', $schoolId);
        }

        $user = $request->user();
        if ($user && $user->role === 'Teacher') {
            $query->where('user_id', $user->id);
        }

        // Apply branch filtering - Restrict to accessible branches
        $accessibleBranchIds = $this->getAccessibleBranchIds($request);

        if ($user && $user->role === 'SuperAdmin' && ! empty($user->company_id) && ! $request->filled('branch_id')) {
            $query->whereIn('branch_id', function ($q) use ($user) {
                $q->select('branches.id')
                    ->from('branches')
                    ->join('schools', 'branches.school_id', '=', 'schools.id')
                    ->where('schools.company_id', (int) $user->company_id)
                    ->whereNull('schools.deleted_at')
                    ->whereNull('branches.deleted_at');
            });
        } elseif ($accessibleBranchIds !== 'all') {
            if (! empty($accessibleBranchIds)) {
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
            $query->where('designation', 'like', $request->designation.'%');
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
            $query->where('employee_id', 'like', $request->employee_id.'%');
        }

        if ($request->filled('email')) {
            $email = strip_tags($request->email);
            $query->whereHas('user', function ($userQuery) use ($email) {
                $userQuery->where('email', 'like', "{$email}%");
            });
        }

        // Filter by active status
        if ($request->has('is_active') && $request->is_active !== '') {
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN);
            $query->whereHas('user', function ($q) use ($isActive) {
                $q->where('is_active', $isActive);
            });
        }

        // OPTIMIZED Global search across multiple fields - removed leading wildcards
        if ($request->has('search') && $request->search !== '') {
            $searchTerm = $request->search;
            $query->where(function ($q) use ($searchTerm) {
                $q->where('employee_id', 'like', $searchTerm.'%')
                    ->orWhere('designation', 'like', $searchTerm.'%')
                    ->orWhereHas('user', function ($userQuery) use ($searchTerm) {
                        $userQuery->where('first_name', 'like', $searchTerm.'%')
                            ->orWhere('last_name', 'like', $searchTerm.'%')
                            ->orWhere('email', 'like', $searchTerm.'%')
                            ->orWhere('phone', 'like', $searchTerm.'%');
                    })
                    ->orWhereHas('department', function ($deptQuery) use ($searchTerm) {
                        $deptQuery->where('name', 'like', $searchTerm.'%');
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
