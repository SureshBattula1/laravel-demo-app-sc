<?php

namespace App\Exports;

use App\Services\ExportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class UsersExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths, WithTitle
{
    protected Collection $data;

    protected ExportService $exportService;

    public function __construct(Collection $data, ?array $columns = null)
    {
        $this->data = $data;
        $this->exportService = new ExportService('users');

        if ($columns) {
            $this->exportService->setColumns($columns);
        }
    }

    public function collection(): Collection
    {
        return collect($this->exportService->transformCollection($this->data));
    }

    public function headings(): array
    {
        return $this->exportService->getHeaders();
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return $this->exportService->getColumnWidths();
    }

    public function title(): string
    {
        return 'Users';
    }
}
