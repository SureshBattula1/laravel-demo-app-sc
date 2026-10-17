<?php

namespace App\Services;

use App\Models\FeeDue;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentFeeAutoAssignService
{
    public function __construct(
        protected AcademicYearContext $academicYearContext,
        protected StudentTransportFeeSyncService $transportSyncService
    ) {}

    /**
     * Synchronize all student fees (Transport, Hostel, Concession/Scholarship).
     */
    public function syncAllFees(Student $student): void
    {
        try {
            DB::beginTransaction();

            $this->syncTransportFee($student);
            $this->syncHostelFee($student);
            $this->syncConcession($student);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('StudentFeeAutoAssignService::syncAllFees failed: '.$e->getMessage(), [
                'student_id' => $student->id,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Synchronize transport details and dues for a student.
     */
    public function syncTransportFee(Student $student): void
    {
        try {
            if (! $student->user_id) {
                return;
            }

            if ($student->transport_required) {
                $route = null;
                if ($student->transport_route) {
                    $route = TransportRoute::withoutTenantScope()
                        ->where('branch_id', $student->branch_id)
                        ->where(function ($q) use ($student) {
                            $q->where('route_name', $student->transport_route)
                                ->orWhere('route_number', $student->transport_route);
                        })
                        ->first();
                }
                if (! $route) {
                    $route = TransportRoute::withoutTenantScope()
                        ->where('branch_id', $student->branch_id)
                        ->first();
                }

                $vehicleId = null;
                if ($student->vehicle_number) {
                    $vehicleId = Vehicle::withoutTenantScope()
                        ->where('branch_id', $student->branch_id)
                        ->where('vehicle_number', $student->vehicle_number)
                        ->value('id');
                }

                $fee = (float) ($student->transport_fee ?: ($route?->fare ?? 0));
                $dueDate = $student->transport_due_date ? Carbon::parse($student->transport_due_date)->toDateString() : null;

                $assignment = StudentTransport::withoutTenantScope()->updateOrCreate(
                    ['student_id' => (int) $student->user_id],
                    [
                        'route_id' => $route?->id ?: 1,
                        'vehicle_id' => $vehicleId,
                        'branch_id' => $student->branch_id,
                        'school_id' => $student->school_id,
                        'stop_name' => $student->pickup_point ?: 'Default Stop',
                        'pickup_time' => $student->pickup_time ?: '07:30:00',
                        'drop_time' => $student->drop_time ?: '16:00:00',
                        'annual_fee' => $fee,
                        'monthly_fee' => $fee,
                        'due_date' => $dueDate,
                        'status' => 'Active',
                    ]
                );

                $this->transportSyncService->syncAssignment($assignment, $fee, $dueDate);
            } else {
                $this->revertTransportFee($student, 'Transport marked as not required');
            }
        } catch (\Throwable $e) {
            Log::error('StudentFeeAutoAssignService::syncTransportFee failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }

    /**
     * Revert / unassign transport fee for student.
     */
    public function revertTransportFee(Student $student, ?string $reason = null): void
    {
        try {
            $student->update([
                'transport_required' => false,
                'transport_route' => null,
                'vehicle_number' => null,
                'pickup_point' => null,
                'drop_point' => null,
                'pickup_time' => null,
                'drop_time' => null,
                'transport_fee' => 0,
                'transport_due_date' => null,
            ]);

            if ($student->user_id) {
                $existing = StudentTransport::withoutTenantScope()
                    ->where('student_id', $student->user_id)
                    ->first();
                if ($existing) {
                    $existing->delete();
                }
            }

            $dues = FeeDue::where('student_id', $student->id)
                ->where('fee_type', 'Transport')
                ->get();

            foreach ($dues as $due) {
                if ((float) $due->paid_amount <= 0) {
                    $due->delete();
                } else {
                    $due->update([
                        'balance_amount' => 0,
                        'status' => 'Paid',
                        'metadata' => array_merge($due->metadata ?? [], [
                            'reverted_at' => now()->toIso8601String(),
                            'reverted_reason' => $reason ?? 'Transport unassigned',
                        ]),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('StudentFeeAutoAssignService::revertTransportFee failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }

    /**
     * Synchronize hostel details and dues for a student.
     */
    public function syncHostelFee(Student $student): void
    {
        try {
            $hostelFee = (float) ($student->hostel_fee ?? 0);
            $isActive = (bool) $student->hostel_required && $hostelFee > 0;

            if ($isActive) {
                $academicYearName = $student->academic_year
                    ?: ($this->academicYearContext->name())
                    ?: date('Y').'-'.(date('Y') + 1);
                $academicYearId = $student->academic_year_id ?: $this->academicYearContext->id(false);

                $feeStructure = FeeStructure::firstOrCreate(
                    [
                        'branch_id' => $student->branch_id,
                        'fee_type' => 'Hostel Fee',
                        'grade' => (string) $student->grade,
                        'academic_year' => $academicYearName,
                    ],
                    [
                        'school_id' => $student->school_id,
                        'academic_year_id' => $academicYearId,
                        'amount' => $hostelFee,
                        'is_active' => true,
                        'is_recurring' => false,
                        'description' => 'Student Hostel Fee',
                    ]
                );

                $dueDate = $student->hostel_due_date
                    ? Carbon::parse($student->hostel_due_date)->toDateString()
                    : ($feeStructure->due_date ?: now()->addMonth()->toDateString());

                $feeDue = FeeDue::where('student_id', $student->id)
                    ->where('fee_type', 'Hostel')
                    ->where(function ($q) use ($academicYearName) {
                        $q->where('academic_year', $academicYearName)
                            ->orWhereNull('academic_year')
                            ->orWhere('academic_year', '');
                    })
                    ->first();

                if ($feeDue) {
                    $paidAmount = (float) $feeDue->paid_amount;
                    $balanceAmount = max(0, $hostelFee - $paidAmount);
                    $status = $balanceAmount <= 0 ? 'Paid' : ($paidAmount > 0 ? 'PartiallyPaid' : 'Pending');
                    $overdueDays = 0;
                    if ($dueDate && Carbon::parse($dueDate)->isPast() && $balanceAmount > 0) {
                        $status = 'Overdue';
                        $overdueDays = max(0, (int) Carbon::now()->diffInDays(Carbon::parse($dueDate)));
                    }

                    $feeDue->update([
                        'fee_structure_id' => $feeStructure->id,
                        'academic_year' => $academicYearName,
                        'current_grade' => (string) $student->grade,
                        'original_amount' => $hostelFee,
                        'balance_amount' => $balanceAmount,
                        'due_date' => $dueDate,
                        'overdue_days' => $overdueDays,
                        'status' => $status,
                        'metadata' => array_merge($feeDue->metadata ?? [], [
                            'hostel_name' => $student->hostel_name,
                            'hostel_room_number' => $student->hostel_room_number,
                            'updated_at' => now()->toIso8601String(),
                        ]),
                    ]);
                } else {
                    $overdueDays = 0;
                    $status = 'Pending';
                    if ($dueDate && Carbon::parse($dueDate)->isPast() && $hostelFee > 0) {
                        $status = 'Overdue';
                        $overdueDays = max(0, (int) Carbon::now()->diffInDays(Carbon::parse($dueDate)));
                    }

                    FeeDue::create([
                        'student_id' => $student->id,
                        'fee_structure_id' => $feeStructure->id,
                        'academic_year' => $academicYearName,
                        'original_grade' => (string) $student->grade,
                        'current_grade' => (string) $student->grade,
                        'original_amount' => $hostelFee,
                        'paid_amount' => 0,
                        'balance_amount' => $hostelFee,
                        'due_date' => $dueDate,
                        'overdue_days' => $overdueDays,
                        'status' => $status,
                        'fee_type' => 'Hostel',
                        'metadata' => [
                            'hostel_name' => $student->hostel_name,
                            'hostel_room_number' => $student->hostel_room_number,
                            'created_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            } else {
                $this->revertHostelFee($student, 'Hostel marked as not required');
            }
        } catch (\Throwable $e) {
            Log::error('StudentFeeAutoAssignService::syncHostelFee failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }

    /**
     * Revert / unassign hostel fee for student.
     */
    public function revertHostelFee(Student $student, ?string $reason = null): void
    {
        try {
            $student->update([
                'hostel_required' => false,
                'hostel_name' => null,
                'hostel_room_number' => null,
                'hostel_fee' => 0,
                'hostel_due_date' => null,
            ]);

            $dues = FeeDue::where('student_id', $student->id)
                ->where('fee_type', 'Hostel')
                ->get();

            foreach ($dues as $due) {
                if ((float) $due->paid_amount <= 0) {
                    $due->delete();
                } else {
                    $due->update([
                        'balance_amount' => 0,
                        'status' => 'Paid',
                        'metadata' => array_merge($due->metadata ?? [], [
                            'reverted_at' => now()->toIso8601String(),
                            'reverted_reason' => $reason ?? 'Hostel unassigned',
                        ]),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('StudentFeeAutoAssignService::revertHostelFee failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }

    /**
     * Synchronize concession and scholarship details on student dues.
     */
    public function syncConcession(Student $student): void
    {
        try {
            if ($student->fee_concession_applicable) {
                $concessionPercentage = (float) ($student->concession_percentage ?? 0);
                $concessionType = $student->concession_type ?? 'General Concession';
                $scholarshipName = $student->scholarship_name ?? null;

                // Tag existing tuition / general fee dues with concession metadata
                $tuitionDues = FeeDue::where('student_id', $student->id)
                    ->whereNotIn('fee_type', ['Transport', 'Hostel'])
                    ->get();

                foreach ($tuitionDues as $due) {
                    $metadata = $due->metadata ?? [];
                    $metadata['concession_applicable'] = true;
                    $metadata['concession_type'] = $concessionType;
                    $metadata['concession_percentage'] = $concessionPercentage;
                    $metadata['scholarship_name'] = $scholarshipName;

                    if ($concessionPercentage > 0 && (float) $due->paid_amount <= 0) {
                        $discount = round(($due->original_amount * $concessionPercentage) / 100, 2);
                        $metadata['concession_discount_amount'] = $discount;
                        $metadata['original_amount_before_concession'] = $due->original_amount;
                        $newBalance = max(0, $due->original_amount - $discount);
                        $due->update([
                            'balance_amount' => $newBalance,
                            'metadata' => $metadata,
                        ]);
                    } else {
                        $due->update(['metadata' => $metadata]);
                    }
                }
            } else {
                $this->revertConcession($student, 'Fee concession disabled');
            }
        } catch (\Throwable $e) {
            Log::error('StudentFeeAutoAssignService::syncConcession failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }

    /**
     * Revert / cancel concession and scholarship on student dues.
     */
    public function revertConcession(Student $student, ?string $reason = null): void
    {
        try {
            $student->update([
                'fee_concession_applicable' => false,
                'concession_percentage' => 0,
                'concession_type' => null,
                'scholarship_name' => null,
                'scholarship_details' => null,
            ]);

            $tuitionDues = FeeDue::where('student_id', $student->id)
                ->whereNotIn('fee_type', ['Transport', 'Hostel'])
                ->get();

            foreach ($tuitionDues as $due) {
                $metadata = $due->metadata ?? [];
                if (isset($metadata['original_amount_before_concession'])) {
                    $originalAmount = (float) $metadata['original_amount_before_concession'];
                    $paid = (float) $due->paid_amount;
                    unset($metadata['concession_applicable'], $metadata['concession_type'], $metadata['concession_percentage'], $metadata['concession_discount_amount'], $metadata['original_amount_before_concession']);
                    $metadata['concession_reverted_at'] = now()->toIso8601String();
                    $metadata['concession_reverted_reason'] = $reason ?? 'Concession reverted';

                    $due->update([
                        'original_amount' => $originalAmount,
                        'balance_amount' => max(0, $originalAmount - $paid),
                        'metadata' => $metadata,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('StudentFeeAutoAssignService::revertConcession failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }
}
