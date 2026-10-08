<?php

namespace App\Services;

use App\Models\FeeDue;
use App\Models\FeeStructure;
use App\Models\RouteStop;
use App\Models\Student;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class StudentTransportFeeSyncService
{
    public function __construct(
        protected AcademicYearContext $academicYearContext
    ) {}

    /**
     * Synchronize a student's transport assignment to:
     * 1. The students table (profile view fields).
     * 2. The fee_structures table (branch grade transport fee anchor).
     * 3. The fee_dues table (fee ledger for student dues & finance tracking).
     */
    public function syncAssignment(StudentTransport $assignment, ?float $overrideFee = null, ?string $overrideDueDate = null): void
    {
        try {
            if ($overrideDueDate !== null) {
                $assignment->due_date = $overrideDueDate ? Carbon::parse($overrideDueDate)->toDateString() : null;
                $assignment->save();
            }

            $student = Student::where('user_id', $assignment->student_id)->first();
            if (! $student) {
                Log::warning('StudentTransportFeeSync: Student record not found for user_id', [
                    'user_id' => $assignment->student_id,
                ]);

                return;
            }

            $route = $assignment->route ?: TransportRoute::find($assignment->route_id);
            $vehicle = $assignment->vehicle
                ?: ($assignment->vehicle_id ? Vehicle::find($assignment->vehicle_id) : null)
                ?: ($assignment->route_id ? Vehicle::where('route_id', $assignment->route_id)->first() : null);

            $pickupStop = $assignment->pickup_stop_id ? RouteStop::find($assignment->pickup_stop_id) : null;
            $dropStop = $assignment->drop_stop_id ? RouteStop::find($assignment->drop_stop_id) : null;

            $fee = $overrideFee !== null
                ? (float) $overrideFee
                : (float) ($assignment->annual_fee ?: $assignment->monthly_fee ?: ($route?->fare ?? 0));

            $isActive = strcasecmp((string) ($assignment->status ?? 'Active'), 'Active') === 0;

            // -------------------------------------------------------------
            // 1. UPDATE STUDENTS PROFILE TABLE
            // -------------------------------------------------------------
            $pickupPoint = $pickupStop?->stop_name ?: ($assignment->stop_name ?: $student->pickup_point);
            $dropPoint = $dropStop?->stop_name ?: $student->drop_point;
            $pickupTime = $assignment->pickup_time ?: ($pickupStop?->pickup_time ?: $student->pickup_time);
            $dropTime = $assignment->drop_time ?: ($dropStop?->drop_time ?: $student->drop_time);
            $routeName = $route ? ($route->route_name ?: $route->route_number) : $student->transport_route;
            $vehicleNumber = $vehicle?->vehicle_number ?: $student->vehicle_number;

            $student->update([
                'transport_required' => $isActive,
                'transport_route' => $isActive ? $routeName : null,
                'vehicle_number' => $isActive ? $vehicleNumber : null,
                'pickup_point' => $isActive ? $pickupPoint : null,
                'drop_point' => $isActive ? $dropPoint : null,
                'pickup_time' => $isActive ? $pickupTime : null,
                'drop_time' => $isActive ? $dropTime : null,
                'transport_fee' => $isActive ? $fee : 0,
            ]);

            // -------------------------------------------------------------
            // 2. RESOLVE ACADEMIC YEAR CONTEXT
            // -------------------------------------------------------------
            $academicYearName = $student->academic_year
                ?: ($route?->branch?->current_academic_year)
                ?: ($this->academicYearContext->name())
                ?: date('Y').'-'.(date('Y') + 1);

            $academicYearId = $student->academic_year_id ?: $this->academicYearContext->id(false);

            // -------------------------------------------------------------
            // 3. ANCHOR / RESOLVE FEE STRUCTURE
            // -------------------------------------------------------------
            $branchId = $assignment->branch_id ?: $student->branch_id;
            $schoolId = $assignment->school_id ?: $student->school_id;

            $feeStructure = FeeStructure::firstOrCreate(
                [
                    'branch_id' => $branchId,
                    'fee_type' => 'Transport Fee',
                    'grade' => (string) $student->grade,
                    'academic_year' => $academicYearName,
                ],
                [
                    'school_id' => $schoolId,
                    'academic_year_id' => $academicYearId,
                    'amount' => $fee,
                    'is_active' => true,
                    'is_recurring' => false,
                    'description' => 'Student Transport Fee',
                ]
            );

            // -------------------------------------------------------------
            // 4. SYNC FEE DUE LEDGER RECORD
            // -------------------------------------------------------------
            $assignmentDueDate = $assignment->due_date ? Carbon::parse($assignment->due_date)->toDateString() : null;
            if ($assignmentDueDate && ! $feeStructure->due_date) {
                $feeStructure->update(['due_date' => $assignmentDueDate]);
            }

            $feeDue = FeeDue::where('student_id', $student->id)
                ->where('fee_type', 'Transport')
                ->where(function ($q) use ($academicYearName) {
                    $q->where('academic_year', $academicYearName)
                        ->orWhereNull('academic_year')
                        ->orWhere('academic_year', '');
                })
                ->first();

            if ($isActive) {
                if ($feeDue) {
                    $paidAmount = (float) $feeDue->paid_amount;
                    $balanceAmount = max(0, $fee - $paidAmount);
                    $effectiveDueDate = $assignmentDueDate ?: ($feeDue->due_date ?: ($feeStructure->due_date ?: now()->addMonth()->toDateString()));

                    $status = $balanceAmount <= 0 ? 'Paid' : ($paidAmount > 0 ? 'PartiallyPaid' : 'Pending');
                    $overdueDays = 0;
                    if ($effectiveDueDate && Carbon::parse($effectiveDueDate)->isPast() && $balanceAmount > 0) {
                        $status = 'Overdue';
                        $overdueDays = max(0, (int) Carbon::now()->diffInDays(Carbon::parse($effectiveDueDate)));
                    }

                    $feeDue->update([
                        'fee_structure_id' => $feeStructure->id,
                        'academic_year' => $academicYearName,
                        'current_grade' => (string) $student->grade,
                        'original_amount' => $fee,
                        'balance_amount' => $balanceAmount,
                        'due_date' => $effectiveDueDate,
                        'overdue_days' => $overdueDays,
                        'status' => $status,
                        'metadata' => [
                            'assignment_id' => $assignment->id,
                            'route_id' => $assignment->route_id,
                            'route_name' => $route?->route_name,
                            'pickup_stop_id' => $assignment->pickup_stop_id,
                            'drop_stop_id' => $assignment->drop_stop_id,
                            'stop_name' => $pickupPoint,
                            'vehicle_id' => $assignment->vehicle_id,
                            'updated_at' => now()->toIso8601String(),
                        ],
                    ]);
                } else {
                    $dueDate = $assignmentDueDate ?: ($feeStructure->due_date ?: now()->addMonth()->toDateString());
                    $overdueDays = 0;
                    $status = 'Pending';
                    if ($dueDate && Carbon::parse($dueDate)->isPast() && $fee > 0) {
                        $status = 'Overdue';
                        $overdueDays = max(0, (int) Carbon::now()->diffInDays(Carbon::parse($dueDate)));
                    }

                    FeeDue::create([
                        'student_id' => $student->id,
                        'fee_structure_id' => $feeStructure->id,
                        'academic_year' => $academicYearName,
                        'original_grade' => (string) $student->grade,
                        'current_grade' => (string) $student->grade,
                        'original_amount' => $fee,
                        'paid_amount' => 0,
                        'balance_amount' => $fee,
                        'due_date' => $dueDate,
                        'overdue_days' => $overdueDays,
                        'status' => $status,
                        'fee_type' => 'Transport',
                        'metadata' => [
                            'assignment_id' => $assignment->id,
                            'route_id' => $assignment->route_id,
                            'route_name' => $route?->route_name,
                            'pickup_stop_id' => $assignment->pickup_stop_id,
                            'drop_stop_id' => $assignment->drop_stop_id,
                            'stop_name' => $pickupPoint,
                            'vehicle_id' => $assignment->vehicle_id,
                            'created_at' => now()->toIso8601String(),
                        ],
                    ]);
                }
            } else {
                // Inactive assignment:
                if ($feeDue) {
                    if ((float) $feeDue->paid_amount <= 0) {
                        $feeDue->delete();
                    } else {
                        $feeDue->update([
                            'balance_amount' => 0,
                            'status' => 'Paid',
                        ]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error('StudentTransportFeeSyncService::syncAssignment failed: '.$e->getMessage(), [
                'assignment_id' => $assignment->id ?? null,
                'student_user_id' => $assignment->student_id ?? null,
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Unassign a student from transport: clears students table columns and closes/deletes dues.
     */
    public function handleUnassignment(int $studentUserId): void
    {
        try {
            $student = Student::where('user_id', $studentUserId)->first();
            if (! $student) {
                return;
            }

            $student->update([
                'transport_required' => false,
                'transport_route' => null,
                'vehicle_number' => null,
                'pickup_point' => null,
                'drop_point' => null,
                'pickup_time' => null,
                'drop_time' => null,
                'transport_fee' => 0,
            ]);

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
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('StudentTransportFeeSyncService::handleUnassignment failed: '.$e->getMessage(), [
                'student_user_id' => $studentUserId,
            ]);
        }
    }

    /**
     * Sync from student profile form edit (StudentController::store or update).
     */
    public function syncFromStudentProfile(Student $student): void
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
                        'status' => 'Active',
                    ]
                );

                $this->syncAssignment($assignment, $fee);
            } else {
                // If not required, deactivate existing assignment if any
                $existing = StudentTransport::withoutTenantScope()
                    ->where('student_id', $student->user_id)
                    ->first();
                if ($existing) {
                    $existing->delete();
                }
                $this->handleUnassignment((int) $student->user_id);
            }
        } catch (\Exception $e) {
            Log::error('StudentTransportFeeSyncService::syncFromStudentProfile failed: '.$e->getMessage(), [
                'student_id' => $student->id,
            ]);
        }
    }
}
