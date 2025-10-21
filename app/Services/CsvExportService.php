<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV Export Service
 * 
 * Generates CSV exports with configurable delimiters and encoding.
 */
class CsvExportService
{
    protected ExportService $exportService;
    protected string $delimiter = ',';
    protected string $enclosure = '"';
    protected string $encoding = 'UTF-8';
    
    public function __construct(string $module)
    {
        $this->exportService = new ExportService($module);
        $this->delimiter = config('exports.global.csv_delimiter', ',');
    }
    
    public function setColumns(array $columns): self
    {
        $this->exportService->setColumns($columns);
        return $this;
    }
    
    public function setDelimiter(string $delimiter): self
    {
        $this->delimiter = $delimiter;
        return $this;
    }
    
    /**
     * Generate CSV streamed response
     */
    public function generate(Collection $data, string $filename): StreamedResponse
    {
        $headers = $this->exportService->getHeaders();
        $rows = $this->exportService->transformCollection($data);
        
        $callback = function() use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            if ($this->encoding === 'UTF-8') {
                fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
            }
            
            // Write headers
            fputcsv($file, $headers, $this->delimiter, $this->enclosure);
            
            // Write data rows
            foreach ($rows as $row) {
                fputcsv($file, $row, $this->delimiter, $this->enclosure);
            }
            
            fclose($file);
        };
        
        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv; charset=' . $this->encoding,
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }
}


