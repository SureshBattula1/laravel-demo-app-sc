<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdmissionApplication extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'branch_id',
        'application_number',
        'application_date',
        'academic_year',
        'applying_for_grade',
        'applying_for_section',
        'first_name',
        'last_name',
        'date_of_birth',
        'gender',
        'blood_group',
        'religion',
        'nationality',
        'category',
        'mother_tongue',
        'email',
        'phone',
        'alternate_phone',
        'current_address',
        'current_city',
        'current_state',
        'current_country',
        'current_pincode',
        'permanent_address',
        'permanent_city',
        'permanent_state',
        'permanent_country',
        'permanent_pincode',
        'father_name',
        'father_phone',
        'father_email',
        'father_occupation',
        'father_qualification',
        'father_annual_income',
        'mother_name',
        'mother_phone',
        'mother_email',
        'mother_occupation',
        'mother_qualification',
        'mother_annual_income',
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'guardian_email',
        'guardian_address',
        'previous_school',
        'previous_grade',
        'previous_school_board',
        'previous_percentage',
        'transfer_certificate_number',
        'tc_date',
        'application_status',
        'application_fee_paid',
        'application_fee_amount',
        'application_fee_payment_date',
        'application_fee_receipt_number',
        'entrance_test_required',
        'entrance_test_date',
        'entrance_test_score',
        'entrance_test_result',
        'interview_required',
        'interview_date',
        'interview_score',
        'interview_result',
        'admission_decision',
        'admission_decision_date',
        'admission_offer_letter',
        'admission_validity_date',
        'registration_fee_paid',
        'registration_fee_amount',
        'registration_fee_payment_date',
        'admission_confirmed',
        'admission_confirmed_date',
        'student_id',
        'remarks',
        'documents',
        'created_by',
        'updated_by'
    ];

    protected $casts = [
        'application_date' => 'date',
        'date_of_birth' => 'date',
        'tc_date' => 'date',
        'application_fee_payment_date' => 'date',
        'entrance_test_date' => 'date',
        'interview_date' => 'date',
        'admission_decision_date' => 'date',
        'admission_validity_date' => 'date',
        'registration_fee_payment_date' => 'date',
        'admission_confirmed_date' => 'date',
        'application_fee_paid' => 'boolean',
        'registration_fee_paid' => 'boolean',
        'entrance_test_required' => 'boolean',
        'interview_required' => 'boolean',
        'admission_confirmed' => 'boolean',
        'father_annual_income' => 'decimal:2',
        'mother_annual_income' => 'decimal:2',
        'application_fee_amount' => 'decimal:2',
        'registration_fee_amount' => 'decimal:2',
        'entrance_test_score' => 'decimal:2',
        'interview_score' => 'decimal:2',
        'previous_percentage' => 'decimal:2',
        'documents' => 'array',
    ];

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('application_status', $status);
    }

    public function scopeByGrade($query, $grade)
    {
        return $query->where('applying_for_grade', $grade);
    }

    public function scopeByAcademicYear($query, $year)
    {
        return $query->where('academic_year', $year);
    }

    public function scopePending($query)
    {
        return $query->whereIn('application_status', ['Applied', 'Shortlisted']);
    }

    public function scopeApproved($query)
    {
        return $query->where('application_status', 'Admitted');
    }
}

