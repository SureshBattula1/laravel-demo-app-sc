<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Trait for standardized server-side pagination and sorting
 * 
 * This trait provides consistent pagination and sorting functionality
 * across all controllers in the application.
 */
trait PaginatesAndSorts
{
    /**
     * Apply pagination and sorting to a query
     *
     * @param Builder $query
     * @param Request $request
     * @param array $sortableColumns - Array of columns that can be sorted
     * @param string $defaultSortColumn - Default column to sort by
     * @param string $defaultSortDirection - Default sort direction (asc/desc)
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    protected function paginateAndSort(
        $query,
        Request $request,
        array $sortableColumns = [],
        string $defaultSortColumn = 'created_at',
        string $defaultSortDirection = 'desc'
    ) {
        // Apply sorting
        $sortBy = $request->get('sort_by', $defaultSortColumn);
        $sortDirection = $request->get('sort_direction', $defaultSortDirection);
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) 
            ? strtolower($sortDirection) 
            : $defaultSortDirection;
        
        // Validate sort column - only allow whitelisted columns
        if (!empty($sortableColumns) && !in_array($sortBy, $sortableColumns)) {
            $sortBy = $defaultSortColumn;
        }
        
        // Apply sorting to query
        $query->orderBy($sortBy, $sortDirection);
        
        // Apply pagination
        $perPage = (int) $request->get('per_page', 25);
        
        // Limit per_page to reasonable values (1-100)
        $perPage = max(1, min(100, $perPage));
        
        return $query->paginate($perPage);
    }
    
    /**
     * Apply sorting to a query builder (works with both Eloquent and DB query builder)
     *
     * @param \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param array $sortableColumns - Array of columns that can be sorted
     * @param string $defaultSortColumn - Default column to sort by
     * @param string $defaultSortDirection - Default sort direction (asc/desc)
     * @return \Illuminate\Database\Query\Builder|\Illuminate\Database\Eloquent\Builder
     */
    protected function applySorting(
        $query,
        Request $request,
        array $sortableColumns = [],
        string $defaultSortColumn = 'created_at',
        string $defaultSortDirection = 'desc'
    ) {
        // Get sort parameters from request
        $sortBy = $request->get('sort_by', $defaultSortColumn);
        $sortDirection = $request->get('sort_direction', $request->get('sort_order', $defaultSortDirection));
        
        // Validate sort direction
        $sortDirection = in_array(strtolower($sortDirection), ['asc', 'desc']) 
            ? strtolower($sortDirection) 
            : $defaultSortDirection;
        
        // Validate sort column - only allow whitelisted columns
        if (!empty($sortableColumns) && !in_array($sortBy, $sortableColumns)) {
            $sortBy = $defaultSortColumn;
        }
        
        // Apply sorting to query
        return $query->orderBy($sortBy, $sortDirection);
    }
    
    /**
     * Format paginated response with metadata
     *
     * @param \Illuminate\Contracts\Pagination\LengthAwarePaginator $paginator
     * @param string $message
     * @return array
     */
    protected function paginatedResponse($paginator, string $message = 'Data retrieved successfully'): array
    {
        return [
            'success' => true,
            'message' => $message,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages()
            ]
        ];
    }
}

