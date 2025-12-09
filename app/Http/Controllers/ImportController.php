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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Font;

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
            // 🔥 Debug logging
            $file = $request->file('file');
            Log::info('Import upload request received', [
                'entity' => $entity,
                'has_file' => $request->hasFile('file'),
                'file_name' => $file ? $file->getClientOriginalName() : null,
                'file_extension' => $file ? $file->getClientOriginalExtension() : null,
                'file_mime' => $file ? $file->getMimeType() : null,
                'file_size' => $file ? $file->getSize() : null,
                'branch_id' => $request->input('branch_id'),
                'grade' => $request->input('grade'),
                'section' => $request->input('section'),
                'academic_year' => $request->input('academic_year'),
                'content_type' => $request->header('Content-Type'),
                'all_inputs' => $request->except(['file'])
            ]);

            $validator = Validator::make($request->all(), [
                'file' => 'required|file|max:10240', // 10MB max - Accept any file type for now
                'branch_id' => 'required|exists:branches,id',
                'grade' => $entity === 'student' ? 'required|string' : 'nullable|string',
                'section' => 'nullable|string',
                'academic_year' => $entity === 'student' ? 'required|string' : 'nullable|string',
            ], [
                'file.required' => 'Please select an Excel file to upload',
                'file.mimes' => 'File must be Excel format (.xlsx, .xls) or CSV (.csv)',
                'file.file' => 'The uploaded file is invalid',
                'branch_id.required' => 'Branch is required',
                'branch_id.exists' => 'Selected branch does not exist',
                'grade.required' => 'Grade is required for student import',
                'academic_year.required' => 'Academic year is required for student import',
            ]);

            if ($validator->fails()) {
                Log::error('Import validation failed', [
                    'errors' => $validator->errors()->toArray()
                ]);
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors(),
                ], 422);
            }

            // 🔥 Additional file extension check (more flexible than MIME type)
            $file = $request->file('file');
            $extension = strtolower($file->getClientOriginalExtension());
            $allowedExtensions = ['xlsx', 'xls', 'csv'];
            
            if (!in_array($extension, $allowedExtensions)) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid file type. Please upload Excel (.xlsx, .xls) or CSV (.csv) files only.",
                    'errors' => [
                        'file' => ["File extension '{$extension}' is not allowed. Use: " . implode(', ', $allowedExtensions)]
                    ]
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
            $storedFileName = $importHistory->batch_id . '.' . $file->getClientOriginalExtension();
            
            // 🔥 Get file info BEFORE moving (after move, original is gone)
            $originalFileName = $file->getClientOriginalName();
            $originalFileSize = $file->getSize();
            
            // 🔥 Ensure directory exists and is writable
            $tempDir = storage_path('app' . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'temp');
            if (!is_dir($tempDir)) {
                mkdir($tempDir, 0775, true);
            }
            
            // 🔥 Use direct file move instead of Storage facade
            $fullPath = $tempDir . DIRECTORY_SEPARATOR . $storedFileName;
            
            try {
                $file->move($tempDir, $storedFileName);
            } catch (\Exception $e) {
                Log::error('File move failed', [
                    'error' => $e->getMessage(),
                    'temp_dir' => $tempDir,
                    'target_file' => $storedFileName,
                    'is_writable' => is_writable($tempDir)
                ]);
                throw new \Exception('Failed to store file: ' . $e->getMessage());
            }
            
            Log::info('File stored successfully', [
                'batch_id' => $importHistory->batch_id,
                'full_path' => $fullPath,
                'file_exists' => file_exists($fullPath),
                'file_size' => file_exists($fullPath) ? filesize($fullPath) : 0,
                'stored_filename' => $storedFileName,
                'directory_writable' => is_writable($tempDir)
            ]);
            
            if (!file_exists($fullPath)) {
                throw new \Exception('File upload failed - file not found after storage');
            }

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => [
                    'batch_id' => $importHistory->batch_id,
                    'file_name' => $originalFileName,
                    'file_size' => $originalFileSize,
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
            // 🔥 Construct path properly for Windows/Linux
            $extension = pathinfo($importHistory->file_name, PATHINFO_EXTENSION);
            $storedFileName = $batchId . '.' . $extension;
            $filePath = storage_path('app') . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'temp' . DIRECTORY_SEPARATOR . $storedFileName;

            // 🔥 Debug logging
            $tempDir = storage_path('app') . DIRECTORY_SEPARATOR . 'imports' . DIRECTORY_SEPARATOR . 'temp';
            Log::info('Looking for import file', [
                'batch_id' => $batchId,
                'original_filename' => $importHistory->file_name,
                'extension' => $extension,
                'stored_filename' => $storedFileName,
                'looking_at_path' => $filePath,
                'file_exists' => file_exists($filePath),
                'temp_directory' => $tempDir,
                'temp_dir_exists' => is_dir($tempDir),
                'files_in_temp' => is_dir($tempDir) ? scandir($tempDir) : 'Directory not found'
            ]);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Upload file not found',
                    'debug' => [
                        'expected_path' => $filePath,
                        'batch_id' => $batchId,
                        'original_filename' => $importHistory->file_name,
                        'files_in_temp' => is_dir($tempDir) ? scandir($tempDir) : []
                    ]
                ], 404);
            }

            // Parse Excel and insert to staging
            $data = $this->parseExcelFile($filePath, $entity);

            // 🔥 Critical: Log parsed data count
            Log::info('Data parsed from file', [
                'batch_id' => $batchId,
                'entity' => $entity,
                'data_count' => count($data),
                'file_path' => $filePath,
                'first_row_sample' => !empty($data) ? array_slice($data, 0, 1) : 'NO DATA'
            ]);

            if (empty($data)) {
                Log::error('No data parsed from file', [
                    'batch_id' => $batchId,
                    'entity' => $entity,
                    'file_path' => $filePath,
                    'file_exists' => file_exists($filePath),
                    'file_size' => file_exists($filePath) ? filesize($filePath) : 0
                ]);
                
                ImportHistory::where('batch_id', $batchId)->update([
                    'status' => 'failed',
                    'error_message' => 'No data found in the uploaded file. Please check the file format and ensure it contains data rows.',
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No data found in the uploaded file',
                    'error' => 'The file appears to be empty or could not be parsed. Please check the file format.',
                ], 400);
            }

            $context = $importHistory->import_context ?? [];

            if ($entity === 'student') {
                $inserted = $this->importService->insertStudentsStagingData($batchId, $data, $context);
                Log::info('Student staging data inserted', [
                    'batch_id' => $batchId,
                    'inserted_count' => $inserted,
                    'expected_count' => count($data)
                ]);
                
                // 🔥 Verify records were actually inserted
                $actualCount = \App\Models\StudentImport::where('batch_id', $batchId)->count();
                Log::info('Actual records in database', [
                    'batch_id' => $batchId,
                    'actual_count' => $actualCount,
                    'inserted_count' => $inserted
                ]);
                
                $validationResult = $this->importService->validateStudents($batchId);
            } else if ($entity === 'teacher') {
                $inserted = $this->importService->insertTeachersStagingData($batchId, $data, $context);
                Log::info('Teacher staging data inserted', [
                    'batch_id' => $batchId,
                    'inserted_count' => $inserted,
                    'expected_count' => count($data)
                ]);
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

            // 🔥 Debug logging
            Log::info('Preview request received', [
                'entity' => $entity,
                'batch_id' => $batchId,
                'status_filter' => $status,
                'per_page' => $perPage,
                'page' => $request->get('page', 1)
            ]);

            $model = $entity === 'student' ? StudentImport::class : TeacherImport::class;

            $query = $model::where('batch_id', $batchId);

            if ($status === 'valid') {
                $query->valid();
                Log::info('Filtering for VALID records only');
            } elseif ($status === 'invalid') {
                $query->invalid();
                Log::info('Filtering for INVALID records only');
            } else {
                Log::info('Showing ALL records (no status filter)');
            }

            $records = $query->orderBy('row_number')->paginate($perPage);
            
            // 🔥 Debug the results - check database directly
            $totalInDb = $model::where('batch_id', $batchId)->count();
            $validInDb = $model::where('batch_id', $batchId)->valid()->count();
            $invalidInDb = $model::where('batch_id', $batchId)->invalid()->count();
            $pendingInDb = $model::where('batch_id', $batchId)->pending()->count();
            
            Log::info('Preview results', [
                'batch_id' => $batchId,
                'status_filter_applied' => $status,
                'records_returned' => $records->count(),
                'total_in_page' => $records->total(),
                'total_in_db' => $totalInDb,
                'valid_in_db' => $validInDb,
                'invalid_in_db' => $invalidInDb,
                'pending_in_db' => $pendingInDb
            ]);

            // Get summary
            $summary = [
                'total' => $totalInDb,
                'valid' => $validInDb,
                'invalid' => $invalidInDb,
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
     * Parse Excel file using PhpSpreadsheet
     */
    protected function parseExcelFile(string $filePath, string $entity): array
    {
        try {
            // Determine file type and create appropriate reader
            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            
            // Detect actual file type using MIME type or file signature
            $actualFileType = $this->detectFileType($filePath, $extension);
            
            // Use actual file type, not just extension
            if ($actualFileType === 'csv') {
                $reader = IOFactory::createReader('Csv');
                $reader->setInputEncoding('UTF-8');
                $reader->setDelimiter(',');
                $reader->setEnclosure('"');
                $reader->setSheetIndex(0);
                // 🔥 Important: Set these to ensure all rows are read
                $reader->setReadDataOnly(false);
            } elseif ($actualFileType === 'xls') {
                $reader = IOFactory::createReader('Xls');
            } else {
                // Default to Xlsx (handles .xlsx files)
                $reader = IOFactory::createReader('Xlsx');
            }
            
            // Update extension variable to match actual file type
            $extension = $actualFileType;

            // Load the spreadsheet
            $spreadsheet = $reader->load($filePath);
            $worksheet = $spreadsheet->getActiveSheet();
            
            // Get the highest row and column
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            
            // 🔥 For CSV files, getHighestRow() and getHighestColumn() might not detect correctly
            // So we need to manually count rows and columns
            if ($extension === 'csv') {
                // Use getHighestDataRow() which is more reliable for CSV
                $actualHighestRow = $worksheet->getHighestDataRow();
                
                // If that doesn't work, manually count rows
                if ($actualHighestRow <= 1) {
                    $actualHighestRow = 1;
                    $maxRowsToCheck = 10000; // Reasonable max
                    for ($r = 1; $r <= $maxRowsToCheck; $r++) {
                        $hasData = false;
                        // Check first few columns to see if row has data
                        for ($c = 1; $c <= 5; $c++) {
                            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                            try {
                                $cellValue = $worksheet->getCell($col . $r)->getValue();
                                if (!empty(trim($cellValue))) {
                                    $hasData = true;
                                    break;
                                }
                            } catch (\Exception $e) {
                                // Cell doesn't exist, continue
                                break;
                            }
                        }
                        if ($hasData) {
                            $actualHighestRow = $r;
                        } elseif ($r > $actualHighestRow + 10) {
                            // If we've gone 10 rows without data, stop
                            break;
                        }
                    }
                }
                $highestRow = $actualHighestRow;
                
                // For columns, use getHighestDataColumn() or read header row more thoroughly
                $actualHighestColumn = $worksheet->getHighestDataColumn();
                if (empty($actualHighestColumn) || $actualHighestColumn === 'A') {
                    $maxColumns = 100;
                    $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                    $highestColumnIndex = max($highestColumnIndex, min($maxColumns, 50));
                    $highestColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($highestColumnIndex);
                } else {
                    $highestColumn = $actualHighestColumn;
                }
                
            }

            // Read header row (first row)
            $headers = []; // Will store: ['col_index' => 'normalized_header_name']
            $headerRow = 1;
            $originalHeaders = [];
            
            // 🔥 For CSV, use native PHP CSV reading to preserve empty columns exactly
            if ($extension === 'csv') {
                // 🔥 Validate that this is actually a CSV file, not an Excel file
                $fileHandle = fopen($filePath, 'rb');
                if ($fileHandle === false) {
                    throw new \Exception('Failed to open CSV file');
                }
                
                $firstBytes = fread($fileHandle, 8);
                fclose($fileHandle);
                
                // If file starts with PK (ZIP signature), it's actually an Excel file
                if ($firstBytes && substr($firstBytes, 0, 2) === 'PK') {
                    throw new \Exception('The uploaded file appears to be an Excel file (.xlsx) but was detected as CSV. Please save your file as .xlsx format or ensure the file extension matches the file type.');
                }
                
                // Read CSV file directly using PHP's native functions to preserve empty columns
                $csvFile = fopen($filePath, 'r');
                if ($csvFile === false) {
                    throw new \Exception('Failed to open CSV file');
                }
                
                // Read header row using fgetcsv (preserves empty columns)
                $headerRowData = fgetcsv($csvFile, 0, ',', '"');
                fclose($csvFile);
                
                if ($headerRowData === false || empty($headerRowData)) {
                    throw new \Exception('Failed to read CSV header row');
                }
                
                // Validate header row doesn't contain binary data
                foreach ($headerRowData as $idx => $header) {
                    if (is_string($header) && (preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F]/', $header) || 
                        stripos($header, 'PK') === 0 || 
                        stripos($header, '<?xml') !== false ||
                        stripos($header, 'workbook.xml') !== false ||
                        stripos($header, '[Content_Types]') !== false)) {
                        throw new \Exception('The file appears to be an Excel file (.xlsx) but is being read as CSV. Please save your file as .xlsx format or ensure the file extension matches the file type.');
                    }
                }
                
                // Process each header by index - preserve ALL positions including empty ones
                foreach ($headerRowData as $colIndex => $headerValue) {
                    $headerValue = trim($headerValue ?? '');
                    
                    // Store header even if empty (to preserve column position)
                    if (!empty($headerValue)) {
                        $originalHeaders[$colIndex] = $headerValue;
                        $normalized = $this->normalizeHeader($headerValue);
                        $headers[$colIndex] = $normalized; // Store by index
                        
                    } else {
                        // Store null for empty headers to preserve column position
                        $headers[$colIndex] = null;
                    }
                }
                
            } else {
                // For Excel files, read all columns (including empty ones) to preserve positions
                $maxColIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
                
                // 🔥 Read header row as array for Excel too, to handle empty columns correctly
                $headerArray = $worksheet->rangeToArray('A' . $headerRow . ':' . $highestColumn . $headerRow, null, false, false, false);
                $headerArray = !empty($headerArray) ? $headerArray[0] : [];
                
                foreach ($headerArray as $colIndex => $headerValue) {
                    $headerValue = trim($headerValue ?? '');
                    $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                    
                    if (!empty($headerValue)) {
                        $originalHeaders[$col] = $headerValue;
                        $normalized = $this->normalizeHeader($headerValue);
                        $headers[$colIndex] = $normalized; // Store by index for consistency
                    } else {
                        // Store null for empty headers to preserve column position
                        $headers[$colIndex] = null;
                    }
                }
            }
            
            // Build header mapping only for non-null headers
            $headerMapping = [];
            foreach ($headers as $colIndex => $normalizedHeader) {
                if ($normalizedHeader !== null && isset($originalHeaders[$colIndex])) {
                    $headerMapping[$originalHeaders[$colIndex]] = $normalizedHeader;
                }
            }

            if (empty($headers)) {
                throw new \Exception('No headers found in the Excel file. Please ensure the first row contains column names.');
            }

            // Map headers to database field names
            $fieldMapping = $this->getFieldMapping($entity);
            
            // 🔥 For CSV files, read all data rows once using native PHP CSV functions
            // This preserves empty columns correctly
            $allCsvRows = [];
            if ($extension === 'csv') {
                $csvHandle = fopen($filePath, 'r');
                if ($csvHandle === false) {
                    throw new \Exception('Failed to open CSV file for data reading');
                }
                
                // Skip header row (already read)
                fgetcsv($csvHandle, 0, ',', '"');
                
                // Read all data rows
                while (($csvRow = fgetcsv($csvHandle, 0, ',', '"')) !== false) {
                    $allCsvRows[] = $csvRow;
                }
                fclose($csvHandle);
                
            }
            
            // Read data rows
            $data = [];
            $rowNumber = 0;

            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = [];
                $isEmptyRow = true;
                $hasAnyData = false;

                // 🔥 For CSV files, use pre-loaded CSV rows
                if ($extension === 'csv') {
                    // Get the row data for current row (row 2 = index 0, row 3 = index 1, etc.)
                    $rowArrayIndex = $row - 2;
                    if (!isset($allCsvRows[$rowArrayIndex])) {
                        continue; // Skip if row doesn't exist
                    }
                    $rowArray = $allCsvRows[$rowArrayIndex];
                    
                    
                    // 🔥 CRITICAL: Process ALL headers by index, including empty ones
                    // This ensures column positions match between headers and data
                    foreach ($headers as $colIndex => $headerName) {
                        // Skip if header is null (empty header column)
                        if ($headerName === null) {
                            continue;
                        }
                        
                        // Get value from array by index (0-based)
                        $cellValue = isset($rowArray[$colIndex]) ? $rowArray[$colIndex] : null;
                        
                        // Trim whitespace
                        $cellValue = is_null($cellValue) ? null : trim($cellValue);
                        
                        // Convert empty strings to null
                        if ($cellValue === '') {
                            $cellValue = null;
                        }
                        
                        // Check if row has any data
                        if (!empty($cellValue)) {
                            $isEmptyRow = false;
                            $hasAnyData = true;
                        }

                        // Map header to database field
                        $dbField = $this->mapHeaderToField($headerName, $fieldMapping);
                        
                        
                        if ($dbField) {
                            // Handle date fields
                            if (in_array($dbField, ['admission_date', 'date_of_birth', 'joining_date', 'leaving_date'])) {
                                $rowData[$dbField] = $this->convertDate($cellValue);
                            } else {
                                // Store null for empty values instead of empty string
                                $rowData[$dbField] = $cellValue ?: null;
                            }
                        }
                    }
                } else {
                    // For Excel files, also use array-based reading to preserve column positions
                    // But limit to the actual number of headers we found
                    $maxHeaderIndex = max(array_keys($headers));
                    $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($maxHeaderIndex + 1);
                    $rowArray = $worksheet->rangeToArray('A' . $row . ':' . $lastColumn . $row, null, false, false, false);
                    $rowArray = !empty($rowArray) ? $rowArray[0] : [];
                    
                    // Validate column count matches headers
                    if (count($rowArray) > count($headers)) {
                        // Truncate to match header count
                        $rowArray = array_slice($rowArray, 0, count($headers));
                    }
                    
                    // Process each header by index
                    foreach ($headers as $colIndex => $headerName) {
                        // Skip if header is null (empty header column)
                        if ($headerName === null) {
                            continue;
                        }
                        
                        // Get value from array by index (ensure we don't go beyond array bounds)
                        $cellValue = isset($rowArray[$colIndex]) ? $rowArray[$colIndex] : null;
                        
                        // 🔥 Validate cell value - skip if it looks like binary/corrupted data
                        // Only check if the value is suspiciously long or contains Excel internal structure
                        if (!is_null($cellValue) && is_string($cellValue)) {
                            $trimmedValue = trim($cellValue);
                            // Only flag as binary if it's very long AND contains Excel internal markers
                            // Short strings with PK might be valid (like "PK-123" employee ID)
                            if (strlen($trimmedValue) > 100 && (
                                preg_match('/[\x00-\x08\x0B-\x0C\x0E-\x1F]/', $trimmedValue) || 
                                (stripos($trimmedValue, 'PK') === 0 && stripos($trimmedValue, 'workbook.xml') !== false) ||
                                stripos($trimmedValue, '<?xml') !== false ||
                                stripos($trimmedValue, 'workbook.xml') !== false ||
                                stripos($trimmedValue, '[Content_Types]') !== false
                            )) {
                                $cellValue = null;
                            }
                        }
                        
                        // Handle formula cells if it's a formula object
                        if ($cellValue instanceof \PhpOffice\PhpSpreadsheet\Cell\Cell && $cellValue->getDataType() === 'f') {
                            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex + 1);
                            try {
                                $cellValue = $worksheet->getCell($col . $row)->getCalculatedValue();
                            } catch (\Exception $e) {
                                $cellValue = null;
                            }
                        }
                        
                        // Trim whitespace
                        $cellValue = is_null($cellValue) ? null : (is_string($cellValue) ? trim($cellValue) : $cellValue);
                        
                        // Convert empty strings to null
                        if ($cellValue === '') {
                            $cellValue = null;
                        }
                        
                        // Check if row has any data
                        if (!empty($cellValue)) {
                            $isEmptyRow = false;
                            $hasAnyData = true;
                        }

                        // Map header to database field
                        $dbField = $this->mapHeaderToField($headerName, $fieldMapping);
                        
                        if ($dbField) {
                            // Handle date fields
                            if (in_array($dbField, ['admission_date', 'date_of_birth', 'joining_date', 'leaving_date'])) {
                                $rowData[$dbField] = $this->convertDate($cellValue);
                            } else {
                                // Store null for empty values instead of empty string
                                $rowData[$dbField] = $cellValue ?: null;
                            }
                        }
                    }
                }

                // For CSV, only skip if truly empty (no data in any mapped field)
                // For Excel, use the original logic
                if ($extension === 'csv') {
                    if (!$hasAnyData || empty($rowData)) {
                        if ($row <= 5) {
                            Log::debug('Skipping empty CSV row', ['row' => $row, 'has_any_data' => $hasAnyData, 'row_data_count' => count($rowData)]);
                        }
                        continue;
                    }
                } else {
                    if ($isEmptyRow) {
                        continue;
                    }
                }

                $data[] = $rowData;
                $rowNumber++;
            }


            if (empty($data) && $extension === 'csv') {
            }

            return $data;
        } catch (\Exception $e) {
            Log::error('Excel parsing failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Failed to parse Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Normalize header name (remove special chars, lowercase, etc.)
     */
    protected function normalizeHeader(string $header): string
    {
        // Convert to lowercase and replace spaces/underscores with underscores
        $normalized = strtolower(trim($header));
        $normalized = preg_replace('/[\s\-]+/', '_', $normalized);
        $normalized = preg_replace('/[^a-z0-9_]/', '', $normalized);
        return $normalized;
    }

    /**
     * Get field mapping for entity type
     */
    protected function getFieldMapping(string $entity): array
    {
        if ($entity === 'student') {
            return [
                // Basic info
                'first_name' => ['first_name', 'firstname', 'fname'],
                'last_name' => ['last_name', 'lastname', 'lname', 'surname'],
                'email' => ['email', 'email_address'],
                'phone' => ['phone', 'phone_number', 'mobile', 'mobile_number'],
                
                // Student details
                'admission_number' => ['admission_number', 'admission_no', 'adm_no', 'admission_number'],
                'admission_date' => ['admission_date', 'adm_date', 'admissiondate'],
                'roll_number' => ['roll_number', 'roll_no', 'roll', 'rollnumber'],
                'registration_number' => ['registration_number', 'reg_no', 'reg_number', 'registration'],
                'grade' => ['grade', 'class', 'standard'],
                'section' => ['section', 'sec'],
                'academic_year' => ['academic_year', 'academic_year', 'acadyear', 'year'],
                'stream' => ['stream'],
                
                // Personal info
                'date_of_birth' => ['date_of_birth', 'dob', 'birthdate', 'birth_date'],
                'gender' => ['gender', 'sex'],
                'blood_group' => ['blood_group', 'bloodgroup', 'blood', 'bg'],
                'religion' => ['religion'],
                'category' => ['category', 'caste'],
                'nationality' => ['nationality'],
                'mother_tongue' => ['mother_tongue', 'mothertongue', 'mother_tongue'],
                
                // Address
                'current_address' => ['current_address', 'address', 'currentaddress'],
                'permanent_address' => ['permanent_address', 'permanentaddress', 'permanent_addr'],
                'city' => ['city'],
                'state' => ['state'],
                'country' => ['country'],
                'pincode' => ['pincode', 'pin_code', 'pin', 'zip', 'postal_code'],
                
                // Father details
                'father_name' => ['father_name', 'fathername', 'father'],
                'father_phone' => ['father_phone', 'father_phone_number', 'father_mobile'],
                'father_email' => ['father_email', 'father_email_address'],
                'father_occupation' => ['father_occupation', 'father_occ', 'father_occup'],
                'father_annual_income' => ['father_annual_income', 'father_income', 'father_salary'],
                
                // Mother details
                'mother_name' => ['mother_name', 'mothername', 'mother'],
                'mother_phone' => ['mother_phone', 'mother_phone_number', 'mother_mobile'],
                'mother_email' => ['mother_email', 'mother_email_address'],
                'mother_occupation' => ['mother_occupation', 'mother_occ', 'mother_occup'],
                'mother_annual_income' => ['mother_annual_income', 'mother_income', 'mother_salary'],
                
                // Guardian details
                'guardian_name' => ['guardian_name', 'guardianname', 'guardian'],
                'guardian_relation' => ['guardian_relation', 'guardian_relationship'],
                'guardian_phone' => ['guardian_phone', 'guardian_phone_number', 'guardian_mobile'],
                
                // Emergency contact
                'emergency_contact_name' => ['emergency_contact_name', 'emergency_contact', 'emergency_name'],
                'emergency_contact_phone' => ['emergency_contact_phone', 'emergency_phone', 'emergency_mobile'],
                'emergency_contact_relation' => ['emergency_contact_relation', 'emergency_relation'],
                
                // Previous school
                'previous_school' => ['previous_school', 'prev_school', 'old_school'],
                'previous_grade' => ['previous_grade', 'prev_grade', 'old_grade'],
                'previous_percentage' => ['previous_percentage', 'prev_percentage', 'old_percentage'],
                'transfer_certificate_number' => ['transfer_certificate_number', 'tc_number', 'tc_no'],
                
                // Medical info
                'medical_history' => ['medical_history', 'medicalhistory', 'medical_info'],
                'allergies' => ['allergies', 'allergy'],
                'medications' => ['medications', 'medication'],
                'height_cm' => ['height_cm', 'height', 'height_in_cm', 'heightcm'],
                'weight_kg' => ['weight_kg', 'weight', 'weight_in_kg', 'weightkg'],
                
                // Other
                'password' => ['password', 'pass'],
                'remarks' => ['remarks', 'remark', 'notes', 'note'],
            ];
        } else {
            // Teacher mapping - All fields
            return [
                // Basic info
                'first_name' => ['first_name', 'firstname', 'fname'],
                'last_name' => ['last_name', 'lastname', 'lname', 'surname'],
                'email' => ['email', 'email_address'],
                'phone' => ['phone', 'phone_number', 'mobile', 'mobile_number'],
                
                // Employment details
                'employee_id' => ['employee_id', 'emp_id', 'employee_number', 'emp_no'],
                'joining_date' => ['joining_date', 'join_date', 'joined_date'],
                'leaving_date' => ['leaving_date', 'leave_date', 'left_date'],
                'designation' => ['designation', 'position', 'job_title'],
                'employee_type' => ['employee_type', 'emp_type', 'type', 'employment_type'],
                
                // Professional details
                'qualification' => ['qualification', 'qualifications', 'education'],
                'experience_years' => ['experience_years', 'experience', 'exp_years', 'years_of_experience'],
                'specialization' => ['specialization', 'speciality', 'subject_specialization'],
                'registration_number' => ['registration_number', 'reg_no', 'reg_number', 'registration'],
                'subjects' => ['subjects', 'subject', 'teaching_subjects'],
                'classes_assigned' => ['classes_assigned', 'classes', 'assigned_classes'],
                
                // Class teacher info
                'is_class_teacher' => ['is_class_teacher', 'class_teacher', 'is_ct'],
                'class_teacher_of_grade' => ['class_teacher_of_grade', 'ct_grade', 'grade_assigned'],
                'class_teacher_of_section' => ['class_teacher_of_section', 'ct_section', 'section_assigned'],
                
                // Personal info
                'date_of_birth' => ['date_of_birth', 'dob', 'birthdate', 'birth_date'],
                'gender' => ['gender', 'sex'],
                'blood_group' => ['blood_group', 'bloodgroup', 'blood', 'bg'],
                'religion' => ['religion'],
                'nationality' => ['nationality'],
                
                // Address
                'current_address' => ['current_address', 'address', 'currentaddress'],
                'permanent_address' => ['permanent_address', 'permanentaddress', 'permanent_addr'],
                'city' => ['city'],
                'state' => ['state'],
                'pincode' => ['pincode', 'pin_code', 'pin', 'zip', 'postal_code'],
                
                // Emergency contact
                'emergency_contact_name' => ['emergency_contact_name', 'emergency_contact', 'emergency_name'],
                'emergency_contact_phone' => ['emergency_contact_phone', 'emergency_phone', 'emergency_mobile'],
                'emergency_contact_relation' => ['emergency_contact_relation', 'emergency_relation'],
                
                // Salary details
                'salary_grade' => ['salary_grade', 'grade', 'pay_grade'],
                'basic_salary' => ['basic_salary', 'salary', 'basic_pay', 'gross_salary'],
                
                // Bank details
                'bank_name' => ['bank_name', 'bank'],
                'bank_account_number' => ['bank_account_number', 'account_number', 'bank_account', 'acc_no'],
                'bank_ifsc_code' => ['bank_ifsc_code', 'ifsc_code', 'ifsc', 'bank_ifsc'],
                
                // Document details
                'pan_number' => ['pan_number', 'pan', 'pan_no', 'pan_card'],
                'aadhar_number' => ['aadhar_number', 'aadhar', 'aadhaar_number', 'aadhaar', 'aadhar_no'],
                
                // Other
                'password' => ['password', 'pass'],
                'remarks' => ['remarks', 'remark', 'notes', 'note'],
            ];
        }
    }

    /**
     * Map header name to database field
     */
    protected function mapHeaderToField(string $headerName, array $fieldMapping): ?string
    {
        // First try exact match
        foreach ($fieldMapping as $dbField => $possibleHeaders) {
            if (in_array($headerName, $possibleHeaders)) {
                return $dbField;
            }
        }
        
        // If exact match not found, try partial match
        foreach ($fieldMapping as $dbField => $possibleHeaders) {
            foreach ($possibleHeaders as $possibleHeader) {
                if (strpos($headerName, $possibleHeader) !== false || strpos($possibleHeader, $headerName) !== false) {
                    return $dbField;
                }
            }
        }
        
        // Return null if no match found (unknown column)
        return null;
    }

    /**
     * Convert various date formats to Y-m-d format
     */
    protected function convertDate($dateValue): ?string
    {
        if (empty($dateValue)) {
            return null;
        }

        // 🔥 Check if it's a numeric value (Excel date serial number) FIRST
        // Excel dates are often stored as numbers or numeric strings
        // Check both numeric and string representations
        $numericValue = is_numeric($dateValue) ? (float)$dateValue : null;
        if ($numericValue !== null && $numericValue > 0 && $numericValue < 1000000) {
            // Likely an Excel date serial number (dates are typically < 1000000)
            try {
                // Use PhpSpreadsheet's built-in Excel date conversion
                if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                    $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($numericValue);
                    if ($timestamp !== false) {
                        return date('Y-m-d', $timestamp);
                    }
                }
                
                // Fallback: Manual Excel date calculation
                // Excel date serial: days since 1900-01-01 (Excel incorrectly treats 1900 as leap year)
                // Use 1899-12-30 as base to match Excel's behavior
                $excelEpoch = new \DateTime('1899-12-30');
                $days = (int)$numericValue;
                $date = clone $excelEpoch;
                $date->modify("+{$days} days");
                return $date->format('Y-m-d');
            } catch (\Exception $e) {
                // Fall through to try as date string
            }
        }

        // If it's a string, try to parse as date
        if (is_string($dateValue)) {
            $trimmed = trim($dateValue);
            
            // Skip if it looks like a serial number (pure digits)
            if (preg_match('/^\d+$/', $trimmed) && strlen($trimmed) > 3) {
                // Might be a serial number as string, try Excel conversion
                $numericValue = (float)$trimmed;
                if ($numericValue > 0 && $numericValue < 1000000) {
                    try {
                        if (class_exists('\PhpOffice\PhpSpreadsheet\Shared\Date')) {
                            $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($numericValue);
                            if ($timestamp !== false) {
                                return date('Y-m-d', $timestamp);
                            }
                        }
                    } catch (\Exception $e) {
                        // Continue to try as date string
                    }
                }
            }
            
            // Try to parse common date formats
            $formats = ['Y-m-d', 'd/m/Y', 'm/d/Y', 'd-m-Y', 'Y/m/d', 'Ymd', 'Y-m-d H:i:s'];
            foreach ($formats as $format) {
                $parsed = \DateTime::createFromFormat($format, $trimmed);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            }
            
            // Try Carbon for flexible parsing (last resort)
            try {
                return \Carbon\Carbon::parse($trimmed)->format('Y-m-d');
            } catch (\Exception $e) {
                return null;
            }
        }

        return null;
    }

    /**
     * Detect actual file type by checking file signature/MIME type
     */
    protected function detectFileType(string $filePath, string $extension): string
    {
        // Check file signature (magic bytes)
        if (!file_exists($filePath) || !is_readable($filePath)) {
            return $extension; // Fallback to extension
        }
        
        $handle = fopen($filePath, 'rb');
        if (!$handle) {
            return $extension;
        }
        
        $firstBytes = fread($handle, 8);
        fclose($handle);
        
        if ($firstBytes === false) {
            return $extension;
        }
        
        // Check for Excel file signatures
        // XLSX files start with PK (ZIP signature) - Excel files are ZIP archives
        if (substr($firstBytes, 0, 2) === 'PK') {
            // Check if it's actually an Excel file by looking for Excel-specific files
            // XLSX: PK\x03\x04 (ZIP) + contains xl/ folder
            // XLS: D0 CF 11 E0 A1 B1 1A E1 (OLE2 format)
            if (strpos($firstBytes, "\x50\x4B\x03\x04") === 0) {
                // Check if file contains Excel structure (quick check)
                $content = file_get_contents($filePath, false, null, 0, 1024);
                if (strpos($content, 'xl/') !== false || strpos($content, '[Content_Types].xml') !== false) {
                    return 'xlsx';
                }
            }
            // XLS (old format) signature
            if (substr($firstBytes, 0, 8) === "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1") {
                return 'xls';
            }
        }
        
        // If extension says CSV but file looks like Excel, return xlsx
        if ($extension === 'csv' && substr($firstBytes, 0, 2) === 'PK') {
            return 'xlsx';
        }
        
        // Default to extension
        return $extension;
    }

    /**
     * Generate student template with all fields
     */
    protected function generateStudentTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Student Import Template');

            // Define all student import fields with display names
            $headers = [
                'First Name', 'Last Name', 'Email', 'Phone', 
                'Admission Number', 'Admission Date', 'Roll Number', 'Registration Number',
                'Date of Birth', 'Gender', 'Blood Group', 'Religion', 'Category', 
                'Nationality', 'Mother Tongue',
                'Current Address', 'Permanent Address', 'City', 'State', 'Country', 'Pincode',
                'Father Name', 'Father Phone', 'Father Email', 'Father Occupation', 'Father Annual Income',
                'Mother Name', 'Mother Phone', 'Mother Email', 'Mother Occupation', 'Mother Annual Income',
                'Guardian Name', 'Guardian Relation', 'Guardian Phone',
                'Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relation',
                'Previous School', 'Previous Grade', 'Previous Percentage', 'Transfer Certificate Number',
                'Medical History', 'Allergies', 'Medications', 'Height (cm)', 'Weight (kg)',
                'Password', 'Remarks'
            ];

            // Write headers to first row
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $column++;
            }

            // Style header row
            $headerRange = 'A1:' . $column . '1';
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Add sample data row
            $sampleData = [
                'John', 'Doe', 'john.doe@example.com', '9876543210',
                'STU-2024-001', '2024-04-15', '101', 'REG-2024-001',
                '2014-05-20', 'Male', 'A+', 'Hindu', 'General', 'Indian', 'English',
                '123 Main Street', '123 Main Street', 'Mumbai', 'Maharashtra', 'India', '400001',
                'Rajesh Doe', '9876543210', 'rajesh@example.com', 'Engineer', '500000',
                'Priya Doe', '9876543211', 'priya@example.com', 'Teacher', '300000',
                '', '', '',
                'Rajesh Doe', '9876543210', 'Father',
                'ABC School', '4', '85.5', 'TC-001',
                'No major issues', 'None', 'None', '150', '45',
                'Welcome@123', 'Good student'
            ];

            $column = 'A';
            foreach ($sampleData as $value) {
                $sheet->setCellValue($column . '2', $value);
                $column++;
            }

            // Set column widths
            foreach (range('A', $column) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Freeze header row
            $sheet->freezePane('A2');

            // Create writer and save to temporary file
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'student_template_');
            $writer->save($tempFile);

            // Return file download
            $fileName = 'student_import_template_' . date('Y-m-d') . '.xlsx';
            
            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Template generation failed', ['error' => $e->getMessage()]);
            
            // Fallback to CSV if Excel generation fails
            $headers = [
                'first_name', 'last_name', 'email', 'phone', 'admission_number', 'admission_date',
                'roll_number', 'registration_number', 'date_of_birth', 'gender', 'blood_group',
                'religion', 'category', 'nationality', 'mother_tongue',
                'current_address', 'permanent_address', 'city', 'state', 'country', 'pincode',
                'father_name', 'father_phone', 'father_email', 'father_occupation', 'father_annual_income',
                'mother_name', 'mother_phone', 'mother_email', 'mother_occupation', 'mother_annual_income',
                'guardian_name', 'guardian_relation', 'guardian_phone',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
                'previous_school', 'previous_grade', 'previous_percentage', 'transfer_certificate_number',
                'medical_history', 'allergies', 'medications', 'height_cm', 'weight_kg',
                'password', 'remarks'
            ];

            $csvContent = implode(',', $headers);
            $fileName = 'student_import_template_' . date('Y-m-d') . '.csv';
            
            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }
    }

    /**
     * Generate teacher template with all fields
     */
    protected function generateTeacherTemplate()
    {
        try {
            $spreadsheet = new Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Teacher Import Template');

            // Define all teacher import fields
            $headers = [
                'First Name', 'Last Name', 'Email', 'Phone',
                'Employee ID', 'Joining Date', 'Leaving Date', 'Designation', 'Employee Type',
                'Qualification', 'Experience Years', 'Specialization', 'Registration Number',
                'Subjects', 'Classes Assigned',
                'Is Class Teacher', 'Class Teacher of Grade', 'Class Teacher of Section',
                'Date of Birth', 'Gender', 'Blood Group', 'Religion', 'Nationality',
                'Current Address', 'Permanent Address', 'City', 'State', 'Pincode',
                'Emergency Contact Name', 'Emergency Contact Phone', 'Emergency Contact Relation',
                'Salary Grade', 'Basic Salary',
                'Bank Name', 'Bank Account Number', 'Bank IFSC Code',
                'PAN Number', 'Aadhar Number',
                'Password', 'Remarks'
            ];

            // Write headers to first row
            $column = 'A';
            foreach ($headers as $header) {
                $sheet->setCellValue($column . '1', $header);
                $column++;
            }

            // Style header row
            $headerRange = 'A1:' . $column . '1';
            $sheet->getStyle($headerRange)->applyFromArray([
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => '4472C4'],
                ],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Add sample data row
            $sampleData = [
                'Jane', 'Smith', 'jane.smith@example.com', '9876543212',
                'TCH-2024-001', '2024-01-01', '', 'Senior Teacher', 'Permanent',
                'M.Sc Mathematics', '5', 'Mathematics', 'REG-2024-001',
                'Mathematics, Physics', 'Class 9, Class 10',
                'Yes', '9', 'A',
                '1985-03-15', 'Female', 'A+', 'Hindu', 'Indian',
                '456 Park Avenue', '456 Park Avenue', 'Delhi', 'Delhi', '110001',
                'John Smith', '9876543213', 'Husband',
                'Grade A', '50000',
                'State Bank of India', '1234567890123456', 'SBIN0001234',
                'ABCDE1234F', '1234 5678 9012',
                'Welcome@123', 'Excellent teacher'
            ];

            $column = 'A';
            foreach ($sampleData as $value) {
                $sheet->setCellValue($column . '2', $value);
                $column++;
            }

            // Set column widths
            foreach (range('A', $column) as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }

            // Freeze header row
            $sheet->freezePane('A2');

            // Create writer and save to temporary file
            $writer = new Xlsx($spreadsheet);
            $tempFile = tempnam(sys_get_temp_dir(), 'teacher_template_');
            $writer->save($tempFile);

            // Return file download
            $fileName = 'teacher_import_template_' . date('Y-m-d') . '.xlsx';
            
            return response()->download($tempFile, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ])->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            Log::error('Teacher template generation failed', ['error' => $e->getMessage()]);
            
            // Fallback to CSV
            $headers = [
                'first_name', 'last_name', 'email', 'phone',
                'employee_id', 'joining_date', 'leaving_date', 'designation', 'employee_type',
                'qualification', 'experience_years', 'specialization', 'registration_number',
                'subjects', 'classes_assigned',
                'is_class_teacher', 'class_teacher_of_grade', 'class_teacher_of_section',
                'date_of_birth', 'gender', 'blood_group', 'religion', 'nationality',
                'current_address', 'permanent_address', 'city', 'state', 'pincode',
                'emergency_contact_name', 'emergency_contact_phone', 'emergency_contact_relation',
                'salary_grade', 'basic_salary',
                'bank_name', 'bank_account_number', 'bank_ifsc_code',
                'pan_number', 'aadhar_number',
                'password', 'remarks'
            ];

            $csvContent = implode(',', $headers);
            $fileName = 'teacher_import_template_' . date('Y-m-d') . '.csv';
            
            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $fileName . '"');
        }
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

