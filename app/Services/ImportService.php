<?php

namespace App\Services;

use App\Models\ImportHistory;
use App\Models\StudentImport;
use App\Models\TeacherImport;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ImportService
{
    /**
     * Create a new import batch
     */
    public function createImportBatch(string $entityType, int $userId, int $branchId, string $fileName, int $fileSize, array $context = []): ImportHistory
    {
        return ImportHistory::create([
            'batch_id' => $this->generateBatchId(),
            'entity_type' => $entityType,
            'uploaded_by' => $userId,
            'branch_id' => $branchId,
            'file_name' => $fileName,
            'file_size' => $fileSize,
            'import_context' => $context,
            'status' => 'uploaded',
            'uploaded_at' => now(),
        ]);
    }

    /**
     * Generate unique batch ID
     */
    protected function generateBatchId(): string
    {
        return 'IMPORT_' . strtoupper(Str::random(12)) . '_' . time();
    }

    /**
     * Insert student records to staging table
     */
    public function insertStudentsStagingData(string $batchId, array $data, array $context): int
    {
        $rowNumber = 1;
        $inserted = 0;

        foreach ($data as $row) {
            StudentImport::create([
                'batch_id' => $batchId,
                'row_number' => $rowNumber++,
                'branch_id' => $context['branch_id'] ?? null,
                'grade' => $context['grade'] ?? null,
                'section' => $context['section'] ?? null,
                'academic_year' => $context['academic_year'] ?? null,
                // Map Excel columns to database columns
                ...$row,
                'validation_status' => 'pending',
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Insert teacher records to staging table
     */
    public function insertTeachersStagingData(string $batchId, array $data, array $context): int
    {
        $rowNumber = 1;
        $inserted = 0;

        foreach ($data as $row) {
            TeacherImport::create([
                'batch_id' => $batchId,
                'row_number' => $rowNumber++,
                'branch_id' => $context['branch_id'] ?? null,
                // Map Excel columns to database columns
                ...$row,
                'validation_status' => 'pending',
            ]);
            $inserted++;
        }

        return $inserted;
    }

    /**
     * Validate student import data
     */
    public function validateStudents(string $batchId): array
    {
        $records = StudentImport::where('batch_id', $batchId)->pending()->get();
        $validCount = 0;
        $invalidCount = 0;

        foreach ($records as $record) {
            $errors = [];
            $warnings = [];

            // Basic field validation
            $validator = Validator::make($record->toArray(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'admission_number' => 'required|string|max:255',
                'admission_date' => 'required|date',
                'grade' => 'required|string',
                'academic_year' => 'required|string',
                'date_of_birth' => 'required|date|before:today',
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
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors = array_merge($errors, $messages);
                }
            }

            // Business logic validation
            if (empty($errors)) {
                // Check email uniqueness
                if (User::where('email', $record->email)->exists()) {
                    $errors[] = "Email '{$record->email}' already exists in the system";
                }

                // Check admission number uniqueness
                if (DB::table('students')->where('admission_number', $record->admission_number)->exists()) {
                    $errors[] = "Admission number '{$record->admission_number}' already exists";
                }

                // Check email uniqueness within this batch
                $duplicateInBatch = StudentImport::where('batch_id', $batchId)
                    ->where('email', $record->email)
                    ->where('id', '!=', $record->id)
                    ->exists();

                if ($duplicateInBatch) {
                    $errors[] = "Duplicate email '{$record->email}' found in row " . $record->row_number;
                }

                // Validate age for grade
                $age = \Carbon\Carbon::parse($record->date_of_birth)->age;
                if ($record->grade && !$this->validateAgeForGrade($age, $record->grade)) {
                    $warnings[] = "Age $age may not be appropriate for grade {$record->grade}";
                }

                // Validate branch exists and is active
                $branch = DB::table('branches')->where('id', $record->branch_id)->first();
                if (!$branch) {
                    $errors[] = "Invalid branch ID";
                } elseif (!$branch->is_active) {
                    $errors[] = "Branch is not active";
                }

                // Validate grade exists
                if ($record->grade && !DB::table('grades')->where('value', $record->grade)->exists()) {
                    $errors[] = "Invalid grade '{$record->grade}'";
                }

                // Validate section exists (if provided)
                if ($record->section) {
                    $sectionExists = DB::table('sections')
                        ->where('branch_id', $record->branch_id)
                        ->where('name', $record->section)
                        ->exists();
                    if (!$sectionExists) {
                        $warnings[] = "Section '{$record->section}' not found for this branch";
                    }
                }
            }

            // Update record with validation results
            $validationStatus = empty($errors) ? 'valid' : 'invalid';
            $record->update([
                'validation_status' => $validationStatus,
                'validation_errors' => empty($errors) ? null : $errors,
                'validation_warnings' => empty($warnings) ? null : $warnings,
            ]);

            if ($validationStatus === 'valid') {
                $validCount++;
            } else {
                $invalidCount++;
            }
        }

        return [
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'total_count' => $records->count(),
        ];
    }

    /**
     * Validate teacher import data
     */
    public function validateTeachers(string $batchId): array
    {
        $records = TeacherImport::where('batch_id', $batchId)->pending()->get();
        $validCount = 0;
        $invalidCount = 0;

        foreach ($records as $record) {
            $errors = [];
            $warnings = [];

            // Basic field validation
            $validator = Validator::make($record->toArray(), [
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'employee_id' => 'required|string|max:255',
                'joining_date' => 'required|date',
                'designation' => 'required|string',
                'employee_type' => 'required|in:Permanent,Contract,Visiting,Temporary',
                'date_of_birth' => 'required|date|before:today',
                'gender' => 'required|in:Male,Female,Other',
                'current_address' => 'required|string',
                'basic_salary' => 'required|numeric|min:0',
            ]);

            if ($validator->fails()) {
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errors = array_merge($errors, $messages);
                }
            }

            // Business logic validation
            if (empty($errors)) {
                // Check email uniqueness
                if (User::where('email', $record->email)->exists()) {
                    $errors[] = "Email '{$record->email}' already exists in the system";
                }

                // Check employee ID uniqueness
                if (DB::table('teachers')->where('employee_id', $record->employee_id)->exists()) {
                    $errors[] = "Employee ID '{$record->employee_id}' already exists";
                }

                // Check email uniqueness within this batch
                $duplicateInBatch = TeacherImport::where('batch_id', $batchId)
                    ->where('email', $record->email)
                    ->where('id', '!=', $record->id)
                    ->exists();

                if ($duplicateInBatch) {
                    $errors[] = "Duplicate email '{$record->email}' found in row " . $record->row_number;
                }

                // Validate branch
                $branch = DB::table('branches')->where('id', $record->branch_id)->first();
                if (!$branch) {
                    $errors[] = "Invalid branch ID";
                } elseif (!$branch->is_active) {
                    $errors[] = "Branch is not active";
                }
            }

            // Update record with validation results
            $validationStatus = empty($errors) ? 'valid' : 'invalid';
            $record->update([
                'validation_status' => $validationStatus,
                'validation_errors' => empty($errors) ? null : $errors,
                'validation_warnings' => empty($warnings) ? null : $warnings,
            ]);

            if ($validationStatus === 'valid') {
                $validCount++;
            } else {
                $invalidCount++;
            }
        }

        return [
            'valid_count' => $validCount,
            'invalid_count' => $invalidCount,
            'total_count' => $records->count(),
        ];
    }

    /**
     * Validate age is appropriate for grade
     */
    protected function validateAgeForGrade(int $age, string $grade): bool
    {
        $gradeAgeMap = [
            'Pre-KG' => [2, 4], 'LKG' => [3, 5], 'UKG' => [4, 6],
            '1' => [5, 7], '2' => [6, 8], '3' => [7, 9],
            '4' => [8, 10], '5' => [9, 11], '6' => [10, 12],
            '7' => [11, 13], '8' => [12, 14], '9' => [13, 15],
            '10' => [14, 16], '11' => [15, 17], '12' => [16, 18]
        ];

        if (!isset($gradeAgeMap[$grade])) {
            return true; // Unknown grade, skip validation
        }

        [$minAge, $maxAge] = $gradeAgeMap[$grade];
        return $age >= $minAge && $age <= $maxAge;
    }

    /**
     * Import validated students to production
     */
    public function importStudentsToProduction(string $batchId, bool $skipInvalid = true): array
    {
        $query = StudentImport::where('batch_id', $batchId)->notImported();

        if ($skipInvalid) {
            $query->valid();
        }

        $records = $query->get();
        $importedCount = 0;
        $failedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($records as $record) {
                try {
                    // Create user account
                    $user = User::create([
                        'first_name' => $record->first_name,
                        'last_name' => $record->last_name,
                        'email' => $record->email,
                        'phone' => $record->phone,
                        'password' => Hash::make($record->password ?? 'Welcome@123'),
                        'role' => 'Student',
                        'user_type' => 'Student',
                        'branch_id' => $record->branch_id,
                        'is_active' => true,
                    ]);

                    // Create student record
                    $studentId = DB::table('students')->insertGetId([
                        'user_id' => $user->id,
                        'branch_id' => $record->branch_id,
                        'admission_number' => $record->admission_number,
                        'admission_date' => $record->admission_date,
                        'roll_number' => $record->roll_number,
                        'registration_number' => $record->registration_number,
                        'grade' => $record->grade,
                        'section' => $record->section,
                        'academic_year' => $record->academic_year,
                        'stream' => $record->stream,
                        'date_of_birth' => $record->date_of_birth,
                        'gender' => $record->gender,
                        'blood_group' => $record->blood_group,
                        'religion' => $record->religion,
                        'category' => $record->category,
                        'nationality' => $record->nationality ?? 'Indian',
                        'mother_tongue' => $record->mother_tongue,
                        'current_address' => $record->current_address,
                        'permanent_address' => $record->permanent_address ?? $record->current_address,
                        'city' => $record->city,
                        'state' => $record->state,
                        'country' => $record->country ?? 'India',
                        'pincode' => $record->pincode,
                        'father_name' => $record->father_name,
                        'father_phone' => $record->father_phone,
                        'father_email' => $record->father_email,
                        'father_occupation' => $record->father_occupation,
                        'father_annual_income' => $record->father_annual_income,
                        'mother_name' => $record->mother_name,
                        'mother_phone' => $record->mother_phone,
                        'mother_email' => $record->mother_email,
                        'mother_occupation' => $record->mother_occupation,
                        'mother_annual_income' => $record->mother_annual_income,
                        'guardian_name' => $record->guardian_name,
                        'guardian_relation' => $record->guardian_relation,
                        'guardian_phone' => $record->guardian_phone,
                        'emergency_contact_name' => $record->emergency_contact_name,
                        'emergency_contact_phone' => $record->emergency_contact_phone,
                        'emergency_contact_relation' => $record->emergency_contact_relation,
                        'previous_school' => $record->previous_school,
                        'previous_grade' => $record->previous_grade,
                        'previous_percentage' => $record->previous_percentage,
                        'transfer_certificate_number' => $record->transfer_certificate_number,
                        'medical_history' => $record->medical_history,
                        'allergies' => $record->allergies,
                        'medications' => $record->medications,
                        'height_cm' => $record->height_cm,
                        'weight_kg' => $record->weight_kg,
                        'student_status' => 'Active',
                        'admission_status' => 'Admitted',
                        'remarks' => $record->remarks,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    // Update user with student ID
                    $user->update(['user_type_id' => $studentId]);

                    // Mark as imported
                    $record->update([
                        'imported_to_production' => true,
                        'imported_user_id' => $user->id,
                        'imported_student_id' => $studentId,
                        'imported_at' => now(),
                    ]);

                    $importedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to import student', [
                        'batch_id' => $batchId,
                        'row' => $record->row_number,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            }

            DB::commit();

            return [
                'imported_count' => $importedCount,
                'failed_count' => $failedCount,
                'total_attempted' => $records->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import transaction failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Import validated teachers to production
     */
    public function importTeachersToProduction(string $batchId, bool $skipInvalid = true): array
    {
        $query = TeacherImport::where('batch_id', $batchId)->notImported();

        if ($skipInvalid) {
            $query->valid();
        }

        $records = $query->get();
        $importedCount = 0;
        $failedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($records as $record) {
                try {
                    // Create user account
                    $user = User::create([
                        'first_name' => $record->first_name,
                        'last_name' => $record->last_name,
                        'email' => $record->email,
                        'phone' => $record->phone,
                        'password' => Hash::make($record->password ?? 'Welcome@123'),
                        'role' => 'Teacher',
                        'user_type' => 'Teacher',
                        'branch_id' => $record->branch_id,
                        'is_active' => true,
                    ]);

                    // Create teacher record (if teachers table exists)
                    if (Schema::hasTable('teachers')) {
                        $teacherId = DB::table('teachers')->insertGetId([
                            'user_id' => $user->id,
                            'branch_id' => $record->branch_id,
                            'employee_id' => $record->employee_id,
                            'joining_date' => $record->joining_date,
                            'leaving_date' => $record->leaving_date,
                            'designation' => $record->designation,
                            'employee_type' => $record->employee_type,
                            'qualification' => json_encode($record->qualification),
                            'experience_years' => $record->experience_years ?? 0,
                            'specialization' => $record->specialization,
                            'registration_number' => $record->registration_number,
                            'subjects' => json_encode($record->subjects),
                            'classes_assigned' => json_encode($record->classes_assigned),
                            'is_class_teacher' => $record->is_class_teacher ?? false,
                            'class_teacher_of_grade' => $record->class_teacher_of_grade,
                            'class_teacher_of_section' => $record->class_teacher_of_section,
                            'date_of_birth' => $record->date_of_birth,
                            'gender' => $record->gender,
                            'blood_group' => $record->blood_group,
                            'religion' => $record->religion,
                            'nationality' => $record->nationality ?? 'Indian',
                            'current_address' => $record->current_address,
                            'permanent_address' => $record->permanent_address ?? $record->current_address,
                            'city' => $record->city,
                            'state' => $record->state,
                            'pincode' => $record->pincode,
                            'emergency_contact_name' => $record->emergency_contact_name,
                            'emergency_contact_phone' => $record->emergency_contact_phone,
                            'emergency_contact_relation' => $record->emergency_contact_relation,
                            'salary_grade' => $record->salary_grade,
                            'basic_salary' => $record->basic_salary,
                            'bank_name' => $record->bank_name,
                            'bank_account_number' => $record->bank_account_number,
                            'bank_ifsc_code' => $record->bank_ifsc_code,
                            'pan_number' => $record->pan_number,
                            'aadhar_number' => $record->aadhar_number,
                            'teacher_status' => 'Active',
                            'remarks' => $record->remarks,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // Update user with teacher ID
                        $user->update(['user_type_id' => $teacherId]);

                        // Mark as imported
                        $record->update([
                            'imported_to_production' => true,
                            'imported_user_id' => $user->id,
                            'imported_teacher_id' => $teacherId,
                            'imported_at' => now(),
                        ]);
                    }

                    $importedCount++;
                } catch (\Exception $e) {
                    Log::error('Failed to import teacher', [
                        'batch_id' => $batchId,
                        'row' => $record->row_number,
                        'error' => $e->getMessage(),
                    ]);
                    $failedCount++;
                }
            }

            DB::commit();

            return [
                'imported_count' => $importedCount,
                'failed_count' => $failedCount,
                'total_attempted' => $records->count(),
            ];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Import transaction failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Delete import batch and all related staging data
     */
    public function deleteBatch(string $batchId): bool
    {
        DB::beginTransaction();

        try {
            StudentImport::where('batch_id', $batchId)->delete();
            TeacherImport::where('batch_id', $batchId)->delete();
            ImportHistory::where('batch_id', $batchId)->delete();

            DB::commit();
            return true;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to delete batch', ['batch_id' => $batchId, 'error' => $e->getMessage()]);
            return false;
        }
    }
}

