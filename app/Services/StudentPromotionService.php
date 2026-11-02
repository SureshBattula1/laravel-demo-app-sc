<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ClassUpgrade;
use App\Models\FeeDue;
use App\Models\PromotionBatch;
use App\Models\Student;
use App\Models\StudentEnrollment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class StudentPromotionService
{
    protected $feeCarryForwardService;

    protected $notificationService;

    public function __construct(FeeCarryForwardService $feeCarryForwardService, ?FeeNotificationService $notificationService = null)
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
     * Promote students with fee handling and batch tracking
     */
    public function promoteStudentsWithFeeHandling(
        $studentIds,
        $fromGrade,
        $toGrade,
        int $toAcademicYearId,
        $userId,
        $checkEligibility = false,
        ?int $fromAcademicYearId = null,
        ?string $fromSection = null,
        ?string $toSection = null,
        ?string $batchId = null,
        array $studentSections = [],
        array $studentDispositions = []
    ): array {
        DB::beginTransaction();

        try {
            $promotedCount = 0;
            $retainedCount = 0;
            $totalFeesCarriedForward = 0;
            $results = [];

            $toAcademicYear = AcademicYear::query()->findOrFail($toAcademicYearId);

            // Generate unique batch ID if not supplied
            $batchId = $batchId ?: 'PROMOT_'.date('Ymd').'_'.strtoupper(Str::random(6));

            $firstStudent = ! empty($studentIds) ? Student::find($studentIds[0]) : null;
            $branchId = $firstStudent?->branch_id;
            $schoolId = $firstStudent?->school_id;

            // Create PromotionBatch record upfront so its integer primary key is available for class_upgrades.batch_id
            $promotionBatch = PromotionBatch::create([
                'batch_id' => $batchId,
                'school_id' => $schoolId,
                'branch_id' => $branchId,
                'from_academic_year_id' => $fromAcademicYearId,
                'to_academic_year_id' => $toAcademicYearId,
                'from_grade' => $fromGrade,
                'to_grade' => $toGrade,
                'from_section' => $fromSection,
                'to_section' => $toSection,
                'total_students' => count($studentIds),
                'promoted_count' => 0,
                'retained_count' => 0,
                'reverted_count' => 0,
                'total_fees_carried_forward' => 0,
                'status' => 'completed',
                'executed_by' => $userId,
                'metadata' => [
                    'student_sections' => $studentSections,
                    'student_dispositions' => $studentDispositions,
                    'check_eligibility' => $checkEligibility,
                ],
            ]);

            foreach ($studentIds as $studentId) {
                $student = Student::find($studentId);

                if (! $student) {
                    continue;
                }

                if (! $branchId) {
                    $branchId = $student->branch_id;
                    $schoolId = $student->school_id;
                }

                $effectiveFromYearId = $fromAcademicYearId ?? $this->resolveFromAcademicYearId($student);
                if (! $this->studentMatchesForPromotion($student, $fromGrade, $effectiveFromYearId, $fromSection)) {
                    continue;
                }

                // Check eligibility if required
                if ($checkEligibility && ! $this->checkPromotionEligibility($student)) {
                    $results[] = [
                        'student_id' => $studentId,
                        'status' => 'skipped',
                        'reason' => 'Not eligible for promotion',
                    ];

                    continue;
                }

                // Check per-student disposition (Promote vs Retain in current grade)
                $disposition = $studentDispositions[$studentId] ?? 'Promote';
                if ($disposition === 'Retain') {
                    $assignedToGrade = $fromGrade;
                    $assignedToSection = ! empty($studentSections[$studentId]) ? $studentSections[$studentId] : ($student->section ?: $fromSection);
                } else {
                    $assignedToGrade = $toGrade;
                    $assignedToSection = ! empty($studentSections[$studentId])
                        ? $studentSections[$studentId]
                        : ($toSection ?: $student->section);
                }

                $fromAcademicYear = $effectiveFromYearId
                    ? (AcademicYear::query()->find($effectiveFromYearId)?->name ?? $student->academic_year)
                    : ($student->academic_year ?? null);

                // Ensure source enrollment exists for history completeness
                if ($effectiveFromYearId) {
                    $this->ensureSourceEnrollment($student, $effectiveFromYearId, $fromGrade, $userId, $student->section ?: $fromSection);

                    // Update source year enrollment status to Inactive (or keep Active if Retained)
                    // Note: student_enrollments.status enum supports ['Active', 'Inactive']
                    StudentEnrollment::query()->where('student_id', $student->id)
                        ->where('academic_year_id', $effectiveFromYearId)
                        ->update([
                            'status' => ($disposition === 'Retain') ? 'Active' : 'Inactive',
                            'updated_by' => $userId,
                        ]);
                }

                $carryForwardAmount = 0;
                if ($disposition === 'Promote') {
                    // Carry forward pending fees (useOwnTransaction=false - we're already in a transaction)
                    $carryForwardResult = $this->feeCarryForwardService->carryForwardFees(
                        $student->user_id,
                        $fromGrade,
                        $assignedToGrade,
                        $fromAcademicYear,
                        $toAcademicYear->name,
                        $userId,
                        false
                    );
                    $carryForwardAmount = $carryForwardResult['total_amount'] ?? 0;
                    $totalFeesCarriedForward += $carryForwardAmount;
                }

                // Create/ensure enrollment for the target year (status = Active)
                $this->createOrUpdateEnrollment($student, $toAcademicYearId, $assignedToGrade, $assignedToSection, $userId);

                // Update student legacy columns for current-year views
                $student->update([
                    'grade' => $assignedToGrade,
                    'academic_year' => $toAcademicYear->name,
                    'academic_year_id' => $toAcademicYearId,
                    'section' => $assignedToSection,
                    'student_status' => 'Active',
                ]);

                // Create promotion history record with batch_id foreign key (promotion_batches.id)
                $statusString = ($disposition === 'Retain') ? 'Detained' : 'Promoted';
                $this->createPromotionHistory(
                    $student,
                    $fromGrade,
                    $assignedToGrade,
                    $fromAcademicYear,
                    $toAcademicYear->name,
                    $userId,
                    $effectiveFromYearId,
                    $toAcademicYearId,
                    $promotionBatch->id,
                    $statusString,
                    $carryForwardAmount,
                    $student->section,
                    $assignedToSection
                );

                // Send notification for carried fees if any
                if ($carryForwardAmount > 0) {
                    try {
                        $feeSummary = $this->feeCarryForwardService->getCarryForwardSummary(
                            $student->user_id,
                            $fromGrade,
                            $assignedToGrade,
                            $fromAcademicYear
                        );
                        $this->notificationService->sendPromotionNotification(
                            $student->id,
                            $fromGrade,
                            $assignedToGrade,
                            $feeSummary
                        );
                    } catch (\Exception $e) {
                        Log::warning('Failed to send promotion notification', [
                            'student_id' => $studentId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                if ($disposition === 'Retain') {
                    $retainedCount++;
                } else {
                    $promotedCount++;
                }

                $results[] = [
                    'student_id' => $studentId,
                    'status' => $disposition === 'Retain' ? 'retained' : 'promoted',
                    'assigned_grade' => $assignedToGrade,
                    'assigned_section' => $assignedToSection,
                    'fees_carried_forward' => $carryForwardAmount,
                ];
            }

            // Update PromotionBatch record with actual promoted/retained counts and total fees
            $promotionBatch->update([
                'school_id' => $schoolId ?? $promotionBatch->school_id,
                'branch_id' => $branchId ?? $promotionBatch->branch_id,
                'promoted_count' => $promotedCount,
                'retained_count' => $retainedCount,
                'total_fees_carried_forward' => $totalFeesCarriedForward,
            ]);

            DB::commit();

            Log::info('Promotion batch committed successfully', [
                'batch_id' => $batchId,
                'promoted_count' => $promotedCount,
                'retained_count' => $retainedCount,
                'total_fees' => $totalFeesCarriedForward,
            ]);

            return [
                'success' => true,
                'batch_id' => $batchId,
                'batch' => $promotionBatch->load(['branch', 'fromAcademicYear', 'toAcademicYear', 'executor']),
                'promoted_count' => $promotedCount,
                'retained_count' => $retainedCount,
                'total_fees_carried_forward' => $totalFeesCarriedForward,
                'results' => $results,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error executing promotion batch', [
                'error' => $e->getMessage(),
                'student_ids' => $studentIds,
            ]);
            throw $e;
        }
    }

    /**
     * Check promotion eligibility
     */
    public function checkPromotionEligibility(Student $student): bool
    {
        return true;
    }

    /**
     * Create promotion history record
     */
    protected function createPromotionHistory(
        $student,
        $fromGrade,
        $toGrade,
        $fromAcademicYear,
        $toAcademicYear,
        $userId,
        ?int $fromAcademicYearId = null,
        ?int $toAcademicYearId = null,
        ?int $batchId = null,
        string $promotionStatus = 'Promoted',
        float $feeCarryForwardAmount = 0,
        ?string $fromSection = null,
        ?string $toSection = null
    ): ClassUpgrade {
        return ClassUpgrade::create([
            'batch_id' => $batchId,
            'student_id' => $student->id,
            'academic_year_from' => $fromAcademicYear ?? $student->academic_year ?? 'Unknown',
            'academic_year_to' => $toAcademicYear ?? 'Unknown',
            'from_academic_year_id' => $fromAcademicYearId,
            'to_academic_year_id' => $toAcademicYearId,
            'from_grade' => $fromGrade,
            'to_grade' => $toGrade,
            'promotion_status' => $promotionStatus,
            'approved_by' => $userId,
            'fee_carry_forward_status' => $feeCarryForwardAmount > 0 ? 'CarriedForward' : 'None',
            'fee_carry_forward_amount' => $feeCarryForwardAmount,
            'notes' => json_encode([
                'from_section' => $fromSection ?? $student->section,
                'to_section' => $toSection,
            ]),
        ]);
    }

    protected function resolveFromAcademicYearId(Student $student): ?int
    {
        if (! empty($student->academic_year_id)) {
            return (int) $student->academic_year_id;
        }

        if (! empty($student->academic_year)) {
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
     * Revert an entire promotion batch or specific students in a batch
     */
    public function revertBatch(string $batchId, int $userId, ?array $studentIds = null, ?string $reason = null): array
    {
        DB::beginTransaction();

        try {
            $batch = PromotionBatch::where('batch_id', $batchId)->firstOrFail();

            // Find all upgrades for this batch using integer foreign key
            $upgradesQuery = ClassUpgrade::where('batch_id', $batch->id)
                ->whereIn('promotion_status', ['Promoted', 'Detained']);

            if (! empty($studentIds)) {
                $upgradesQuery->whereIn('student_id', $studentIds);
            }

            $upgrades = $upgradesQuery->get();

            if ($upgrades->isEmpty()) {
                DB::rollBack();

                return [
                    'success' => false,
                    'message' => 'No active promotions found to revert for this batch selection.',
                ];
            }

            $fromAcademicYear = AcademicYear::find($batch->from_academic_year_id);
            $revertedCount = 0;

            foreach ($upgrades as $upgrade) {
                $student = Student::find($upgrade->student_id);
                if (! $student) {
                    continue;
                }

                // Determine original section from upgrade notes, source enrollment, or batch
                $upgradeNotes = json_decode($upgrade->notes ?? '{}', true) ?: [];
                $sourceEnrollment = null;
                if ($upgrade->from_academic_year_id) {
                    $sourceEnrollment = StudentEnrollment::query()
                        ->where('student_id', $student->id)
                        ->where('academic_year_id', $upgrade->from_academic_year_id)
                        ->first();
                }

                $restoredSection = $upgradeNotes['from_section']
                    ?? $sourceEnrollment?->section
                    ?? $batch->from_section
                    ?? $student->section;

                // 1. Restore student legacy columns back to from_grade, original section, and from_academic_year
                $student->update([
                    'grade' => $upgrade->from_grade,
                    'section' => $restoredSection,
                    'academic_year' => $upgrade->academic_year_from,
                    'academic_year_id' => $upgrade->from_academic_year_id,
                    'student_status' => 'Active',
                ]);

                // 2. Reactivate source enrollment
                if ($upgrade->from_academic_year_id) {
                    StudentEnrollment::query()->where('student_id', $student->id)
                        ->where('academic_year_id', $upgrade->from_academic_year_id)
                        ->update([
                            'grade' => $upgrade->from_grade,
                            'section' => $restoredSection,
                            'status' => 'Active',
                            'updated_by' => $userId,
                        ]);
                }

                // 3. Mark target enrollment inactive or revert
                if ($upgrade->to_academic_year_id) {
                    StudentEnrollment::query()->where('student_id', $student->id)
                        ->where('academic_year_id', $upgrade->to_academic_year_id)
                        ->update([
                            'status' => 'Inactive',
                            'updated_by' => $userId,
                        ]);
                }

                // 4. Clean up CarriedForward fee dues created by this promotion
                FeeDue::where('student_id', $student->id)
                    ->where('original_grade', $upgrade->from_grade)
                    ->where('status', 'CarriedForward')
                    ->delete();

                // 5. Update ClassUpgrade record to Reverted
                $existingNotes = json_decode($upgrade->notes ?? '{}', true) ?: [];
                $existingNotes['reverted_at'] = now()->toIso8601String();
                $existingNotes['reverted_by'] = $userId;
                $existingNotes['revert_reason'] = $reason;

                $upgrade->update([
                    'promotion_status' => 'Reverted',
                    'notes' => json_encode($existingNotes),
                ]);

                $revertedCount++;
            }

            // Update PromotionBatch counters and status
            $totalActivePromotions = ClassUpgrade::where('batch_id', $batch->id)
                ->where('promotion_status', 'Promoted')
                ->count();

            $newBatchStatus = ($totalActivePromotions === 0) ? 'reverted' : 'partially_reverted';

            $batch->update([
                'reverted_count' => ($batch->reverted_count ?? 0) + $revertedCount,
                'status' => $newBatchStatus,
                'reverted_by' => $userId,
                'reverted_at' => now(),
            ]);

            DB::commit();

            Log::info('Promotion batch reverted', [
                'batch_id' => $batchId,
                'reverted_count' => $revertedCount,
                'status' => $newBatchStatus,
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'reverted_count' => $revertedCount,
                'status' => $newBatchStatus,
                'message' => "Successfully reverted {$revertedCount} student(s) from batch {$batchId}.",
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error reverting promotion batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Legacy revert method (kept for backward compatibility)
     */
    public function revertPromotion(array $studentIds, string $fromGrade, string $toGrade, int $academicYearId, int $userId, ?string $fromSection = null, ?string $toSection = null): array
    {
        DB::beginTransaction();
        try {
            $revertedCount = 0;
            $academicYear = AcademicYear::query()->findOrFail($academicYearId);

            foreach ($studentIds as $studentId) {
                $student = Student::find($studentId);
                if (! $student || ! $this->studentMatchesForPromotion($student, $fromGrade, $academicYearId, $fromSection)) {
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

                // Clean up any CarriedForward fee dues created
                FeeDue::where('student_id', $student->id)
                    ->where('current_grade', $fromGrade)
                    ->where('status', 'CarriedForward')
                    ->delete();

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
                }

                $revertedCount++;
            }

            DB::commit();

            return ['success' => true, 'reverted_count' => $revertedCount];
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Revert promotion error', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get paginated promotion batches with filtering and searching
     */
    public function getPromotionBatches(array $filters = [], int $perPage = 25, int $page = 1)
    {
        $query = PromotionBatch::with(['branch', 'fromAcademicYear', 'toAcademicYear', 'executor']);

        if (! empty($filters['branch_id'])) {
            $query->where('branch_id', $filters['branch_id']);
        }

        if (! empty($filters['from_academic_year_id'])) {
            $query->where('from_academic_year_id', $filters['from_academic_year_id']);
        }

        if (! empty($filters['to_academic_year_id'])) {
            $query->where('to_academic_year_id', $filters['to_academic_year_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['from_grade'])) {
            $query->where('from_grade', $filters['from_grade']);
        }

        if (! empty($filters['to_grade'])) {
            $query->where('to_grade', $filters['to_grade']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('batch_id', 'like', "%{$search}%")
                    ->orWhere('from_grade', 'like', "%{$search}%")
                    ->orWhere('to_grade', 'like', "%{$search}%");
            });
        }

        $sortField = $filters['sort_by'] ?? 'created_at';
        $sortDirection = $filters['sort_dir'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Get detailed promotion batch information with all students
     */
    public function getPromotionBatchDetails(string $batchId): array
    {
        $batch = PromotionBatch::with(['branch', 'school', 'fromAcademicYear', 'toAcademicYear', 'executor', 'reverter'])
            ->where('batch_id', $batchId)
            ->firstOrFail();

        $upgrades = ClassUpgrade::with(['student.user', 'approvedBy'])
            ->where('batch_id', $batch->id)
            ->get();

        $students = $upgrades->map(function ($upgrade) {
            $student = $upgrade->student;
            $user = $student?->user;

            return [
                'upgrade_id' => $upgrade->id,
                'student_id' => $upgrade->student_id,
                'admission_number' => $student?->admission_number ?? 'N/A',
                'student_name' => $user ? trim("{$user->first_name} {$user->last_name}") : 'N/A',
                'from_grade' => $upgrade->from_grade,
                'to_grade' => $upgrade->to_grade,
                'academic_year_from' => $upgrade->academic_year_from,
                'academic_year_to' => $upgrade->academic_year_to,
                'promotion_status' => $upgrade->promotion_status,
                'fee_carry_forward_status' => $upgrade->fee_carry_forward_status,
                'fee_carry_forward_amount' => (float) $upgrade->fee_carry_forward_amount,
                'approved_by_name' => $upgrade->approvedBy ? trim("{$upgrade->approvedBy->first_name} {$upgrade->approvedBy->last_name}") : null,
                'created_at' => $upgrade->created_at?->toIso8601String(),
                'notes' => json_decode($upgrade->notes ?? '{}', true),
            ];
        });

        return [
            'batch' => $batch,
            'students' => $students,
            'summary' => [
                'total_students' => $batch->total_students,
                'promoted_count' => $batch->promoted_count,
                'retained_count' => $batch->retained_count,
                'reverted_count' => $batch->reverted_count,
                'total_fees_carried_forward' => (float) $batch->total_fees_carried_forward,
                'status' => $batch->status,
            ],
        ];
    }

    /**
     * Get KPI statistics for the Promotion Dashboard
     */
    public function getPromotionDashboardStats(?int $branchId = null, ?int $academicYearId = null): array
    {
        $batchesQuery = PromotionBatch::query();
        $upgradesQuery = ClassUpgrade::query();

        if ($branchId) {
            $batchesQuery->where('branch_id', $branchId);
            $upgradesQuery->whereHas('student', function ($q) use ($branchId) {
                $q->where('branch_id', $branchId);
            });
        }

        if ($academicYearId) {
            $batchesQuery->where('to_academic_year_id', $academicYearId);
            $upgradesQuery->where('to_academic_year_id', $academicYearId);
        }

        $totalPromoted = (clone $upgradesQuery)->where('promotion_status', 'Promoted')->count();
        $totalRetained = (clone $upgradesQuery)->where('promotion_status', 'Detained')->count();
        $totalReverted = (clone $upgradesQuery)->where('promotion_status', 'Reverted')->count();
        $totalFeesCarried = (float) (clone $upgradesQuery)->sum('fee_carry_forward_amount');

        $totalBatches = (clone $batchesQuery)->count();
        $recentBatches = (clone $batchesQuery)->with(['branch', 'fromAcademicYear', 'toAcademicYear', 'executor'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return [
            'total_promoted' => $totalPromoted,
            'total_retained' => $totalRetained,
            'total_reverted' => $totalReverted,
            'total_fees_carried_forward' => $totalFeesCarried,
            'total_batches' => $totalBatches,
            'recent_batches' => $recentBatches,
        ];
    }
}
