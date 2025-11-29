<?php

namespace App\Http\Controllers;

use App\Services\GlobalUploadService;
use App\Models\UniversalAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GlobalUploadController extends Controller
{
    public function __construct(
        protected GlobalUploadService $uploadService
    ) {}

    /**
     * Upload single file
     */
    public function upload(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,txt,csv|max:10240',
            'upload_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->uploadService->uploadFile($request, $request->upload_path);
            
            Log::info('File uploaded successfully', [
                'file_path' => $result['file_path'],
                'file_name' => $result['file_name']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'File uploaded successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('File upload failed', [
                'error' => $e->getMessage(),
                'upload_path' => $request->upload_path
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload multiple files
     */
    public function uploadMultiple(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'required|file|mimes:jpeg,png,jpg,gif,pdf,doc,docx,xls,xlsx,txt,csv|max:10240',
            'upload_path' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $results = $this->uploadService->uploadMultipleFiles($request, $request->upload_path);
            
            Log::info('Multiple files uploaded', [
                'count' => count($results),
                'upload_path' => $request->upload_path
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            Log::error('Multiple file upload failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete file
     */
    public function delete(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deleted = $this->uploadService->deleteFile($request->file_path);

            return response()->json([
                'success' => $deleted,
                'message' => $deleted ? 'File deleted successfully' : 'File not found'
            ]);
        } catch (\Exception $e) {
            Log::error('File deletion failed', [
                'error' => $e->getMessage(),
                'file_path' => $request->file_path
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete file'
            ], 500);
        }
    }

    /**
     * Get file information
     */
    public function getFileInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $info = $this->uploadService->getFileInfo($request->file_path);

            return response()->json([
                'success' => true,
                'data' => $info
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Check if file exists
     */
    public function checkFileExists(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'file_path' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $exists = $this->uploadService->fileExists($request->file_path);

        return response()->json([
            'success' => true,
            'exists' => $exists
        ]);
    }

    /**
     * Save attachment to database
     */
    public function saveAttachment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'module' => 'required|string',
            'module_id' => 'required|integer',
            'attachment_type' => 'required|string',
            'file_name' => 'required|string',
            'file_path' => 'required|string',
            'original_name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $attachment = UniversalAttachment::create([
                'module' => $request->module,
                'module_id' => $request->module_id,
                'attachment_type' => $request->attachment_type,
                'file_name' => $request->file_name,
                'file_path' => $request->file_path,
                'file_type' => $request->file_type,
                'file_size' => $request->file_size,
                'original_name' => $request->original_name,
                'description' => $request->description,
                'is_active' => true,
                'uploaded_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Attachment saved successfully',
                'data' => $attachment
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to save attachment', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to save attachment'
            ], 500);
        }
    }

    /**
     * Get attachments for a module
     */
    public function getAttachments($module, $moduleId)
    {
        try {
            $attachments = UniversalAttachment::where('module', $module)
                ->where('module_id', $moduleId)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $attachments
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get attachments', [
                'module' => $module,
                'module_id' => $moduleId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get attachments'
            ], 500);
        }
    }

    /**
     * Delete an attachment
     */
    public function deleteAttachment($module, $moduleId, $attachmentId)
    {
        try {
            $attachment = UniversalAttachment::where('module', $module)
                ->where('module_id', $moduleId)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }

            // Delete physical file if exists
            if ($attachment->file_path && Storage::disk('public')->exists($attachment->file_path)) {
                Storage::disk('public')->delete($attachment->file_path);
            }

            // Soft delete the attachment
            $attachment->delete();

            return response()->json([
                'success' => true,
                'message' => 'Attachment deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete attachment', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete attachment'
            ], 500);
        }
    }

    /**
     * Download an attachment
     */
    public function downloadAttachment($module, $moduleId, $attachmentId)
    {
        try {
            $attachment = UniversalAttachment::where('module', $module)
                ->where('module_id', $moduleId)
                ->where('id', $attachmentId)
                ->first();

            if (!$attachment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Attachment not found'
                ], 404);
            }

            $filePath = storage_path('app/public/' . $attachment->file_path);

            if (!file_exists($filePath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'File not found'
                ], 404);
            }

            return response()->download($filePath, $attachment->original_name);
        } catch (\Exception $e) {
            Log::error('Failed to download attachment', [
                'attachment_id' => $attachmentId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download file'
            ], 500);
        }
    }
}

