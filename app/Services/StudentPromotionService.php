<?php

namespace App\Services;

use App\Models\Student;
use App\Models\ClassUpgrade;
use App\Models\FeeStructure;
use App\Services\FeeCarryForwardService;
use App\Services\FeeNotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentPromotionService
{
    protected $feeCarryForwardService;
    protected $notificationService;

    public function __construct(FeeCarryForwardService $feeCarryForwardService, FeeNotificationService $notificationService = null)
    {
        $this->feeCarryForwardService = $feeCarryForwardService;
        $this->notificationService = $notificationService ?? app(FeeNotificationService::class);
    }

    /**
     * Promote students with fee handling
     */
    public function promoteStudentsWithFeeHandling($studentIds, $fromGrade, $toGrade, $academicYear, $userId, $checkEligibility = false): array
    {
        DB::beginTransaction();
        
        try {
            $promotedCount = 0;
            $results = [];

            foreach ($studentIds as $studentId) {
                $student = Student::find($studentId);
                
                if (!$student || $student->grade !== $fromGrade) {
                    continue;
                }

                // Check eligibility if required
                if ($checkEligibility && !$this->checkPromotionEligibility($student)) {
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'skipped',
                        'reason' => 'Not eligible for promotion'
                    ];
                    continue;
                }

                $fromAcademicYear = $student->academic_year;

                // Carry forward pending fees
                $carryForwardResult = $this->feeCarryForwardService->carryForwardFees(
                    $student->user_id,
                    $fromGrade,
                    $toGrade,
                    $fromAcademicYear,
                    $academicYear,
                    $userId
                );

                // Update student grade and academic year
                $student->update([
                    'grade' => $toGrade,
                    'academic_year' => $academicYear,
                    'section' => null // Reset section for new grade
                ]);

                // Create promotion history
                $this->createPromotionHistory($student, $fromGrade, $toGrade, $fromAcademicYear, $academicYear, $userId);

                // Send notification
                if ($carryForwardResult['total_amount'] > 0) {
                    try {
                        $feeSummary = $this->feeCarryForwardService->getCarryForwardSummary(
                            $student->user_id,
                            $fromGrade,
                            $toGrade,
                            $fromAcademicYear
                        );
                        $this->notificationService->sendPromotionNotification(
                            $student->id,
                            $fromGrade,
                            $toGrade,
                            $feeSummary
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to send promotion notification', [
                            'student_id' => $studentId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }

                $promotedCount++;
                $results[] = [
                    'student_id' => $studentId,
                    'status' => 'promoted',
                    'fees_carried_forward' => $carryForwardResult['total_amount'] ?? 0
                ];
            }

            DB::commit();

            Log::info('Students promoted with fee handling', [
                'promoted_count' => $promotedCount,
                'from_grade' => $fromGrade,
                'to_grade' => $toGrade,
                'academic_year' => $academicYear
            ]);

            return [
                'success' => true,
                'promoted_count' => $promotedCount,
                'results' => $results
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error promoting students', [
                'error' => $e->getMessage(),
                'student_ids' => $studentIds
            ]);
            throw $e;
        }
    }

    /**
     * Check promotion eligibility (optional - can be enhanced)
     */
    public function checkPromotionEligibility(Student $student): bool
    {
        // Add eligibility checks here (attendance, fees, academic performance, etc.)
        // For now, return true (no blocking conditions)
        return true;
    }

    /**
     * Create promotion history record
     */
    protected function createPromotionHistory($student, $fromGrade, $toGrade, $fromAcademicYear, $toAcademicYear, $userId): ClassUpgrade
    {
        // Get pending fees amount before promotion
        $breakdown = $this->feeCarryForwardService->getPendingFeesBreakdown(
            $student->user_id,
            $fromGrade,
            $fromAcademicYear
        );
        $totalPendingFees = array_sum(array_column($breakdown, 'total_balance_amount'));

        return ClassUpgrade::create([
            'student_id' => $student->id,
            'academic_year_from' => $fromAcademicYear,
            'academic_year_to' => $toAcademicYear,
            'from_grade' => $fromGrade,
            'to_grade' => $toGrade,
            'promotion_status' => 'Promoted',
            'approved_by' => $userId,
            'fee_carry_forward_status' => $totalPendingFees > 0 ? 'CarriedForward' : 'None',
            'fee_carry_forward_amount' => $totalPendingFees
        ]);
    }

    /**
     * Generate promotion summary report
     */
    public function generatePromotionReport($fromGrade, $toGrade, $academicYear): array
    {
        $promotions = ClassUpgrade::where('from_grade', $fromGrade)
            ->where('to_grade', $toGrade)
            ->where('academic_year_to', $academicYear)
            ->with('student')
            ->get();

        $totalPromoted = $promotions->count();
        $totalFeesCarriedForward = $promotions->sum('fee_carry_forward_amount');

        return [
            'from_grade' => $fromGrade,
            'to_grade' => $toGrade,
            'academic_year' => $academicYear,
            'total_promoted' => $totalPromoted,
            'total_fees_carried_forward' => $totalFeesCarriedForward,
            'promotions' => $promotions
        ];
    }
}

