<?php

namespace App\Services;

use App\Models\FeeStructure;
use App\Models\FeePayment;
use App\Models\FeeDue;
use App\Models\Student;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class FeeCarryForwardService
{
    /**
     * Identify pending fees for a student, grouped by fee_type
     */
    public function identifyPendingFees($studentId, $grade = null, $academicYear = null): array
    {
        // Accept both user_id and student id
        $student = is_numeric($studentId) 
            ? Student::where('user_id', $studentId)->orWhere('id', $studentId)->first()
            : Student::where('user_id', $studentId)->first();
        
        if (!$student) {
            return [];
        }

        $grade = $grade ?? $student->grade;
        $academicYear = $academicYear ?? $student->academic_year;
        $actualUserId = $student->user_id; // Use actual user_id for payment queries

        // Get all fee structures for the student's grade and branch
        $feeStructures = FeeStructure::where('is_active', true)
            ->where('branch_id', $student->branch_id)
            ->where(function($q) use ($grade) {
                $q->where('grade', $grade)
                  ->orWhere('grade', 'Grade ' . $grade)
                  ->orWhere('grade', str_replace('Grade ', '', $grade));
            })
            ->where('academic_year', $academicYear)
            ->get();

        // Get paid structure IDs (fully paid) - use user_id
        $paidStructureIds = FeePayment::where('student_id', $actualUserId)
            ->where('payment_status', 'Completed')
            ->pluck('fee_structure_id')
            ->unique()
            ->toArray();

        // Get already carried forward fee structure IDs to avoid duplicates
        $carriedForwardStructureIds = FeeDue::where('student_id', $student->id)
            ->where('academic_year', $academicYear)
            ->where('status', 'CarriedForward')
            ->whereNotNull('fee_structure_id')
            ->pluck('fee_structure_id')
            ->unique()
            ->toArray();

        $pendingFees = [];

        foreach ($feeStructures as $feeStructure) {
            // Skip if fully paid
            if (in_array($feeStructure->id, $paidStructureIds)) {
                continue;
            }

            // Skip if already carried forward
            if (in_array($feeStructure->id, $carriedForwardStructureIds)) {
                continue;
            }

            // Calculate amount paid - use user_id
            $amountPaid = FeePayment::where('student_id', $actualUserId)
                ->where('fee_structure_id', $feeStructure->id)
                ->whereIn('payment_status', ['Completed', 'Partial'])
                ->sum('total_amount');

            $balanceAmount = max(0, $feeStructure->amount - $amountPaid);

            if ($balanceAmount > 0) {
                $feeType = $feeStructure->fee_type ?? 'General Fee';
                
                if (!isset($pendingFees[$feeType])) {
                    $pendingFees[$feeType] = [];
                }

                $pendingFees[$feeType][] = [
                    'fee_structure_id' => $feeStructure->id,
                    'fee_type' => $feeType,
                    'original_amount' => $feeStructure->amount,
                    'paid_amount' => $amountPaid,
                    'balance_amount' => $balanceAmount,
                    'due_date' => $feeStructure->due_date,
                    'description' => $feeStructure->description,
                    'status' => $amountPaid > 0 ? 'PartiallyPaid' : 'Unpaid'
                ];
            }
        }

        return $pendingFees;
    }

    /**
     * Get detailed breakdown by fee_type
     */
    public function getPendingFeesBreakdown($studentId, $grade = null, $academicYear = null): array
    {
        // identifyPendingFees handles both user_id and student id
        $pendingFees = $this->identifyPendingFees($studentId, $grade, $academicYear);
        
        $breakdown = [];
        
        foreach ($pendingFees as $feeType => $fees) {
            $totalOriginal = 0;
            $totalPaid = 0;
            $totalBalance = 0;
            $minDueDate = null;
            $maxOverdueDays = 0;

            foreach ($fees as $fee) {
                $totalOriginal += $fee['original_amount'];
                $totalPaid += $fee['paid_amount'];
                $totalBalance += $fee['balance_amount'];
                
                if ($fee['due_date']) {
                    $dueDate = Carbon::parse($fee['due_date']);
                    if (!$minDueDate || $dueDate->lt($minDueDate)) {
                        $minDueDate = $dueDate;
                    }
                    
                    $overdueDays = max(0, now()->diffInDays($dueDate, false) * -1);
                    $maxOverdueDays = max($maxOverdueDays, $overdueDays);
                }
            }

            $breakdown[] = [
                'fee_type' => $feeType,
                'fee_count' => count($fees),
                'total_original_amount' => $totalOriginal,
                'total_paid_amount' => $totalPaid,
                'total_balance_amount' => $totalBalance,
                'due_date' => $minDueDate ? $minDueDate->format('Y-m-d') : null,
                'overdue_days' => $maxOverdueDays,
                'status' => $maxOverdueDays > 0 ? 'Overdue' : ($totalPaid > 0 ? 'PartiallyPaid' : 'Pending'),
                'fees' => $fees
            ];
        }

        return $breakdown;
    }

    /**
     * Calculate overdue days
     */
    public function calculateOverdueDays($dueDate): int
    {
        if (!$dueDate) {
            return 0;
        }

        $due = Carbon::parse($dueDate);
        $days = now()->diffInDays($due, false);
        
        return max(0, $days * -1); // Return positive number for overdue days
    }

    /**
     * Carry forward fees for a student
     */
    public function carryForwardFees($studentId, $fromGrade, $toGrade, $fromAcademicYear, $toAcademicYear, $userId): array
    {
        DB::beginTransaction();
        
        try {
            // Accept both user_id and student id
            $student = is_numeric($studentId) 
                ? Student::where('user_id', $studentId)->orWhere('id', $studentId)->first()
                : Student::where('user_id', $studentId)->first();
            
            if (!$student) {
                throw new \Exception('Student not found');
            }

            // Check if fees already carried forward for this promotion to prevent duplicates
            $existingCarriedFees = FeeDue::where('student_id', $student->id)
                ->where('academic_year', $fromAcademicYear)
                ->where('original_grade', $fromGrade)
                ->where('status', 'CarriedForward')
                ->where('carry_forward_date', '>=', now()->subDays(1))
                ->exists();

            if ($existingCarriedFees) {
                throw new \Exception('Fees have already been carried forward for this student');
            }

            $pendingFees = $this->identifyPendingFees($student->user_id, $fromGrade, $fromAcademicYear);
            $carriedFees = [];
            $totalCarried = 0;
            $auditService = new AuditService();

            foreach ($pendingFees as $feeType => $fees) {
                foreach ($fees as $fee) {
                    $overdueDays = $this->calculateOverdueDays($fee['due_date']);
                    $status = $overdueDays > 0 ? 'Overdue' : ($fee['status'] === 'PartiallyPaid' ? 'PartiallyPaid' : 'Pending');
                    
                    $feeDue = FeeDue::create([
                        'student_id' => $student->id,
                        'fee_structure_id' => $fee['fee_structure_id'],
                        'academic_year' => $fromAcademicYear,
                        'original_grade' => $fromGrade,
                        'current_grade' => $toGrade,
                        'original_amount' => $fee['original_amount'],
                        'paid_amount' => $fee['paid_amount'],
                        'balance_amount' => $fee['balance_amount'],
                        'due_date' => $fee['due_date'],
                        'overdue_days' => $overdueDays,
                        'status' => 'CarriedForward',
                        'carry_forward_date' => now(),
                        'carry_forward_reason' => "Carried forward from {$fromGrade} to {$toGrade}",
                        'fee_type' => $feeType,
                        'metadata' => [
                            'promoted_from_grade' => $fromGrade,
                            'promoted_to_grade' => $toGrade,
                            'original_academic_year' => $fromAcademicYear
                        ]
                    ]);

                    $carriedFees[] = $feeDue;
                    $totalCarried += $fee['balance_amount'];

                    // Log audit trail
                    try {
                        $auditService->log('CarryForward', $student->id, [
                            'fee_due_id' => $feeDue->id,
                            'amount_before' => 0,
                            'amount_after' => $fee['balance_amount'],
                            'action_amount' => $fee['balance_amount'],
                            'reason' => "Carried forward from {$fromGrade} to {$toGrade}",
                            'metadata' => [
                                'from_grade' => $fromGrade,
                                'to_grade' => $toGrade,
                                'fee_type' => $feeType,
                                'academic_year_from' => $fromAcademicYear,
                                'academic_year_to' => $toAcademicYear
                            ],
                            'user_id' => $userId
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Failed to log audit for carry forward', ['error' => $e->getMessage()]);
                    }
                }
            }

            DB::commit();

            Log::info('Fees carried forward', [
                'student_id' => $student->id,
                'from_grade' => $fromGrade,
                'to_grade' => $toGrade,
                'total_carried' => $totalCarried,
                'fees_count' => count($carriedFees)
            ]);

            return [
                'success' => true,
                'carried_fees' => $carriedFees,
                'total_amount' => $totalCarried,
                'fee_types_count' => count($pendingFees)
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error carrying forward fees', [
                'student_id' => $studentId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get carry-forward summary for preview
     */
    public function getCarryForwardSummary($studentId, $fromGrade, $toGrade, $fromAcademicYear): array
    {
        // Accept both user_id and student id
        $student = is_numeric($studentId) 
            ? Student::where('user_id', $studentId)->orWhere('id', $studentId)->first()
            : Student::where('user_id', $studentId)->first();
        
        if (!$student) {
            return [
                'student_id' => $studentId,
                'from_grade' => $fromGrade,
                'to_grade' => $toGrade,
                'academic_year' => $fromAcademicYear,
                'pending_fees_breakdown' => [],
                'total_pending_amount' => 0,
                'fee_types_count' => 0
            ];
        }
        
        $breakdown = $this->getPendingFeesBreakdown($student->user_id, $fromGrade, $fromAcademicYear);
        
        $totalPending = array_sum(array_column($breakdown, 'total_balance_amount'));
        
        return [
            'student_id' => $studentId,
            'from_grade' => $fromGrade,
            'to_grade' => $toGrade,
            'academic_year' => $fromAcademicYear,
            'pending_fees_breakdown' => $breakdown,
            'total_pending_amount' => $totalPending,
            'fee_types_count' => count($breakdown)
        ];
    }
}

