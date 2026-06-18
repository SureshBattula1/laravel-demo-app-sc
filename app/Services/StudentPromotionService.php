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
     * Check if a student matches for promotion (enrollment-based when from_year available)
     */
    public function studentMatchesForPromotion(Student $student, string $fromGrade, ?int $fromAcademicYearId, ?string $fromSection = null): bool
    {
        if ($fromAcademicYearId) {
            $enrollment = StudentEnrollment::query()
                ->where('student_id', $student->id)
                ->where('academic_year_id', $fromAcademicYearId)
                ->first();
            if ($enrollment) {
                $gradeMatch = (string) $enrollment->grade === (string) $fromGrade;
                if ($fromSection !== null && $fromSection !== '') {
                    return $gradeMatch && (string) ($enrollment->section ?? '') === (string) $fromSection;
                }
                return $gradeMatch;
            }
        }
        $gradeMatch = (string) $student->grade === (string) $fromGrade;
        if ($fromSection !== null && $fromSection !== '') {
            return $gradeMatch && (string) ($student->section ?? '') === (string) $fromSection;
        }
        return $gradeMatch;
    }

    /**
     * Promote students with fee handling
     */
    public function promoteStudentsWithFeeHandling($studentIds, $fromGrade, $toGrade, int $toAcademicYearId, $userId, $checkEligibility = false, ?int $fromAcademicYearId = null, ?string $fromSection = null, ?string $toSection = null): array
    {
        DB::beginTransaction();
        
        try {
            $promotedCount = 0;
            $results = [];

            $toAcademicYear = AcademicYear::query()->findOrFail($toAcademicYearId);

            foreach ($studentIds as $studentId) {
                $student = Student::find($studentId);
                
                if (!$student) {
                    continue;
                }

                $effectiveFromYearId = $fromAcademicYearId ?? $this->resolveFromAcademicYearId($student);
                if (!$this->studentMatchesForPromotion($student, $fromGrade, $effectiveFromYearId, $fromSection)) {
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

                $fromAcademicYear = $effectiveFromYearId
                    ? (AcademicYear::query()->find($effectiveFromYearId)?->name ?? $student->academic_year)
                    : ($student->academic_year ?? null);

                // Ensure source enrollment exists for history completeness
                if ($effectiveFromYearId) {
                    $this->ensureSourceEnrollment($student, $effectiveFromYearId, $fromGrade, $userId, $fromSection);
                }

                // Carry forward pending fees (useOwnTransaction=false - we're already in a transaction)
                $carryForwardResult = $this->feeCarryForwardService->carryForwardFees(
                    $student->user_id,
                    $fromGrade,
                    $toGrade,
                    $fromAcademicYear,
                    $toAcademicYear->name,
                    $userId,
                    false
                );

                // Create/ensure enrollment for the target year (history-safe)
                $this->createOrUpdateEnrollment($student, $toAcademicYearId, $toGrade, $toSection, $userId);

                // Update student legacy columns for backward compatibility (current-year screens)
                $student->update([
                    'grade' => $toGrade,
                    'academic_year' => $toAcademicYear->name,
                    'academic_year_id' => $toAcademicYearId,
                    'section' => $toSection,
                ]);

                // Create promotion history
                $this->createPromotionHistory($student, $fromGrade, $toGrade, $fromAcademicYear, $toAcademicYear->name, $userId, $effectiveFromYearId, $toAcademicYearId);

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

            Log::info('Students promoted with fee handling - DB committed', [
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
            'academic_year_from' => $fromAcademicYear ?? $student->academic_year ?? 'Unknown',
            'academic_year_to' => $toAcademicYear ?? 'Unknown',
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

    /**
     * Ensure source enrollment exists for from_year (for history completeness)
     */
    public function ensureSourceEnrollment(Student $student, int $academicYearId, string $grade, int $userId, ?string $section = null): void
    {
        StudentEnrollment::query()->firstOrCreate(
            [
                'student_id' => $student->id,
                'academic_year_id' => $academicYearId,
            ],
            [
                'school_id' => $student->school_id,
                'branch_id' => $student->branch_id,
                'grade' => $grade,
                'section' => $section ?? $student->section,
                'roll_number' => $student->roll_number ? (string) $student->roll_number : null,
                'status' => 'Active',
                'created_by' => $userId,
                'updated_by' => $userId,
            ]
        );
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
     * Revert (unpromote) students back to a previous grade/section within an academic year
     */
    public function revertPromotion(array $studentIds, string $fromGrade, string $toGrade, int $academicYearId, int $userId, ?string $fromSection = null, ?string $toSection = null): array
    {
        DB::beginTransaction();
        try {
            $revertedCount = 0;
            $academicYear = AcademicYear::query()->findOrFail($academicYearId);

            foreach ($studentIds as $studentId) {
                $student = Student::find($studentId);
                if (!$student || !$this->studentMatchesForPromotion($student, $fromGrade, $academicYearId, $fromSection)) {
                    continue;
                }

                $enrollment = StudentEnrollment::query()
                    ->where('student_id', $student->id)
                    ->where('academic_year_id', $academicYearId)
                    ->first();

                if ($enrollment) {
                    $enrollment->update([
                        'grade' => $toGrade,
                        'section' => $toSection,
                        'updated_by' => $userId,
                    ]);
                }

                $student->update([
                    'grade' => $toGrade,
                    'section' => $toSection,
                    'academic_year' => $academicYear->name,
                    'academic_year_id' => $academicYearId,
                ]);

                // Update the original Promoted record to Reverted (instead of creating duplicate)
                // Original promotion was: from_grade=toGrade -> to_grade=fromGrade for this academic year
                $originalPromotion = ClassUpgrade::where('student_id', $student->id)
                    ->where('from_grade', $toGrade)
                    ->where('to_grade', $fromGrade)
                    ->where('to_academic_year_id', $academicYearId)
                    ->where('promotion_status', 'Promoted')
                    ->first();

                if ($originalPromotion) {
                    $originalPromotion->update([
                        'promotion_status' => 'Reverted',
                        'notes' => json_encode(array_merge(
                            json_decode($originalPromotion->notes ?? '{}', true) ?? [],
                            ['reverted_at' => now()->toIso8601String(), 'from_section' => $fromSection, 'to_section' => $toSection]
                        )),
                    ]);
                } else {
                    // Fallback: create Reverted record if original promotion not found (legacy flow)
                    ClassUpgrade::create([
                        'student_id' => $student->id,
                        'academic_year_from' => $academicYear->name,
                        'academic_year_to' => $academicYear->name,
                        'from_academic_year_id' => $academicYearId,
                        'to_academic_year_id' => $academicYearId,
                        'from_grade' => $fromGrade,
                        'to_grade' => $toGrade,
                        'promotion_status' => 'Reverted',
                        'approved_by' => $userId,
                        'fee_carry_forward_status' => 'None',
                        'fee_carry_forward_amount' => 0,
                        'notes' => json_encode(['from_section' => $fromSection, 'to_section' => $toSection]),
                    ]);
                }

                $revertedCount++;
            }

            DB::commit();
            Log::info('Students reverted', ['reverted_count' => $revertedCount, 'from_grade' => $fromGrade, 'to_grade' => $toGrade]);

            return ['success' => true, 'reverted_count' => $revertedCount];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Revert promotion error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Generate promotion summary report
     */
    public function generatePromotionReport($fromGrade, $toGrade, $academicYear): array
    {
        $promotions = ClassUpgrade::where('from_grade', $fromGrade)
            ->where('to_grade', $toGrade)
            ->where('academic_year_to', $academicYear)
            ->where('promotion_status', 'Promoted')
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

