<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class GlobalUploadService
{
    protected string $storageDisk = 'public';

    /**
     * Upload single file to CLIENT-provided path
     */
    public function uploadFile(Request $request, string $uploadPath): array
    {
        $file = $request->file('file');
        
        if (!$file) {
            throw new \Exception('File is required');
        }

        // Security: Clean the path to prevent directory traversal
        $cleanPath = $this->sanitizePath($uploadPath);
        
        // Ensure directory exists
        $this->ensureDirectoryExists($cleanPath);
        
        // Store file
        $filePath = Storage::disk($this->storageDisk)->putFileAs(
            dirname($cleanPath),
            $file,
            basename($cleanPath)
        );

        return [
            'file_path' => $filePath,
            'file_url' => Storage::disk($this->storageDisk)->url($filePath),
            'file_name' => $file->getClientOriginalName(),
            'file_size' => $file->getSize(),
            'file_type' => $file->getMimeType(),
            'extension' => strtolower($file->getClientOriginalExtension()),
        ];
    }

    /**
     * Upload multiple files to same path
     */
    public function uploadMultipleFiles(Request $request, string $uploadPath): array
    {
        $files = $request->file('files');
        
        if (!$files || !is_array($files)) {
            throw new \Exception('Files array is required');
        }

        $results = [];
        $cleanPath = $this->sanitizePath($uploadPath);
        $this->ensureDirectoryExists($cleanPath);
        
        foreach ($files as $file) {
            try {
                // Generate unique filename for each file
                $extension = strtolower($file->getClientOriginalExtension());
                $uniqueFilename = time() . '_' . uniqid() . '.' . $extension;
                $fullCleanPath = dirname($cleanPath) . '/' . $uniqueFilename;
                
                $filePath = Storage::disk($this->storageDisk)->putFileAs(
                    dirname($fullCleanPath),
                    $file,
                    basename($fullCleanPath)
                );
                
                $results[] = [
                    'file_path' => $filePath,
                    'file_url' => Storage::disk($this->storageDisk)->url($filePath),
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'file_type' => $file->getMimeType(),
                    'extension' => $extension,
                ];
            } catch (\Exception $e) {
                Log::error('Multiple file upload error', [
                    'error' => $e->getMessage(),
                    'file' => $file->getClientOriginalName()
                ]);
                $results[] = [
                    'error' => $e->getMessage(),
                    'file_name' => $file->getClientOriginalName()
                ];
            }
        }
        
        return $results;
    }

    /**
     * Sanitize path to prevent directory traversal attacks
     */
    private function sanitizePath(string $path): string
    {
        // Remove any ".." to prevent directory traversal
        $path = str_replace('..', '', $path);
        
        // Remove leading slashes
        $path = ltrim($path, '/');
        
        // Ensure it doesn't go outside uploads directory
        if (!str_starts_with($path, 'uploads/')) {
            $path = 'uploads/' . $path;
        }
        
        // Remove any double slashes
        $path = preg_replace('#/+#', '/', $path);
        
        return $path;
    }

    /**
     * Ensure directory exists
     */
    private function ensureDirectoryExists(string $filePath): void
    {
        $directory = dirname($filePath);
        $fullDirectory = Storage::disk($this->storageDisk)->path($directory);
        
        if (!is_dir($fullDirectory)) {
            mkdir($fullDirectory, 0755, true);
        }
    }

    /**
     * Delete file
     */
    public function deleteFile(string $filePath): bool
    {
        try {
            // Sanitize path
            $cleanPath = $this->sanitizePath($filePath);
            
            if (Storage::disk($this->storageDisk)->exists($cleanPath)) {
                return Storage::disk($this->storageDisk)->delete($cleanPath);
            }
            return false;
        } catch (\Exception $e) {
            Log::error('File deletion error', ['error' => $e->getMessage(), 'path' => $filePath]);
            return false;
        }
    }

    /**
     * Get file information
     */
    public function getFileInfo(string $filePath): array
    {
        $cleanPath = $this->sanitizePath($filePath);
        
        if (!Storage::disk($this->storageDisk)->exists($cleanPath)) {
            throw new \Exception('File not found');
        }

        return [
            'file_path' => $cleanPath,
            'file_url' => Storage::disk($this->storageDisk)->url($cleanPath),
            'file_size' => Storage::disk($this->storageDisk)->size($cleanPath),
            'mime_type' => Storage::disk($this->storageDisk)->mimeType($cleanPath),
            'last_modified' => Storage::disk($this->storageDisk)->lastModified($cleanPath),
        ];
    }

    /**
     * Check if file exists
     */
    public function fileExists(string $filePath): bool
    {
        try {
            $cleanPath = $this->sanitizePath($filePath);
            return Storage::disk($this->storageDisk)->exists($cleanPath);
        } catch (\Exception $e) {
            return false;
        }
    }
}

