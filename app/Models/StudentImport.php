<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentImport extends Model
{
    protected $fillable = [
        'batch_id',
        'row_number',
        'branch_id',
        'grade',
        'section',
        'academic_year',
        'first_name',
        'last_name',
        'email',
        'phone',
        'admission_number',
        'admission_date',
        'roll_number',
        'registration_number',
        'grade_override',
        'section_override',
        'academic_year_override',
        'stream',
        'date_of_birth',
        'gender',
        'blood_group',
        'religion',
        'category',
        'nationality',
        'mother_tongue',
        'current_address',
        'permanent_address',
        'city',
        'state',
        'country',
        'pincode',
        'father_name',
        'father_phone',
        'father_email',
        'father_occupation',
        'father_annual_income',
        'mother_name',
        'mother_phone',
        'mother_email',
        'mother_occupation',
        'mother_annual_income',
        'guardian_name',
        'guardian_relation',
        'guardian_phone',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'previous_school',
        'previous_grade',
        'previous_percentage',
        'transfer_certificate_number',
        'medical_history',
        'allergies',
        'medications',
        'height_cm',
        'weight_kg',
        'password',
        'remarks',
        'validation_status',
        'validation_errors',
        'validation_warnings',
        'imported_to_production',
        'imported_user_id',
        'imported_student_id',
        'imported_at',
    ];

    protected $casts = [
        'admission_date' => 'date',
        'date_of_birth' => 'date',
        'father_annual_income' => 'decimal:2',
        'mother_annual_income' => 'decimal:2',
        'previous_percentage' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'weight_kg' => 'decimal:2',
        'validation_errors' => 'array',
        'validation_warnings' => 'array',
        'imported_to_production' => 'boolean',
        'imported_at' => 'datetime',
    ];

    /**
     * Get the import history this belongs to
     */
    public function importHistory(): BelongsTo
    {
        return $this->belongsTo(ImportHistory::class, 'batch_id', 'batch_id');
    }

    /**
     * Get the created user (if imported)
     */
    public function importedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'imported_user_id');
    }

    /**
     * Scope to get valid records
     */
    public function scopeValid($query)
    {
        return $query->where('validation_status', 'valid');
    }

    /**
     * Scope to get invalid records
     */
    public function scopeInvalid($query)
    {
        return $query->where('validation_status', 'invalid');
    }

    /**
     * Scope to get pending validation records
     */
    public function scopePending($query)
    {
        return $query->where('validation_status', 'pending');
    }

    /**
     * Scope to get not yet imported records
     */
    public function scopeNotImported($query)
    {
        return $query->where('imported_to_production', false);
    }

    /**
     * Check if record has errors
     */
    public function hasErrors(): bool
    {
        return !empty($this->validation_errors);
    }

    /**
     * Check if record has warnings
     */
    public function hasWarnings(): bool
    {
        return !empty($this->validation_warnings);
    }
}

