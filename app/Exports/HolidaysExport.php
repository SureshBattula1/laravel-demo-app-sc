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
 * Holidays Export
 * 
 * Reusable export class for holidays list.
 * Uses the ExportService for configuration-driven column management.
 */
class HolidaysExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $data;
    protected ExportService $exportService;
    
    /**
     * @param Collection $data - Holiday records to export
     * @param array|null $columns - Optional custom columns to export
     */
    public function __construct(Collection $data, ?array $columns = null)
    {
        $this->data = $data;
        $this->exportService = new ExportService('holidays');
        
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
                    'startColor' => ['argb' => 'FFFF6F00'], // Amber for holidays
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


