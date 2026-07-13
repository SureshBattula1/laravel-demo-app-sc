<?php

namespace App\Exports;

use App\Services\ExportService;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Illuminate\Support\Collection;

/**
 * Excel export for the global people search results.
 * Columns are driven by the 'global_search' module in config/exports.php.
 */
class GlobalSearchExport implements FromCollection, WithHeadings, WithTitle, ShouldAutoSize
{
    protected Collection $data;
    protected ExportService $exportService;

    public function __construct(Collection $data, ?array $columns = null)
    {
        $this->data = $data;
        $this->exportService = new ExportService('global_search');

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

    public function title(): string
    {
        return 'Search Results';
    }
}
