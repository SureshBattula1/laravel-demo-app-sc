<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\ClassUpgrade;
use App\Models\FeeStructure;
use App\Models\StudentEnrollment;
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
    public function promoteStudentsWithFeeHandling($studentIds, $fromGrade, $toGrade, int $toAcademicYearId, $userId, $checkEligibility = false): array
    {
        DB::beginTransaction();
        
        try {
            $promotedCount = 0;
            $results = [];

            $toAcademicYear = AcademicYear::query()->findOrFail($toAcademicYearId);

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

                $fromAcademicYearId = $this->resolveFromAcademicYearId($student);
                $fromAcademicYear = $fromAcademicYearId
                    ? (AcademicYear::query()->find($fromAcademicYearId)?->name ?? $student->academic_year)
                    : ($student->academic_year ?? null);

                // Carry forward pending fees
                $carryForwardResult = $this->feeCarryForwardService->carryForwardFees(
                    $student->user_id,
                    $fromGrade,
                    $toGrade,
                    $fromAcademicYear,
                    $toAcademicYear->name,
                    $userId
                );

                // Create/ensure enrollment for the target year (history-safe)
                $this->createOrUpdateEnrollment($student, $toAcademicYearId, $toGrade, null, $userId);

                // Update student legacy columns for backward compatibility (current-year screens)
                $student->update([
                    'grade' => $toGrade,
                    'academic_year' => $toAcademicYear->name,
                    'academic_year_id' => $toAcademicYearId,
                    'section' => null,
                ]);

                // Create promotion history
                $this->createPromotionHistory($student, $fromGrade, $toGrade, $fromAcademicYear, $toAcademicYear->name, $userId, $fromAcademicYearId, $toAcademicYearId);

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
                'to_academic_year_id' => $toAcademicYearId
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
    protected function createPromotionHistory($student, $fromGrade, $toGrade, $fromAcademicYear, $toAcademicYear, $userId, ?int $fromAcademicYearId = null, ?int $toAcademicYearId = null): ClassUpgrade
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
            'from_academic_year_id' => $fromAcademicYearId,
            'to_academic_year_id' => $toAcademicYearId,
            'from_grade' => $fromGrade,
            'to_grade' => $toGrade,
            'promotion_status' => 'Promoted',
            'approved_by' => $userId,
            'fee_carry_forward_status' => $totalPendingFees > 0 ? 'CarriedForward' : 'None',
            'fee_carry_forward_amount' => $totalPendingFees
        ]);
    }

    protected function resolveFromAcademicYearId(Student $student): ?int
    {
        // Prefer current student academic_year_id (new schema)
        if (!empty($student->academic_year_id)) {
            return (int) $student->academic_year_id;
        }

        // Fallback: resolve from legacy academic_year string
        if (!empty($student->academic_year)) {
            return AcademicYear::query()->where('name', $student->academic_year)->value('id');
        }

        return null;
    }

    protected function createOrUpdateEnrollment(Student $student, int $academicYearId, string $grade, ?string $section, int $userId): StudentEnrollment
    {
        return StudentEnrollment::query()->updateOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
            ],
            [
                'school_id' => $student->school_id,
                'branch_id' => $student->branch_id,
                'grade' => $grade,
                'section' => $section,
                'roll_number' => $student->roll_number ? (string) $student->roll_number : null,
                'status' => 'Active',
                'updated_by' => $userId,
                'created_by' => $userId,
            ]
        );
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

