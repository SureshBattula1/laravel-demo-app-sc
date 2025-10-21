<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * PDF Export Service
 * 
 * Generates PDF exports using DomPDF with customizable styling.
 * 
 * Note: Requires barryvdh/laravel-dompdf package to be installed.
 * Install with: composer require barryvdh/laravel-dompdf
 */
class PdfExportService
{
    protected ExportService $exportService;
    protected string $orientation = 'landscape';
    protected string $paperSize = 'a4';
    
    public function __construct(string $module)
    {
        $this->exportService = new ExportService($module);
    }
    
    public function setColumns(array $columns): self
    {
        $this->exportService->setColumns($columns);
        return $this;
    }
    
    public function setOrientation(string $orientation): self
    {
        $this->orientation = $orientation;
        return $this;
    }
    
    public function setPaperSize(string $size): self
    {
        $this->paperSize = $size;
        return $this;
    }
    
    /**
     * Generate PDF from data
     */
    public function generate(Collection $data, string $title = '')
    {
        $headers = $this->exportService->getHeaders();
        $rows = $this->exportService->transformCollection($data);
        
        $html = $this->buildHtml($headers, $rows, $title);
        
        // Use Laravel's PDF facade via App container
        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html);
        $pdf->setPaper($this->paperSize, $this->orientation);
            
        return $pdf;
    }
    
    /**
     * Build HTML for PDF
     */
    protected function buildHtml(array $headers, array $rows, string $title): string
    {
        $titleHtml = $title ? "<h2 style='text-align: center; margin-bottom: 20px;'>{$title}</h2>" : '';
        $date = now()->format('d-m-Y H:i:s');
        
        $html = <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'DejaVu Sans', Arial, sans-serif;
                    font-size: 9px;
                    padding: 15px;
                }
                h2 {
                    color: #333;
                    margin-bottom: 10px;
                }
                .meta {
                    text-align: right;
                    color: #666;
                    margin-bottom: 15px;
                    font-size: 8px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                thead {
                    background-color: #4CAF50;
                    color: white;
                }
                th, td {
                    padding: 6px 8px;
                    text-align: left;
                    border: 1px solid #ddd;
                }
                th {
                    font-weight: bold;
                    font-size: 9px;
                }
                tbody tr:nth-child(even) {
                    background-color: #f9f9f9;
                }
                tbody tr:hover {
                    background-color: #f5f5f5;
                }
                .footer {
                    margin-top: 20px;
                    text-align: center;
                    color: #999;
                    font-size: 7px;
                }
            </style>
        </head>
        <body>
            {$titleHtml}
            <div class="meta">Generated on: {$date}</div>
            <table>
                <thead>
                    <tr>
        HTML;
        
        // Add headers
        foreach ($headers as $header) {
            $html .= "<th>" . htmlspecialchars($header) . "</th>";
        }
        
        $html .= "</tr></thead><tbody>";
        
        // Add rows
        foreach ($rows as $row) {
            $html .= "<tr>";
            foreach ($row as $cell) {
                $html .= "<td>" . htmlspecialchars($cell ?? '') . "</td>";
            }
            $html .= "</tr>";
        }
        
        $html .= <<<HTML
                </tbody>
            </table>
            <div class="footer">
                This is a system-generated document
            </div>
        </body>
        </html>
        HTML;
        
        return $html;
    }
}

