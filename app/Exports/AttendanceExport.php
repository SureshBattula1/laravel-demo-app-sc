<?php

namespace App\Exports;

use App\Services\ExportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Illuminate\Support\Collection;

/**
 * Attendance Export
 * 
 * Reusable export class for both student and teacher attendance.
 * Uses the ExportService for configuration-driven column management.
 */
class AttendanceExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $data;
    protected ExportService $exportService;
    protected string $type; // 'student_attendance' or 'teacher_attendance'
    
    /**
     * @param Collection $data - Attendance records to export
     * @param string $type - Type of attendance ('student' or 'teacher')
     * @param array|null $columns - Optional custom columns to export
     */
    public function __construct(Collection $data, string $type = 'student', ?array $columns = null)
    {
        $this->data = $data;
        $this->type = $type;
        
        // Use appropriate export configuration based on type
        $module = $type === 'student' ? 'student_attendance' : 'teacher_attendance';
        $this->exportService = new ExportService($module);
        
        if ($columns) {
            $this->exportService->setColumns($columns);
        }
    }
    
    /**
     * Return the data collection
     */
    public function collection(): Collection
    {
        return collect($this->exportService->transformCollection($this->data));
    }
    
    /**
     * Return column headings
     */
    public function headings(): array
    {
        return $this->exportService->getHeaders();
    }
    
    /**
     * Apply styles to the sheet
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 12,
                    'color' => ['argb' => 'FFFFFFFF'],
                ],
                'fill' => [
                    'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
                    'startColor' => ['argb' => $this->type === 'student' ? 'FF2196F3' : 'FFFF9800'],
                ],
                'alignment' => [
                    'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
                    'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
                ],
            ],
        ];
    }
    
    /**
     * Set column widths
     */
    public function columnWidths(): array
    {
        $columns = $this->exportService->getEnabledColumns();
        $widths = [];
        $columnIndex = 'A';
        
        foreach ($columns as $column) {
            $widths[$columnIndex] = $column['width'] ?? 15;
            $columnIndex++;
        }
        
        return $widths;
    }
    
    /**
     * Set sheet title
     */
    public function title(): string
    {
        return $this->exportService->getSheetName();
    }
}


