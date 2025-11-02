<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Teacher extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Core fields (actual DB columns)
        'user_id', 'branch_id', 'employee_id', 'category_type', 'department_id',
        'joining_date', 'designation', 'employee_type', 'subjects', 'classes_assigned',
        'is_class_teacher', 'date_of_birth', 'gender', 'address', 'basic_salary',
        'bank_account_number', 'teacher_status', 'reporting_manager_id',
        'pan_number', 'aadhaar_number', 'blood_group', 'nationality',
        'father_name', 'mother_name', 'phone', 'email', 'alternate_phone',
        'whatsapp_number', 'emergency_contact_name', 'emergency_contact_phone',
        'emergency_contact_relation', 'epf_number', 'ctc', 'bank_name',
        'bank_ifsc_code', 'profile_picture', 'extended_profile',
        // Address fields
        'current_address', 'current_city', 'current_state', 'current_pincode', 'current_country',
        'permanent_address', 'permanent_city', 'permanent_state', 'permanent_pincode', 'permanent_country',
        // Additional fields that exist in DB
        'first_name', 'middle_name', 'last_name', 'preferred_name', 'title', 'suffix',
        'passport_number', 'passport_expiry', 'driving_license_number', 'driving_license_expiry',
        'voter_id', 'place_of_birth', 'religion', 'caste', 'sub_caste', 'mother_tongue',
        'languages_known', 'handicap_status', 'handicap_details', 'spouse_name',
        'spouse_date_of_birth', 'spouse_occupation', 'spouse_phone', 'spouse_email',
        'number_of_children', 'children_details', 'alternate_email', 'landline_number',
        'employee_type_detail', 'employment_status', 'probation_end_date',
        'confirmation_date', 'reporting_manager', 'subordinates', 'qualification',
        'educational_qualifications', 'professional_certifications', 'training_programs',
        'awards_recognitions', 'publications', 'research_projects', 'experience_years',
        'teaching_experience_years', 'industry_experience_years', 'technical_skills',
        'soft_skills', 'subject_expertise', 'teaching_methodologies', 'medical_history',
        'allergies', 'current_medications', 'family_doctor_name', 'family_doctor_phone',
        'family_doctor_address', 'last_medical_checkup', 'medical_insurance_details',
        'emergency_contact_number', 'emergency_contact_2_name', 'emergency_contact_2_phone',
        'emergency_contact_2_relation', 'emergency_contact_2_address', 'pf_number',
        'esi_number', 'uan_number', 'gratuity_number', 'salary_components', 'deductions',
        'income_tax_pan', 'account_title', 'bank_branch_name', 'previous_employers',
        'references', 'professional_memberships', 'professional_license',
        'performance_reviews', 'appraisals', 'goals_objectives', 'training_needs',
        'hobbies_interests', 'volunteer_work', 'community_involvement',
        'personal_statement', 'career_objectives', 'notes', 'additional_notes'
    ];
    
    // Virtual attributes stored in extended_profile JSON
    protected $appends = [];
    
    protected $extendedFields = [
        'first_name', 'middle_name', 'last_name', 'preferred_name', 'title', 'suffix',
        'passport_number', 'passport_expiry', 'driving_license_number', 'driving_license_expiry',
        'voter_id', 'place_of_birth', 'religion', 'caste', 'sub_caste', 'mother_tongue',
        'languages_known', 'handicap_status', 'handicap_details', 'spouse_name',
        'spouse_date_of_birth', 'spouse_occupation', 'spouse_phone', 'spouse_email',
        'number_of_children', 'children_details', 'alternate_email', 'landline_number',
        'current_address', 'current_city', 'current_state', 'current_pincode', 'current_country',
        'permanent_address', 'permanent_city', 'permanent_state', 'permanent_pincode',
        'permanent_country', 'employee_type_detail', 'employment_status', 'probation_end_date',
        'confirmation_date', 'reporting_manager', 'subordinates', 'qualification',
        'educational_qualifications', 'professional_certifications', 'training_programs',
        'awards_recognitions', 'publications', 'research_projects', 'experience_years',
        'teaching_experience_years', 'industry_experience_years', 'technical_skills',
        'soft_skills', 'subject_expertise', 'teaching_methodologies', 'medical_history',
        'allergies', 'current_medications', 'family_doctor_name', 'family_doctor_phone',
        'family_doctor_address', 'last_medical_checkup', 'medical_insurance_details',
        'emergency_contact_number', 'emergency_contact_2_name', 'emergency_contact_2_phone',
        'emergency_contact_2_relation', 'emergency_contact_2_address', 'pf_number',
        'esi_number', 'uan_number', 'gratuity_number', 'salary_components', 'deductions',
        'income_tax_pan', 'account_title', 'bank_branch_name', 'previous_employers',
        'references', 'professional_memberships', 'professional_license',
        'performance_reviews', 'appraisals', 'goals_objectives', 'training_needs',
        'hobbies_interests', 'volunteer_work', 'community_involvement',
        'personal_statement', 'career_objectives', 'notes', 'additional_notes'
    ];

    protected function casts(): array
    {
        return [
            'is_class_teacher' => 'boolean',
            'date_of_birth' => 'date',
            'joining_date' => 'date',
            'basic_salary' => 'decimal:2',
            'ctc' => 'decimal:2',
            'subjects' => 'array',
            'classes_assigned' => 'array',
            'extended_profile' => 'array',
            'languages_known' => 'array',
            'children_details' => 'array',
            'subordinates' => 'array',
            'educational_qualifications' => 'array',
            'professional_certifications' => 'array',
            'training_programs' => 'array',
            'awards_recognitions' => 'array',
            'publications' => 'array',
            'research_projects' => 'array',
            'technical_skills' => 'array',
            'soft_skills' => 'array',
            'subject_expertise' => 'array',
            'teaching_methodologies' => 'array',
            'salary_components' => 'array',
            'deductions' => 'array',
            'previous_employers' => 'array',
            'references' => 'array',
            'performance_reviews' => 'array',
            'appraisals' => 'array',
            'goals_objectives' => 'array',
            'training_needs' => 'array'
        ];
    }

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function attachments()
    {
        return $this->hasMany(TeacherAttachment::class);
    }

    public function profilePicture()
    {
        return $this->hasOne(TeacherAttachment::class)
                    ->where('document_type', 'profile_picture')
                    ->where('is_active', true)
                    ->latest();
    }

    // Accessors for extended_profile fields
    public function getNotesAttribute($value)
    {
        return $value ?? $this->extended_profile['notes'] ?? null;
    }

    public function getHobbiesInterestsAttribute($value)
    {
        return $value ?? $this->extended_profile['hobbies_interests'] ?? null;
    }

    public function getCareerObjectivesAttribute($value)
    {
        return $value ?? $this->extended_profile['career_objectives'] ?? null;
    }

    public function getProfessionalMembershipsAttribute($value)
    {
        return $value ?? $this->extended_profile['professional_memberships'] ?? null;
    }

    public function getProfessionalLicenseAttribute($value)
    {
        return $value ?? $this->extended_profile['professional_license'] ?? null;
    }

    // Profile picture URL accessor
    public function getProfilePictureAttribute($value)
    {
        if (!$value) {
            return null;
        }
        
        // If already a full URL, return as is
        if (str_starts_with($value, 'http')) {
            return $value;
        }
        
        // If value doesn't start with 'storage/', add it
        if (!str_starts_with($value, 'storage/')) {
            return url('storage/' . $value);
        }
        
        // Value already has 'storage/', just construct full URL
        return url($value);
    }

    // Accessors
    public function getFullNameAttribute()
    {
        $name = $this->first_name ?? $this->user->first_name ?? '';
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . ($this->last_name ?? $this->user->last_name ?? '');
        return trim($name);
    }

    public function getDisplayNameAttribute()
    {
        return $this->preferred_name ?: $this->getFullNameAttribute();
    }

    public function getProfileCompletionPercentageAttribute()
    {
        $totalFields = 50; // Total important fields
        $filledFields = 0;
        
        $importantFields = [
            'staff_id', 'category_type', 'designation', 'gender', 'date_of_birth',
            'pan_number', 'father_name', 'mother_name', 'current_address',
            'permanent_address', 'qualification', 'work_experience', 'basic_salary',
            'bank_account_number', 'bank_ifsc_code', 'emergency_contact_name',
            'emergency_contact_phone', 'aadhaar_number', 'blood_group',
            'marital_status', 'spouse_name', 'alternate_email', 'alternate_phone',
            'whatsapp_number', 'current_city', 'current_state', 'current_pincode',
            'permanent_city', 'permanent_state', 'permanent_pincode', 'epf_number',
            'pf_number', 'esi_number', 'uan_number', 'ctc', 'medical_history',
            'allergies', 'family_doctor_name', 'family_doctor_phone',
            'emergency_contact_2_name', 'emergency_contact_2_phone',
            'professional_memberships', 'hobbies_interests', 'volunteer_work',
            'community_involvement', 'personal_statement', 'career_objectives',
            'technical_skills', 'soft_skills', 'subject_expertise', 'notes'
        ];

        foreach ($importantFields as $field) {
            if (!empty($this->$field)) {
                $filledFields++;
            }
        }

        return round(($filledFields / $totalFields) * 100);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('teacher_status', 'Active');
    }

    public function scopeTeaching($query)
    {
        return $query->where('category_type', 'Teaching');
    }

    public function scopeNonTeaching($query)
    {
        return $query->where('category_type', 'Non-Teaching');
    }

    public function scopeByBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    public function scopeByDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    // Methods
    public function updateProfileCompletion()
    {
        // Just compute and return the percentage, don't save to database
        return $this->getProfileCompletionPercentageAttribute();
    }

    public function isProfileComplete()
    {
        return $this->getProfileCompletionPercentageAttribute() >= 80;
    }

    public function getRequiredDocuments()
    {
        $required = ['resume_path', 'joining_letter_path'];
        $optional = ['aadhaar_path', 'pan_path', 'passport_path', 'driving_license_path'];
        
        return [
            'required' => $required,
            'optional' => $optional,
            'uploaded' => array_filter([
                'resume' => $this->resume_path,
                'joining_letter' => $this->joining_letter_path,
                'aadhaar' => $this->aadhaar_path ?? null,
                'pan' => $this->pan_path ?? null,
                'passport' => $this->passport_path ?? null,
                'driving_license' => $this->driving_license_path ?? null
            ])
        ];
    }
}
