<?php

namespace App\Services;

use App\Models\FeeDue;
use App\Models\FeePayment;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FeeDuesService
{
    /**
     * Get all dues for a student
     */
    public function getStudentDues($studentId, $filters = []): array
    {
        $student = Student::where('user_id', $studentId)->first();
        
        if (!$student) {
            return [
                'dues' => [],
                'summary' => []
            ];
        }

        $query = FeeDue::where('student_id', $student->id);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['fee_type'])) {
            $query->where('fee_type', $filters['fee_type']);
        }

        if (isset($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        $dues = $query->with(['feeStructure', 'student'])->get();

        // Group by fee_type
        $duesByType = [];
        $totalBalance = 0;

        foreach ($dues as $due) {
            $feeType = $due->fee_type;
            
            if (!isset($duesByType[$feeType])) {
                $duesByType[$feeType] = [
                    'total_amount' => 0,
                    'paid_amount' => 0,
                    'balance_amount' => 0,
                    'count' => 0,
                    'overdue_count' => 0,
                    'dues' => []
                ];
            }

            $duesByType[$feeType]['total_amount'] += $due->original_amount;
            $duesByType[$feeType]['paid_amount'] += $due->paid_amount;
            $duesByType[$feeType]['balance_amount'] += $due->balance_amount;
            $duesByType[$feeType]['count']++;
            
            if ($due->status === 'Overdue' || ($due->overdue_days > 0)) {
                $duesByType[$feeType]['overdue_count']++;
            }

            $duesByType[$feeType]['dues'][] = $due;
            $totalBalance += $due->balance_amount;
        }

        return [
            'dues' => $dues,
            'dues_by_type' => $duesByType,
            'total_balance' => $totalBalance,
            'summary' => [
                'total_dues' => $dues->count(),
                'total_balance' => $totalBalance,
                'fee_types_count' => count($duesByType)
            ]
        ];
    }

    /**
     * Update due status based on payments
     */
    public function updateDueStatus($dueId): void
    {
        $due = FeeDue::find($dueId);
        
        if (!$due) {
            return;
        }

        $originalStatus = $due->status;
        $newStatus = $this->calculateStatus($due);
        
        if ($originalStatus !== $newStatus) {
            $due->update(['status' => $newStatus]);
            
            Log::info('Due status updated', [
                'due_id' => $dueId,
                'old_status' => $originalStatus,
                'new_status' => $newStatus
            ]);
        }
    }

    /**
     * Calculate status based on amounts and dates
     */
    protected function calculateStatus(FeeDue $due): string
    {
        if ($due->balance_amount <= 0) {
            return 'Paid';
        }

        if ($due->paid_amount > 0) {
            $status = 'PartiallyPaid';
        } else {
            $status = 'Pending';
        }

        // Check if overdue - due_date is already a Carbon instance from model cast
        if ($due->due_date && $due->due_date->isPast()) {
            $status = 'Overdue';
        }

        return $status;
    }

    /**
     * Calculate aging buckets
     */
    public function calculateAging($dues): array
    {
        $aging = [
            '0-30' => ['count' => 0, 'amount' => 0],
            '31-60' => ['count' => 0, 'amount' => 0],
            '61-90' => ['count' => 0, 'amount' => 0],
            '90+' => ['count' => 0, 'amount' => 0]
        ];

        foreach ($dues as $due) {
            $bucket = $due->aging_bucket;
            $aging[$bucket]['count']++;
            $aging[$bucket]['amount'] += $due->balance_amount;
        }

        return $aging;
    }

    /**
     * Apply payment to specific dues
     */
    public function applyPaymentToDues($paymentId, $dueIds, $amounts): void
    {
        DB::beginTransaction();
        
        try {
            $payment = FeePayment::find($paymentId);
            
            if (!$payment) {
                throw new \Exception('Payment not found');
            }

            $totalAllocated = 0;

            $auditService = new AuditService();
            $student = Student::find($payment->student_id);

            foreach ($dueIds as $index => $dueId) {
                $due = FeeDue::find($dueId);
                $amount = $amounts[$index] ?? 0;

                if ($due && $amount > 0) {
                    $amountBefore = $due->balance_amount;
                    $newPaidAmount = min($due->balance_amount, $due->paid_amount + $amount);
                    $newBalanceAmount = $due->original_amount - $newPaidAmount;

                    $due->update([
                        'paid_amount' => $newPaidAmount,
                        'balance_amount' => $newBalanceAmount
                    ]);

                    $this->updateDueStatus($due->id);
                    $totalAllocated += $amount;

                    // Log audit trail
                    if ($student) {
                        try {
                            $auditService->log('Payment', $student->id, [
                                'fee_payment_id' => $paymentId,
                                'fee_due_id' => $dueId,
                                'amount_before' => $amountBefore,
                                'amount_after' => $newBalanceAmount,
                                'action_amount' => $amount,
                                'reason' => 'Payment applied to fee due',
                                'metadata' => [
                                    'fee_type' => $due->fee_type,
                                    'payment_method' => $payment->payment_method ?? null
                                ]
                            ]);
                        } catch (\Exception $e) {
                            Log::warning('Failed to log audit for payment', ['error' => $e->getMessage()]);
                        }
                    }
                }
            }

            DB::commit();

            Log::info('Payment allocated to dues', [
                'payment_id' => $paymentId,
                'total_allocated' => $totalAllocated
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error allocating payment to dues', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get overdue fees with aging
     */
    public function getOverdueFees($filters = []): array
    {
        $query = FeeDue::overdue();

        // Apply filters
        if (isset($filters['branch_id'])) {
            $query->whereHas('student', function($q) use ($filters) {
                $q->where('branch_id', $filters['branch_id']);
            });
        }

        if (isset($filters['fee_type'])) {
            $query->where('fee_type', $filters['fee_type']);
        }

        if (isset($filters['grade'])) {
            $query->where('current_grade', $filters['grade']);
        }

        $overdueFees = $query->with(['student', 'feeStructure'])->get();
        $aging = $this->calculateAging($overdueFees);

        return [
            'overdue_fees' => $overdueFees,
            'aging_analysis' => $aging,
            'total_count' => $overdueFees->count(),
            'total_amount' => $overdueFees->sum('balance_amount')
        ];
    }

    /**
     * Generate dues report
     */
    public function generateDuesReport($filters = []): array
    {
        $query = FeeDue::with(['student', 'feeStructure']);

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

        if (isset($filters['academic_year'])) {
            $query->where('academic_year', $filters['academic_year']);
        }

        $dues = $query->get();
        $aging = $this->calculateAging($dues);

        // Group by fee_type
        $byFeeType = $dues->groupBy('fee_type')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_balance' => $group->sum('balance_amount'),
                'total_paid' => $group->sum('paid_amount')
            ];
        });

        return [
            'dues' => $dues,
            'summary' => [
                'total_count' => $dues->count(),
                'total_balance' => $dues->sum('balance_amount'),
                'total_paid' => $dues->sum('paid_amount')
            ],
            'aging_analysis' => $aging,
            'by_fee_type' => $byFeeType
        ];
    }
}

