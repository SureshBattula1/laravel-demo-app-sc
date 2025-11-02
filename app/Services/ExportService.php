<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Base Export Service
 * 
 * Provides reusable export functionality across all modules.
 * Handles column configuration, data transformation, and file generation.
 */
class ExportService
{
    protected string $module;
    protected array $config;
    protected array $selectedColumns = [];
    
    public function __construct(string $module)
    {
        $this->module = $module;
        $this->config = config("exports.{$module}", []);
        $this->initializeSelectedColumns();
    }
    
    /**
     * Initialize selected columns based on configuration
     */
    protected function initializeSelectedColumns(): void
    {
        $this->selectedColumns = collect($this->config['columns'] ?? [])
            ->filter(fn($column) => $column['enabled'] ?? false)
            ->keys()
            ->toArray();
    }
    
    /**
     * Set specific columns to export
     */
    public function setColumns(array $columns): self
    {
        $this->selectedColumns = $columns;
        return $this;
    }
    
    /**
     * Get enabled columns configuration
     */
    public function getEnabledColumns(): array
    {
        $columns = $this->config['columns'] ?? [];
        
        return collect($this->selectedColumns)
            ->mapWithKeys(fn($key) => [$key => $columns[$key] ?? ['label' => $key, 'enabled' => true]])
            ->toArray();
    }
    
    /**
     * Get column headers for export
     */
    public function getHeaders(): array
    {
        return collect($this->getEnabledColumns())
            ->map(fn($column) => $column['label'] ?? '')
            ->values()
            ->toArray();
    }
    
    /**
     * Transform data row for export
     */
    public function transformRow($row): array
    {
        $columns = $this->getEnabledColumns();
        $transformed = [];
        
        foreach ($this->selectedColumns as $key) {
            $value = $this->getNestedValue($row, $key);
            $column = $columns[$key] ?? [];
            
            $transformed[] = $this->formatValue($value, $column['format'] ?? null);
        }
        
        return $transformed;
    }
    
    /**
     * Get nested value from object/array using dot notation
     */
    protected function getNestedValue($data, string $key)
    {
        // Handle special cases for branch.name, etc.
        if (str_contains($key, '.')) {
            $keys = explode('.', $key);
            $value = $data;
            
            foreach ($keys as $k) {
                if (is_object($value)) {
                    $value = $value->{$k} ?? null;
                } elseif (is_array($value)) {
                    $value = $value[$k] ?? null;
                } else {
                    return null;
                }
            }
            
            return $value;
        }
        
        if (is_object($data)) {
            return $data->{$key} ?? null;
        }
        
        if (is_array($data)) {
            return $data[$key] ?? null;
        }
        
        return null;
    }
    
    /**
     * Format value based on type
     */
    protected function formatValue($value, ?string $format)
    {
        if ($value === null) {
            return '';
        }
        
        return match($format) {
            'date' => $this->formatDate($value),
            'datetime' => $this->formatDateTime($value),
            'boolean' => $value ? 'Yes' : 'No',
            default => (string) $value
        };
    }
    
    /**
     * Format date value
     */
    protected function formatDate($value): string
    {
        try {
            $format = config('exports.global.date_format', 'd-m-Y');
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
    
    /**
     * Format datetime value
     */
    protected function formatDateTime($value): string
    {
        try {
            $format = config('exports.global.datetime_format', 'd-m-Y H:i:s');
            return \Carbon\Carbon::parse($value)->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
    
    /**
     * Generate filename with timestamp
     */
    public function generateFilename(string $format = 'xlsx'): string
    {
        $prefix = $this->config['filename_prefix'] ?? $this->module;
        $timestamp = config('exports.global.include_timestamp', true) 
            ? '_' . now()->format('Y-m-d_His')
            : '';
        
        return "{$prefix}{$timestamp}.{$format}";
    }
    
    /**
     * Get sheet name for Excel export
     */
    public function getSheetName(): string
    {
        return $this->config['sheet_name'] ?? ucfirst($this->module);
    }
    
    /**
     * Transform collection for export
     */
    public function transformCollection(Collection $data): array
    {
        return $data->map(fn($row) => $this->transformRow($row))->toArray();
    }
}


