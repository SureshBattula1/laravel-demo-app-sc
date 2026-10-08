<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\DB;

/**
 * Natural (numeric-aware) ordering on the `grades.value` column.
 *
 * Numeric grade values sort numerically (e.g. 12, 11, 10, 9, ...) instead of
 * string-order (9, 8, ..., 12, 11, 10), while non-numeric values such as
 * "LKG" / "UKG" are placed after the numeric grades.
 */
trait OrdersGradesByValue
{
    /**
     * Apply orderBy on grades.value (numeric-aware) to the given query.
     *
     * @param  \Illuminate\Database\Query\Builder|mixed  $query
     * @param  string  $direction  asc|desc (default: desc)
     */
    protected function orderGradeValue($query, string $direction = 'desc')
    {
        $dir = strtolower($direction) === 'asc' ? 'ASC' : 'DESC';
        $column = 'grades.value';

        if (DB::getDriverName() === 'mysql') {
            return $query->orderByRaw(
                "IF($column REGEXP '^[0-9]+$', CAST($column AS UNSIGNED), -1) $dir"
            );
        }

        if (DB::getDriverName() === 'sqlite') {
            return $query->orderByRaw(
                "CASE WHEN CAST($column AS INTEGER) = 0 AND $column <> '0' THEN -1 ELSE CAST($column AS INTEGER) END $dir"
            );
        }

        return $query->orderBy($column, $direction);
    }
}
