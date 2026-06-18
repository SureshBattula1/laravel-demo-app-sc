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
 * Transactions Export
 * 
 * Reusable export class for both income and expense transactions.
 * Uses the ExportService for configuration-driven column management.
 */
class TransactionsExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $data;
    protected ExportService $exportService;
    protected string $type; // 'income' or 'expense'
    
    /**
     * @param Collection $data - Transaction records to export
     * @param string $type - Type of transaction ('income' or 'expense')
     * @param array|null $columns - Optional custom columns to export
     */
    public function __construct(Collection $data, string $type = 'income', ?array $columns = null)
    {
        $this->data = $data;
        $this->type = strtolower($type);
        
        // Use appropriate export configuration based on type
        $module = $this->type === 'income' ? 'income_transactions' : 'expense_transactions';
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
                    // Green for income, Red for expense
                    'startColor' => ['argb' => $this->type === 'income' ? 'FF4CAF50' : 'FFF44336'],
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


