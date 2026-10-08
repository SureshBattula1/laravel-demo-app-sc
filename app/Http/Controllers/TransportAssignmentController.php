<?php

namespace App\Http\Controllers;

use App\Http\Requests\BulkAssignTransportRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\RouteStop;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use App\Services\StudentTransportFeeSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportAssignmentController extends Controller
{
    use PaginatesAndSorts;

    public function __construct(
        protected StudentTransportFeeSyncService $syncService
    ) {}

    /** Paginated list of student transport assignments with filters. */
    public function index(Request $request)
    {
        try {
            $query = DB::table('student_transport')
                ->leftJoin('users as u', 'student_transport.student_id', '=', 'u.id')
                ->leftJoin('students as s', 'u.id', '=', 's.user_id')
                ->leftJoin('transport_routes as r', 'student_transport.route_id', '=', 'r.id')
                ->leftJoin('vehicles as v', 'student_transport.vehicle_id', '=', 'v.id')
                ->leftJoin('route_stops as ps', 'student_transport.pickup_stop_id', '=', 'ps.id')
                ->leftJoin('route_stops as ds', 'student_transport.drop_stop_id', '=', 'ds.id')
                ->leftJoin('branches as b', 'student_transport.branch_id', '=', 'b.id')
                ->select(
                    'student_transport.*',
                    DB::raw('COALESCE(student_transport.annual_fee, student_transport.monthly_fee, 0) as annual_fee'),
                    DB::raw("CONCAT(u.first_name, ' ', u.last_name) as student_name"),
                    'u.email as student_email',
                    's.id as student_record_id',
                    's.admission_number',
                    's.admission_number as admission_no',
                    's.roll_number',
                    's.grade',
                    's.grade as class_name',
                    's.section',
                    's.section as section_name',
                    'r.route_number',
                    'r.route_name',
                    'v.vehicle_number',
                    'ps.stop_name as pickup_stop_name',
                    'ds.stop_name as drop_stop_name',
                    'b.name as branch_name'
                );

            $this->scopeQuery($request, $query, 'student_transport');

            if ($request->filled('branch_id')) {
                $query->where('student_transport.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('route_id')) {
                $query->where('student_transport.route_id', (int) $request->route_id);
            }
            if ($request->filled('pickup_stop_id')) {
                $query->where('student_transport.pickup_stop_id', (int) $request->pickup_stop_id);
            }
            if ($request->filled('status')) {
                $query->where('student_transport.status', $request->status);
            }
            if ($request->filled('grade')) {
                $query->where('s.grade', $request->grade);
            }
            if ($request->filled('section')) {
                $query->where('s.section', $request->section);
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where(DB::raw("CONCAT(u.first_name, ' ', u.last_name)"), 'like', "{$s}%")
                        ->orWhere('s.admission_number', 'like', "{$s}%")
                        ->orWhere('r.route_name', 'like', "{$s}%")
                        ->orWhere('r.route_number', 'like', "{$s}%")
                        ->orWhere('student_transport.stop_name', 'like', "{$s}%");
                });
            }

            $sortable = ['u.first_name', 's.admission_number', 'r.route_number', 'student_transport.status', 'student_transport.monthly_fee'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'student_transport.id', 'desc');

            return $this->envelope($rows, $rows->items(), 'Transport assignments retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List transport assignments error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch transport assignments', $e);
        }
    }

    /** Single student transport assignment. */
    public function store(Request $request)
    {
        try {
            $studentId = $request->student_id;
            $userId = DB::table('users')->where('id', $studentId)->value('id')
                ?? DB::table('students')->where('id', $studentId)->value('user_id');

            if (! $userId) {
                return response()->json(['success' => false, 'message' => 'Invalid student user reference'], 422);
            }

            $route = TransportRoute::withoutTenantScope()->findOrFail($request->route_id);
            if (! $this->canAccessBranch($request, (int) $route->branch_id)) {
                return $this->forbiddenResponse();
            }

            $pickup = $request->pickup_stop_id ? RouteStop::find($request->pickup_stop_id) : null;
            $drop = $request->drop_stop_id ? RouteStop::find($request->drop_stop_id) : null;

            $stopName = $request->stop_name ?: ($pickup?->stop_name ?? ($drop?->stop_name ?? 'Default Stop'));
            $pickupTime = $request->pickup_time ?: ($pickup?->pickup_time ?? '07:30:00');
            $dropTime = $request->drop_time ?: ($drop?->drop_time ?? '16:00:00');
            $annualFee = $request->filled('annual_fee')
                ? (float) $request->annual_fee
                : ($request->filled('monthly_fee') ? (float) $request->monthly_fee : (float) $route->fare);
            $vehicleId = $request->vehicle_id ?: DB::table('vehicles')->where('route_id', $route->id)->value('id');

            $assignment = StudentTransport::withoutTenantScope()->updateOrCreate(
                ['student_id' => (int) $userId],
                [
                    'route_id' => $route->id,
                    'vehicle_id' => $vehicleId,
                    'branch_id' => $route->branch_id,
                    'school_id' => $route->school_id,
                    'stop_name' => $stopName,
                    'pickup_stop_id' => $request->pickup_stop_id ?: null,
                    'drop_stop_id' => $request->drop_stop_id ?: null,
                    'pickup_time' => $pickupTime,
                    'drop_time' => $dropTime,
                    'annual_fee' => $annualFee,
                    'monthly_fee' => $annualFee,
                    'due_date' => $request->due_date ?: null,
                    'status' => $request->input('status', 'Active'),
                ]
            );

            $this->syncService->syncAssignment($assignment, $annualFee);

            return response()->json([
                'success' => true,
                'message' => 'Student transport assigned successfully',
                'data' => $assignment->load(['route', 'vehicle', 'student']),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Assign student transport error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to assign student', $e);
        }
    }

    /** Update an existing student transport assignment. */
    public function update(Request $request, string $id)
    {
        try {
            $assignment = StudentTransport::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $assignment->branch_id)) {
                return $this->forbiddenResponse();
            }

            $pickup = $request->pickup_stop_id ? RouteStop::find($request->pickup_stop_id) : null;
            $drop = $request->drop_stop_id ? RouteStop::find($request->drop_stop_id) : null;

            $data = [];
            if ($request->filled('route_id')) {
                $data['route_id'] = (int) $request->route_id;
            }
            if ($request->has('vehicle_id')) {
                $data['vehicle_id'] = $request->vehicle_id ?: null;
            }
            if ($request->has('pickup_stop_id')) {
                $data['pickup_stop_id'] = $request->pickup_stop_id ?: null;
                if ($pickup) {
                    $data['stop_name'] = $pickup->stop_name;
                    $data['pickup_time'] = $pickup->pickup_time ?: '07:30:00';
                }
            }
            if ($request->has('drop_stop_id')) {
                $data['drop_stop_id'] = $request->drop_stop_id ?: null;
                if ($drop && ! isset($data['stop_name'])) {
                    $data['stop_name'] = $drop->stop_name;
                }
                if ($drop) {
                    $data['drop_time'] = $drop->drop_time ?: '16:00:00';
                }
            }
            if ($request->filled('stop_name')) {
                $data['stop_name'] = $request->stop_name;
            }
            if ($request->filled('pickup_time')) {
                $data['pickup_time'] = $request->pickup_time;
            }
            if ($request->filled('drop_time')) {
                $data['drop_time'] = $request->drop_time;
            }
            if ($request->has('annual_fee') || $request->has('monthly_fee')) {
                $fee = $request->has('annual_fee') ? (float) $request->annual_fee : (float) $request->monthly_fee;
                $data['annual_fee'] = $fee;
                $data['monthly_fee'] = $fee;
            }
            if ($request->has('due_date')) {
                $data['due_date'] = $request->due_date ?: null;
            }
            if ($request->filled('status')) {
                $data['status'] = $request->status;
            }

            $assignment->update($data);
            $assignment = $assignment->fresh(['route', 'vehicle', 'student']);
            $this->syncService->syncAssignment($assignment);

            return response()->json([
                'success' => true,
                'message' => 'Assignment updated successfully',
                'data' => $assignment,
            ]);
        } catch (\Exception $e) {
            Log::error('Update student transport assignment error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to update assignment', $e);
        }
    }

    /** Remove student transport assignment. */
    public function destroy(Request $request, string $id)
    {
        try {
            $assignment = StudentTransport::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $assignment->branch_id)) {
                return $this->forbiddenResponse();
            }

            $studentUserId = (int) $assignment->student_id;
            $assignment->delete();
            $this->syncService->handleUnassignment($studentUserId);

            return response()->json([
                'success' => true,
                'message' => 'Student transport assignment removed successfully',
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to remove assignment', $e);
        }
    }

    /** Bulk assign a list of students to a route and pickup/drop stops. */
    public function bulkAssign(BulkAssignTransportRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $route = TransportRoute::withoutTenantScope()->findOrFail($request->route_id);
                if (! $this->canAccessBranch($request, (int) $route->branch_id)) {
                    return $this->forbiddenResponse();
                }

                $pickup = $request->pickup_stop_id ? RouteStop::find($request->pickup_stop_id) : null;
                $drop = $request->drop_stop_id ? RouteStop::find($request->drop_stop_id) : null;

                $stopName = $request->stop_name ?: ($pickup?->stop_name ?? ($drop?->stop_name ?? 'Default Stop'));
                $pickupTime = $request->pickup_time ?: ($pickup?->pickup_time ?? '07:30:00');
                $dropTime = $request->drop_time ?: ($drop?->drop_time ?? '16:00:00');
                $annualFee = $request->filled('annual_fee')
                    ? (float) $request->annual_fee
                    : ($request->filled('monthly_fee') ? (float) $request->monthly_fee : (float) $route->fare);
                $status = $request->input('status', 'Active');
                $vehicleId = $request->vehicle_id ?: DB::table('vehicles')->where('route_id', $route->id)->value('id');

                $assignedCount = 0;
                $studentIds = (array) $request->student_ids;

                foreach ($studentIds as $studentUserId) {
                    $userId = DB::table('users')->where('id', $studentUserId)->value('id')
                        ?? DB::table('students')->where('id', $studentUserId)->value('user_id');

                    if (! $userId) {
                        continue;
                    }

                    $assignment = StudentTransport::withoutTenantScope()->updateOrCreate(
                        ['student_id' => (int) $userId],
                        [
                            'route_id' => $route->id,
                            'vehicle_id' => $vehicleId,
                            'branch_id' => $route->branch_id,
                            'school_id' => $route->school_id,
                            'stop_name' => $stopName,
                            'pickup_stop_id' => $request->pickup_stop_id ?: null,
                            'drop_stop_id' => $request->drop_stop_id ?: null,
                            'pickup_time' => $pickupTime,
                            'drop_time' => $dropTime,
                            'annual_fee' => $annualFee,
                            'monthly_fee' => $annualFee,
                            'due_date' => $request->due_date ?: null,
                            'status' => $status,
                        ]
                    );

                    $this->syncService->syncAssignment($assignment, $annualFee);

                    $assignedCount++;
                }

                return response()->json([
                    'success' => true,
                    'message' => "Successfully assigned {$assignedCount} student(s) to route {$route->route_number}",
                    'assigned_count' => $assignedCount,
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Bulk assign transport error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to bulk assign students', $e);
        }
    }

    private function scopeQuery(Request $request, $query, string $table): void
    {
        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $query->where(function ($q) use ($schoolId, $table) {
                $q->where("$table.school_id", $schoolId)->orWhereNull("$table.school_id");
            });
        }
        $branches = $this->getAccessibleBranchIds($request);
        if ($branches !== 'all') {
            ! empty($branches) ? $query->whereIn("$table.branch_id", $branches) : $query->whereRaw('1 = 0');
        }
    }

    private function envelope($paginator, array $data, string $message)
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
