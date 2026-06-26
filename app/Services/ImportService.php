<?php

namespace App\Services;

use App\Models\ImportHistory;
use App\Models\StudentImport;
use App\Models\TeacherImport;
use App\Models\User;
use App\Models\Role;
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
            // Prepare the data to insert
            $importData = [
                'batch_id' => $batchId,
                'row_number' => $rowNumber++,
                'branch_id' => $context['branch_id'] ?? null,
                'validation_status' => 'pending',
            ];

            // Use context values for grade, section, academic_year (these are set at upload time)
            // But allow Excel to override via grade_override, section_override, etc.
            $importData['grade'] = $context['grade'] ?? null;
            $importData['section'] = $context['section'] ?? null;
            $importData['academic_year'] = $context['academic_year'] ?? null;

            // If Excel has override fields, use them to override context values
            if (isset($row['grade_override']) && !empty($row['grade_override'])) {
                $importData['grade_override'] = $row['grade_override'];
                $importData['grade'] = $row['grade_override'];
            }
            if (isset($row['section_override']) && !empty($row['section_override'])) {
                $importData['section_override'] = $row['section_override'];
                $importData['section'] = $row['section_override'];
            }
            if (isset($row['academic_year_override']) && !empty($row['academic_year_override'])) {
                $importData['academic_year_override'] = $row['academic_year_override'];
                $importData['academic_year'] = $row['academic_year_override'];
            }

            // Remove override fields from row data to avoid duplication
            unset($row['grade_override'], $row['section_override'], $row['academic_year_override']);
            
            // Also remove grade/section/academic_year from Excel row if present
            // (we use context values, not Excel values for these)
            unset($row['grade'], $row['section'], $row['academic_year']);

            // Merge Excel row data (all other fields)
            $importData = array_merge($importData, $row);

            // 🔥 Debug first row to see what data we have

            // Filter out null values for better performance (optional, but cleaner)
            // Actually, we should keep null values as they might be needed for validation
            // $importData = array_filter($importData, fn($value) => $value !== null);

            try {
                StudentImport::create($importData);
                $inserted++;
            } catch (\Exception $e) {
                Log::error('Failed to insert student import row', [
                    'batch_id' => $batchId,
                    'row_number' => $rowNumber - 1,
                    'error' => $e->getMessage(),
                    'data' => $importData
                ]);
                // Continue with next row instead of failing completely
            }
        }

        Log::info('Student staging data inserted', [
            'batch_id' => $batchId,
            'inserted_count' => $inserted,
            'total_rows' => count($data)
        ]);

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
            // Prepare the data to insert
            $importData = [
                'batch_id' => $batchId,
                'row_number' => $rowNumber++,
                'branch_id' => $context['branch_id'] ?? null,
                'validation_status' => 'pending',
            ];

            // Handle boolean fields. is_class_teacher is NOT NULL in the staging table,
            // so always coerce it (defaulting to false when the column is blank/missing) and
            // remove it from $row so the array_merge below can't reintroduce a null value.
            $importData['is_class_teacher'] = $this->convertToBoolean($row['is_class_teacher'] ?? false);
            unset($row['is_class_teacher']);

            // Merge Excel row data (all other fields)
            $importData = array_merge($importData, $row);

            try {
                TeacherImport::create($importData);
                $inserted++;
            } catch (\Exception $e) {
                Log::error('Failed to insert teacher import row', [
                    'batch_id' => $batchId,
                    'row_number' => $rowNumber - 1,
                    'error' => $e->getMessage(),
                    'data' => $importData
                ]);
                // Continue with next row instead of failing completely
            }
        }

        Log::info('Teacher staging data inserted', [
            'batch_id' => $batchId,
            'inserted_count' => $inserted,
            'total_rows' => count($data)
        ]);

        return $inserted;
    }

    /**
     * Convert various boolean representations to actual boolean
     */
    protected function convertToBoolean($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        
        if (is_numeric($value)) {
            return (bool)$value;
        }
        
        $stringValue = strtolower(trim((string)$value));
        return in_array($stringValue, ['yes', 'true', '1', 'y', 'on']);
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
                'branch_id' => 'required|exists:branches,id', // 🔥 Added branch_id validation
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'admission_number' => 'required|string|max:255',
                'admission_date' => 'required|date',
                'grade' => 'required|string',
                'section' => 'nullable|string', // 🔥 Section is optional (not required)
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
                    foreach ($messages as $message) {
                        // 🔥 Make error messages more descriptive
                        if (strpos($message, 'required') !== false) {
                            $errors[] = "Missing required field: {$field}";
                        } else {
                            $errors[] = "{$field}: {$message}";
                        }
                    }
                }
            }

            // Business logic validation
            if (empty($errors)) {
                // Check email uniqueness
                if (User::where('email', $record->email)->exists()) {
                    $errors[] = "Email '{$record->email}' already exists in the system";
                }

                // 🔥 Check phone uniqueness
                if ($record->phone && User::where('phone', $record->phone)->exists()) {
                    $errors[] = "Phone number '{$record->phone}' already exists in the system";
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
                    $errors[] = "Duplicate email '{$record->email}' found in this import";
                }
                
                // 🔥 Check phone uniqueness within this batch
                if ($record->phone) {
                    $duplicatePhoneInBatch = StudentImport::where('batch_id', $batchId)
                        ->where('phone', $record->phone)
                        ->where('id', '!=', $record->id)
                        ->exists();

                    if ($duplicatePhoneInBatch) {
                        $errors[] = "Duplicate phone '{$record->phone}' found in this import";
                    }
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
                if ($record->grade && !DB::table('grades')->where('school_id', $branch?->school_id)->where('value', $record->grade)->exists()) {
                    $errors[] = "Invalid grade '{$record->grade}'";
                }

                // Validate section exists (if provided)
                if ($record->section) {
                    $sectionExists = DB::table('sections')
                        ->where('branch_id', $record->branch_id)
                        ->where('name', $record->section)
                        ->where('grade_level', $record->grade) // 🔥 Also check grade matches
                        ->exists();
                    if (!$sectionExists) {
                        $warnings[] = "Section '{$record->section}' not found for Branch ID {$record->branch_id}, Grade {$record->grade}";
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
            // Pre-Primary Grades
            'PlaySchool' => [2, 3],
            'Nursery' => [3, 4],
            'LKG' => [4, 5],
            'UKG' => [5, 6],
            // Primary Grades
            '1' => [6, 7],
            '2' => [7, 8],
            '3' => [8, 9],
            '4' => [9, 10],
            '5' => [10, 11],
            // Middle School
            '6' => [11, 12],
            '7' => [12, 13],
            '8' => [13, 14],
            // Secondary
            '9' => [14, 15],
            '10' => [15, 16],
            // Senior Secondary
            '11' => [16, 17],
            '12' => [17, 18]
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
    public function importStudentsToProduction(string $batchId, bool $skipInvalid = true, ?int $createdBy = null): array
    {
        $query = StudentImport::where('batch_id', $batchId)->notImported();

        if ($skipInvalid) {
            $query->valid();
        }

        $records = $query->get();
        $importedCount = 0;
        $failedCount = 0;

        // Memoize branch->school_id and academic-year-name->id lookups so the loop
        // stays O(1) per row (no N+1) even for large batches.
        $schoolIdByBranch = [];
        $academicYearIdByName = [];
        $hasEnrollments = Schema::hasTable('student_enrollments');

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
                    
                    // Assign Student role via roles relationship
                    $studentRole = Role::where('slug', 'student')->first();
                    if ($studentRole) {
                        $user->roles()->attach($studentRole->id, [
                            'is_primary' => true,
                            'branch_id' => $record->branch_id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    // Resolve school_id (from branch) and academic_year_id (from name) the same
                    // way StudentController::store does, so imported students are properly
                    // school-scoped and tied to an academic year. Memoized to avoid N+1.
                    $branchId = $record->branch_id;
                    if (!array_key_exists($branchId, $schoolIdByBranch)) {
                        $schoolIdByBranch[$branchId] = DB::table('branches')->where('id', $branchId)->value('school_id');
                    }
                    $schoolId = $schoolIdByBranch[$branchId];

                    $ayName = $record->academic_year;
                    if ($ayName !== null && !array_key_exists($ayName, $academicYearIdByName)) {
                        $academicYearIdByName[$ayName] = DB::table('academic_years')->where('name', $ayName)->value('id');
                    }
                    $academicYearId = $record->academic_year_id ?? ($ayName !== null ? $academicYearIdByName[$ayName] : null);

                    // Create student record
                    // ✅ Only include columns that exist in the students table
                    $studentData = [
                        'user_id' => $user->id,
                        'branch_id' => $branchId,
                        'school_id' => $schoolId,
                        'academic_year_id' => $academicYearId,
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
                        'father_qualification' => $record->father_qualification,
                        'father_organization' => $record->father_organization,
                        'father_designation' => $record->father_designation,
                        'mother_name' => $record->mother_name,
                        'mother_phone' => $record->mother_phone,
                        'mother_email' => $record->mother_email,
                        'mother_occupation' => $record->mother_occupation,
                        'mother_annual_income' => $record->mother_annual_income,
                        'mother_qualification' => $record->mother_qualification,
                        'mother_organization' => $record->mother_organization,
                        'mother_designation' => $record->mother_designation,
                        'guardian_name' => $record->guardian_name,
                        'guardian_relation' => $record->guardian_relation,
                        'guardian_phone' => $record->guardian_phone,
                        'guardian_qualification' => $record->guardian_qualification,
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
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    $studentId = DB::table('students')->insertGetId($studentData);

                    // Update user with student ID
                    $user->update(['user_type_id' => $studentId]);

                    // Create the active enrollment row that StudentController::store also creates.
                    // Grade/section/group/attendance/promotion views read from student_enrollments,
                    // so without this the imported student is invisible to them.
                    if ($hasEnrollments && $academicYearId) {
                        DB::table('student_enrollments')->updateOrInsert(
                            ['student_id' => $studentId, 'academic_year_id' => (int) $academicYearId],
                            [
                                'school_id' => $schoolId,
                                'branch_id' => $branchId,
                                'grade' => (string) $record->grade,
                                'section' => $record->section,
                                'roll_number' => $record->roll_number,
                                'status' => 'Active',
                                'created_by' => $createdBy,
                                'updated_by' => $createdBy,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }

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

        // Memoize branch->school_id to avoid N+1 inside the loop.
        $schoolIdByBranch = [];

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
                    
                    // Assign Teacher role via roles relationship
                    $teacherRole = Role::where('slug', 'teacher')->first();
                    if ($teacherRole) {
                        $user->roles()->attach($teacherRole->id, [
                            'is_primary' => true,
                            'branch_id' => $record->branch_id,
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);
                    }

                    // Create teacher record (if teachers table exists)
                    if (Schema::hasTable('teachers')) {
                        // Resolve school_id from branch (memoized) so the teacher is school-scoped,
                        // matching TeacherController::store.
                        $branchId = $record->branch_id;
                        if (!array_key_exists($branchId, $schoolIdByBranch)) {
                            $schoolIdByBranch[$branchId] = DB::table('branches')->where('id', $branchId)->value('school_id');
                        }

                        $teacherId = DB::table('teachers')->insertGetId([
                            'user_id' => $user->id,
                            'branch_id' => $branchId,
                            'school_id' => $schoolIdByBranch[$branchId],
                            'employee_id' => $record->employee_id,
                            'joining_date' => $record->joining_date,
                            'leaving_date' => $record->leaving_date,
                            'designation' => $record->designation,
                            'employee_type' => $record->employee_type,
                            // qualification is stored as plain text (model accessor handles JSON legacy),
                            // matching the create flow; subjects/classes_assigned are JSON arrays.
                            'qualification' => $record->qualification,
                            'experience_years' => $record->experience_years ?? 0,
                            'specialization' => $record->specialization,
                            'registration_number' => $record->registration_number,
                            'subjects' => json_encode($this->toList($record->subjects)),
                            'classes_assigned' => json_encode($this->toList($record->classes_assigned)),
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
     * Normalize a value into a list for JSON array columns (subjects, classes_assigned).
     * Accepts an array, a comma/semicolon/pipe-separated string, or null.
     *
     * @return array<int, string>
     */
    private function toList($value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : $v, $value), fn ($v) => $v !== null && $v !== ''));
        }
        if ($value === null || $value === '') {
            return [];
        }
        $parts = preg_split('/[,;|]/', (string) $value);
        return array_values(array_filter(array_map('trim', $parts), fn ($v) => $v !== ''));
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

