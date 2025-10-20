<?php

namespace App\Http\Controllers;

use App\Models\ImportHistory;
use App\Models\StudentImport;
use App\Models\TeacherImport;
use App\Services\ImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ImportController extends Controller
{
    protected ImportService $importService;

    public function __construct(ImportService $importService)
    {
        $this->importService = $importService;
    }

    /**
     * Get available import modules
     */
    public function getModules()
    {
        try {
            $modules = [
                [
                    'id' => 'student',
                    'name' => 'Students',
                    'icon' => 'school',
                    'description' => 'Import student records with admission details',
                    'requiresContext' => true,
                    'contextFields' => ['branch', 'grade', 'section', 'academic_year'],
                    'lastImport' => ImportHistory::where('entity_type', 'student')
                        ->where('status', 'completed')
                        ->latest()
                        ->value('created_at'),
                    'totalRecords' => \DB::table('students')->count(),
                ],
                [
                    'id' => 'teacher',
                    'name' => 'Teachers',
                    'icon' => 'person',
                    'description' => 'Import teacher records with employment details',
                    'requiresContext' => true,
                    'contextFields' => ['branch'],
                    'lastImport' => ImportHistory::where('entity_type', 'teacher')
                        ->where('status', 'completed')
                        ->latest()
                        ->value('created_at'),
                    'totalRecords' => \DB::table('users')->where('role', 'Teacher')->count(),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $modules,
            ]);
        } catch (\Exception $e) {
            Log::error('Get import modules error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch import modules',
            ], 500);
        }
    }

    /**
     * Upload and process Excel file
     */
    public function upload(Request $request, string $entity)
    {
        try {
            $validator = Validator::make($request->all(), [
                'file' => 'required|file|mimes:xlsx,xls,csv|max:10240', // 10MB max
                'branch_id' => 'required|exists:branches,id',
                'grade' => $entity === 'student' ? 'required|string' : 'nullable',
                'section' => 'nullable|string',
                'academic_year' => $entity === 'student' ? 'required|string' : 'nullable',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $file = $request->file('file');
            $context = [
                'branch_id' => $request->branch_id,
                'grade' => $request->grade,
                'section' => $request->section,
                'academic_year' => $request->academic_year,
            ];

            // Create import history record
            $importHistory = $this->importService->createImportBatch(
                $entity,
                auth()->id(),
                $request->branch_id,
                $file->getClientOriginalName(),
                $file->getSize(),
                $context
            );

            // Store file temporarily
            $path = $file->storeAs('imports/temp', $importHistory->batch_id . '.' . $file->getClientOriginalExtension());

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'batch_id' => $importHistory->batch_id,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'status' => 'uploaded',
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Upload error', ['entity' => $entity, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Upload failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Validate imported data
     */
    public function validate(Request $request, string $entity, string $batchId)
    {
        try {
            $importHistory = ImportHistory::where('batch_id', $batchId)->firstOrFail();

            // Update status to validating
            $importHistory->update([
                'status' => 'validating',
                'validation_started_at' => now(),
            ]);

            // Read and parse Excel file
            $filePath = storage_path('app/imports/temp/' . $batchId . '.' . pathinfo($importHistory->file_name, PATHINFO_EXTENSION));

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload file not found',
                ], 404);
            }

            // Parse Excel and insert to staging
            $data = $this->parseExcelFile($filePath, $entity);

            $context = $importHistory->import_context ?? [];

            if ($entity === 'student') {
                $inserted = $this->importService->insertStudentsStagingData($batchId, $data, $context);
                $validationResult = $this->importService->validateStudents($batchId);
            } else if ($entity === 'teacher') {
                $inserted = $this->importService->insertTeachersStagingData($batchId, $data, $context);
                $validationResult = $this->importService->validateTeachers($batchId);
            }

            // Update import history
            $importHistory->update([
                'status' => 'validated',
                'total_rows' => $validationResult['total_count'],
                'valid_rows' => $validationResult['valid_count'],
                'invalid_rows' => $validationResult['invalid_count'],
                'validation_completed_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Validation completed',
                'data' => [
                    'batch_id' => $batchId,
                    'total_rows' => $validationResult['total_count'],
                    'valid_rows' => $validationResult['valid_count'],
                    'invalid_rows' => $validationResult['invalid_count'],
                    'status' => 'validated',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Validation error', ['entity' => $entity, 'batch_id' => $batchId, 'error' => $e->getMessage()]);

            ImportHistory::where('batch_id', $batchId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Get preview of validation results with pagination
     */
    public function preview(Request $request, string $entity, string $batchId)
    {
        try {
            $perPage = $request->get('per_page', 25);
            $status = $request->get('status'); // 'valid', 'invalid', 'all'

            $model = $entity === 'student' ? StudentImport::class : TeacherImport::class;

            $query = $model::where('batch_id', $batchId);

            if ($status === 'valid') {
                $query->valid();
            } elseif ($status === 'invalid') {
                $query->invalid();
            }

            $records = $query->orderBy('row_number')->paginate($perPage);

            // Get summary
            $summary = [
                'total' => $model::where('batch_id', $batchId)->count(),
                'valid' => $model::where('batch_id', $batchId)->valid()->count(),
                'invalid' => $model::where('batch_id', $batchId)->invalid()->count(),
                'imported' => $model::where('batch_id', $batchId)->where('imported_to_production', true)->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $records->items(),
                'summary' => $summary,
                'meta' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                    'last_page' => $records->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Preview error', ['entity' => $entity, 'batch_id' => $batchId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch preview',
            ], 500);
        }
    }

    /**
     * Commit import to production
     */
    public function commit(Request $request, string $entity, string $batchId)
    {
        try {
            $importHistory = ImportHistory::where('batch_id', $batchId)->firstOrFail();

            $validator = Validator::make($request->all(), [
                'skip_invalid' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            $skipInvalid = $request->get('skip_invalid', true);

            // Update status to importing
            $importHistory->update([
                'status' => 'importing',
                'import_started_at' => now(),
            ]);

            // Import to production
            if ($entity === 'student') {
                $result = $this->importService->importStudentsToProduction($batchId, $skipInvalid);
            } else if ($entity === 'teacher') {
                $result = $this->importService->importTeachersToProduction($batchId, $skipInvalid);
            }

            // Update import history
            $importHistory->update([
                'status' => 'completed',
                'imported_rows' => $result['imported_count'],
                'import_completed_at' => now(),
            ]);

            // Clean up temp file
            $this->cleanupTempFile($batchId, $importHistory->file_name);

            return response()->json([
                'success' => true,
                'message' => "Successfully imported {$result['imported_count']} records",
                'data' => [
                    'batch_id' => $batchId,
                    'imported_count' => $result['imported_count'],
                    'failed_count' => $result['failed_count'],
                    'total_attempted' => $result['total_attempted'],
                    'status' => 'completed',
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Import commit error', ['entity' => $entity, 'batch_id' => $batchId, 'error' => $e->getMessage()]);

            ImportHistory::where('batch_id', $batchId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Cancel/delete import batch
     */
    public function cancel(string $entity, string $batchId)
    {
        try {
            $importHistory = ImportHistory::where('batch_id', $batchId)->firstOrFail();

            // Clean up temp file
            $this->cleanupTempFile($batchId, $importHistory->file_name);

            // Delete batch and staging data
            $this->importService->deleteBatch($batchId);

            return response()->json([
                'success' => true,
                'message' => 'Import cancelled successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Cancel import error', ['entity' => $entity, 'batch_id' => $batchId, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel import',
            ], 500);
        }
    }

    /**
     * Get import history
     */
    public function history(Request $request)
    {
        try {
            $query = ImportHistory::with(['uploader:id,first_name,last_name,email', 'branch:id,name,code'])
                ->orderBy('created_at', 'desc');

            if ($request->has('entity_type')) {
                $query->where('entity_type', $request->entity_type);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('days')) {
                $query->recent($request->days);
            }

            $perPage = $request->get('per_page', 25);
            $history = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $history->items(),
                'meta' => [
                    'current_page' => $history->currentPage(),
                    'per_page' => $history->perPage(),
                    'total' => $history->total(),
                    'last_page' => $history->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Import history error', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch import history',
            ], 500);
        }
    }

    /**
     * Download Excel template
     */
    public function downloadTemplate(string $entity)
    {
        try {
            if ($entity === 'student') {
                return $this->generateStudentTemplate();
            } elseif ($entity === 'teacher') {
                return $this->generateTeacherTemplate();
            }

            return response()->json([
                'success' => false,
                'message' => 'Invalid entity type',
            ], 400);
        } catch (\Exception $e) {
            Log::error('Template download error', ['entity' => $entity, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate template',
            ], 500);
        }
    }

    /**
     * Parse Excel file (simplified version)
     */
    protected function parseExcelFile(string $filePath, string $entity): array
    {
        // For now, return sample data to test the flow
        // In production, you would use PhpSpreadsheet or Laravel Excel
        if ($entity === 'student') {
            return [
                [
                    'first_name' => 'John',
                    'last_name' => 'Doe',
                    'email' => 'john.doe@example.com',
                    'phone' => '9876543210',
                    'admission_number' => 'STU-2024-001',
                    'admission_date' => '2024-04-15',
                    'grade' => '5',
                    'section' => 'A',
                    'academic_year' => '2024-2025',
                    'date_of_birth' => '2014-05-20',
                    'gender' => 'Male',
                    'current_address' => '123 Main Street',
                    'city' => 'Mumbai',
                    'state' => 'Maharashtra',
                    'pincode' => '400001',
                    'father_name' => 'Rajesh Doe',
                    'father_phone' => '9876543210',
                    'mother_name' => 'Priya Doe',
                    'emergency_contact_name' => 'Rajesh Doe',
                    'emergency_contact_phone' => '9876543210',
                ],
                [
                    'first_name' => 'Jane',
                    'last_name' => 'Smith',
                    'email' => 'jane.smith@example.com',
                    'phone' => '9876543211',
                    'admission_number' => 'STU-2024-002',
                    'admission_date' => '2024-04-16',
                    'grade' => '5',
                    'section' => 'A',
                    'academic_year' => '2024-2025',
                    'date_of_birth' => '2014-08-15',
                    'gender' => 'Female',
                    'current_address' => '456 Park Avenue',
                    'city' => 'Delhi',
                    'state' => 'Delhi',
                    'pincode' => '110001',
                    'father_name' => 'Michael Smith',
                    'father_phone' => '9876543212',
                    'mother_name' => 'Jennifer Smith',
                    'emergency_contact_name' => 'Michael Smith',
                    'emergency_contact_phone' => '9876543212',
                ]
            ];
        } else {
            return [
                [
                    'first_name' => 'Alice',
                    'last_name' => 'Johnson',
                    'email' => 'alice.johnson@example.com',
                    'phone' => '9876543213',
                    'employee_id' => 'TCH-2024-001',
                    'joining_date' => '2024-01-01',
                    'designation' => 'Senior Teacher',
                    'employee_type' => 'Permanent',
                    'date_of_birth' => '1985-03-15',
                    'gender' => 'Female',
                    'current_address' => '789 Lake Road',
                    'city' => 'Bangalore',
                    'state' => 'Karnataka',
                    'pincode' => '560001',
                    'basic_salary' => '50000',
                    'emergency_contact_name' => 'Bob Johnson',
                    'emergency_contact_phone' => '9876543214',
                ]
            ];
        }
    }

    /**
     * Generate student template
     */
    protected function generateStudentTemplate()
    {
        // For now, return a simple CSV template
        // In production, you would use PhpSpreadsheet to create Excel
        $headers = [
            'first_name', 'last_name', 'email', 'phone', 'admission_number', 'admission_date',
            'grade', 'section', 'roll_number', 'academic_year', 'date_of_birth', 'gender',
            'blood_group', 'current_address', 'city', 'state', 'country', 'pincode',
            'father_name', 'father_phone', 'father_email', 'mother_name', 'mother_phone',
            'emergency_contact_name', 'emergency_contact_phone', 'remarks'
        ];

        $sampleData = [
            'John', 'Doe', 'john.doe@example.com', '9876543210', 'STU-2024-001', '2024-04-15',
            '5', 'A', '101', '2024-2025', '2014-05-20', 'Male',
            'A+', '123 Main Street', 'Mumbai', 'Maharashtra', 'India', '400001',
            'Rajesh Doe', '9876543210', 'rajesh@example.com', 'Priya Doe', '9876543211',
            'Rajesh Doe', '9876543210', 'Good student'
        ];

        $csvContent = implode(',', $headers) . "\n" . implode(',', $sampleData);
        
        $fileName = 'student_import_template_' . date('Y-m-d') . '.csv';
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * Generate teacher template
     */
    protected function generateTeacherTemplate()
    {
        // For now, return a simple CSV template
        $headers = [
            'first_name', 'last_name', 'email', 'phone', 'employee_id', 'joining_date',
            'designation', 'employee_type', 'date_of_birth', 'gender', 'current_address',
            'city', 'state', 'pincode', 'basic_salary', 'emergency_contact_name',
            'emergency_contact_phone', 'remarks'
        ];

        $sampleData = [
            'Jane', 'Smith', 'jane.smith@example.com', '9876543212', 'TCH-2024-001', '2024-01-01',
            'Senior Teacher', 'Permanent', '1985-03-15', 'Female', '456 Park Avenue',
            'Delhi', 'Delhi', '110001', '50000', 'John Smith', '9876543213', 'Excellent teacher'
        ];

        $csvContent = implode(',', $headers) . "\n" . implode(',', $sampleData);
        
        $fileName = 'teacher_import_template_' . date('Y-m-d') . '.csv';
        
        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
    }

    /**
     * Clean up temporary file
     */
    protected function cleanupTempFile(string $batchId, string $fileName): void
    {
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $path = 'imports/temp/' . $batchId . '.' . $extension;

        if (Storage::exists($path)) {
            Storage::delete($path);
        }
    }
}

