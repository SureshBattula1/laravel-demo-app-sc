<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BranchAnalytic extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'analytics_date',
        'metric_type',
        'metric_value',
        'breakdown'
    ];

    protected function casts(): array
    {
        return [
            'analytics_date' => 'date',
            'metric_value' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    // Scopes
    public function scopeForDate($query, $date)
    {
        return $query->where('analytics_date', $date);
    }

    public function scopeForDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('analytics_date', [$startDate, $endDate]);
    }

    public function scopeByMetricType($query, $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeForBranch($query, $branchId)
    {
        return $query->where('branch_id', $branchId);
    }

    // Static methods for common analytics
    public static function getEnrollmentTrend($branchId, $days = 30)
    {
        return self::forBranch($branchId)
            ->byMetricType('enrollment')
            ->forDateRange(now()->subDays($days), now())
            ->orderBy('analytics_date')
            ->get();
    }

    public static function getRevenueTrend($branchId, $days = 30)
    {
        return self::forBranch($branchId)
            ->byMetricType('revenue')
            ->forDateRange(now()->subDays($days), now())
            ->orderBy('analytics_date')
            ->get();
    }

    public static function getAttendanceTrend($branchId, $days = 30)
    {
        return self::forBranch($branchId)
            ->byMetricType('attendance')
            ->forDateRange(now()->subDays($days), now())
            ->orderBy('analytics_date')
            ->get();
    }
}

