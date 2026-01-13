<?php

namespace App\Services;

use App\Models\FeeDue;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\ClassUpgrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FeeReportService
{
    /**
     * Generate dues report grouped by fee_type
     */
    public function duesReport($filters = []): array
    {
        $query = FeeDue::with(['student.user', 'feeStructure']);

        // Apply filters
        if (isset($filters['branch_id'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        if (isset($filters['grade'])) {
            $query->where('current_grade', $filters['grade']);
        }

        if (isset($filters['fee_type'])) {
            $query->where('fee_type', $filters['fee_type']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $dues = $query->get();

        // Group by fee_type
        $grouped = $dues->groupBy('fee_type');

        return [
            'data' => $dues,
            'grouped_by_fee_type' => $grouped,
            'summary' => [
                'total_count' => $dues->count(),
                'total_balance' => $dues->sum('balance_amount'),
                'fee_types' => $grouped->keys()->toArray()
            ]
        ];
    }

    /**
     * Generate promotion dues report
     */
    public function promotionDuesReport($filters = []): array
    {
        $query = FeeDue::where('status', 'CarriedForward')
            ->with(['student.user', 'feeStructure']);

        // Apply filters
        if (isset($filters['from_grade'])) {
            $query->where('original_grade', $filters['from_grade']);
        }

        if (isset($filters['to_grade'])) {
            $query->where('current_grade', $filters['to_grade']);
        }

        if (isset($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        $carriedFees = $query->get();

        // Group by fee_type
        $grouped = $carriedFees->groupBy('fee_type')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_balance' => $group->sum('balance_amount'),
                'total_original' => $group->sum('original_amount'),
                'fees' => $group
            ];
        });

        return [
            'data' => $carriedFees,
            'grouped_by_fee_type' => $grouped,
            'summary' => [
                'total_carried_forward' => $carriedFees->sum('balance_amount'),
                'total_count' => $carriedFees->count(),
                'fee_types' => $grouped->keys()->toArray()
            ]
        ];
    }

    /**
     * Generate collection report by fee_type
     */
    public function collectionReport($filters = []): array
    {
        $query = FeePayment::whereIn('payment_status', ['Completed', 'Partial'])
            ->with(['feeStructure', 'student.user']);

        // Apply date range
        if (isset($filters['from_date'])) {
            $query->where('payment_date', '>=', $filters['from_date']);
        }

        if (isset($filters['to_date'])) {
            $query->where('payment_date', '<=', $filters['to_date']);
        }

        // Apply branch filter
        if (isset($filters['branch_id'])) {
            $query->whereHas('feeStructure', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        $payments = $query->get();

        // Group by fee_type
        $grouped = $payments->groupBy(function($payment) {
            return $payment->feeStructure->fee_type ?? 'General Fee';
        })->map(function($group) {
            return [
                'count' => $group->count(),
                'total_collected' => $group->sum('amount_paid'),
                'payments' => $group
            ];
        });

        return [
            'data' => $payments,
            'grouped_by_fee_type' => $grouped,
            'summary' => [
                'total_collected' => $payments->sum('amount_paid'),
                'total_transactions' => $payments->count(),
                'fee_types' => $grouped->keys()->toArray()
            ]
        ];
    }

    /**
     * Generate overdue report grouped by fee_type
     */
    public function overdueReport($filters = []): array
    {
        $query = FeeDue::where(function($q) {
            $q->where('status', 'Overdue')
              ->orWhere(function($q2) {
                  $q2->where('status', 'Pending')
                     ->where('due_date', '<', now());
              });
        })->with(['student.user', 'feeStructure']);

        // Apply filters
        if (isset($filters['branch_id'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        if (isset($filters['grade'])) {
            $query->where('current_grade', $filters['grade']);
        }

        $overdueFees = $query->get();

        // Calculate aging
        $aging = [
            '0-30' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '90+' => ['count' => 0, 'amount' => 0]
        ];

        foreach ($overdueFees as $fee) {
            $bucket = $fee->aging_bucket;
            $aging[$bucket]['count']++;
            $aging[$bucket]['amount'] += $fee->balance_amount;
        }

        // Group by fee_type
        $grouped = $overdueFees->groupBy('fee_type')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_balance' => $group->sum('balance_amount'),
                'fees' => $group
            ];
        });

        return [
            'data' => $overdueFees,
            'grouped_by_fee_type' => $grouped,
            'aging_analysis' => $aging,
            'summary' => [
                'total_overdue' => $overdueFees->sum('balance_amount'),
                'total_count' => $overdueFees->count(),
                'fee_types' => $grouped->keys()->toArray()
            ]
        ];
    }

    /**
     * Generate student fee statement with fee_type details
     */
    public function studentStatement($studentId, $filters = []): array
    {
        $student = Student::where('user_id', $studentId)->first();
        
        if (!$student) {
            return [
                'student' => null,
                'dues' => [],
                'payments' => [],
                'summary' => []
            ];
        }

        // Get dues
        $duesQuery = FeeDue::where('student_id', $student->id);
        if (isset($filters['academic_year'])) {
            $duesQuery->where('academic_year', $filters['academic_year']);
        }
        $dues = $duesQuery->with('feeStructure')->get();

        // Get payments
        $paymentsQuery = FeePayment::where('student_id', $studentId)
            ->whereIn('payment_status', ['Completed', 'Partial']);
        if (isset($filters['academic_year'])) {
            $paymentsQuery->where('academic_year', $filters['academic_year']);
        }
        $payments = $paymentsQuery->with('feeStructure')->orderBy('payment_date', 'desc')->get();

        // Group dues by fee_type
        $duesByType = $dues->groupBy('fee_type');

        return [
            'student' => $student,
            'dues' => $dues,
            'dues_by_type' => $duesByType,
            'payments' => $payments,
            'summary' => [
                'total_dues' => $dues->sum('balance_amount'),
                'total_paid' => $payments->sum('amount_paid'),
                'fee_types_count' => $duesByType->count()
            ]
        ];
    }

    /**
     * Generate grade-wise dues report
     */
    public function gradeWiseDuesReport($filters = []): array
    {
        $query = FeeDue::with(['student.user', 'feeStructure']);

        // Apply filters
        if (isset($filters['branch_id'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        if (isset($filters['grade'])) {
            $query->where('current_grade', $filters['grade']);
        }

        $dues = $query->get();

        // Group by grade and fee_type
        $grouped = $dues->groupBy('current_grade')->map(function($gradeGroup) {
            return $gradeGroup->groupBy('fee_type')->map(function($typeGroup) {
                return [
                    'count' => $typeGroup->count(),
                    'total_balance' => $typeGroup->sum('balance_amount'),
                    'total_paid' => $typeGroup->sum('paid_amount')
                ];
            });
        });

        return [
            'data' => $dues,
            'grouped_by_grade_and_type' => $grouped,
            'summary' => [
                'total_count' => $dues->count(),
                'total_balance' => $dues->sum('balance_amount'),
                'grades' => $grouped->keys()->toArray()
            ]
        ];
    }

    /**
     * Generate aging analysis report by fee_type
     */
    public function agingAnalysis($filters = []): array
    {
        $query = FeeDue::where('status', '!=', 'Paid')
            ->with(['student.user', 'feeStructure']);

        // Apply filters
        if (isset($filters['branch_id'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        $dues = $query->get();

        // Calculate aging by fee_type
        $agingByType = [];
        
        foreach ($dues as $due) {
            $feeType = $due->fee_type;
            $bucket = $due->aging_bucket;
            
            if (!isset($agingByType[$feeType])) {
                $agingByType[$feeType] = [
                    '0-30' => ['count' => 0, 'amount' => 0],
                    '31-60' => ['count' => 0, 'amount' => 0],
                    '61-90' => ['count' => 0, 'amount' => 0],
                    '90+' => ['count' => 0, 'amount' => 0]
                ];
            }
            
            $agingByType[$feeType][$bucket]['count']++;
            $agingByType[$feeType][$bucket]['amount'] += $due->balance_amount;
        }

        return [
            'data' => $dues,
            'aging_by_fee_type' => $agingByType,
            'summary' => [
                'total_count' => $dues->count(),
                'total_balance' => $dues->sum('balance_amount'),
                'fee_types' => array_keys($agingByType)
            ]
        ];
    }

    /**
     * Generate fee type summary report
     */
    public function feeTypeSummaryReport($filters = []): array
    {
        $query = FeeDue::with(['student.user']);

        // Apply filters
        if (isset($filters['branch_id'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        if (isset($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        $dues = $query->get();

        // Group by fee_type
        $summary = $dues->groupBy('fee_type')->map(function($group) {
            return [
                'total_count' => $group->count(),
                'total_original' => $group->sum('original_amount'),
                'total_paid' => $group->sum('paid_amount'),
                'total_balance' => $group->sum('balance_amount'),
                'overdue_count' => $group->where('status', 'Overdue')->count(),
                'overdue_amount' => $group->where('status', 'Overdue')->sum('balance_amount')
            ];
        });

        return [
            'summary_by_fee_type' => $summary,
            'grand_total' => [
                'total_count' => $dues->count(),
                'total_balance' => $dues->sum('balance_amount'),
                'fee_types_count' => $summary->count()
            ]
        ];
    }
}

