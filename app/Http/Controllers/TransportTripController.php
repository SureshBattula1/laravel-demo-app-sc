<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTripRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\TransportRoute;
use App\Models\TransportTrip;
use App\Models\TripBoardingLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportTripController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            $query = DB::table('transport_trips')
                ->leftJoin('transport_routes', 'transport_trips.route_id', '=', 'transport_routes.id')
                ->leftJoin('vehicles', 'transport_trips.vehicle_id', '=', 'vehicles.id')
                ->leftJoin('transport_drivers', 'transport_trips.transport_driver_id', '=', 'transport_drivers.id')
                ->leftJoin('branches', 'transport_trips.branch_id', '=', 'branches.id')
                ->whereNull('transport_trips.deleted_at')
                ->select(
                    'transport_trips.*',
                    'transport_routes.route_number',
                    'transport_routes.route_name',
                    'vehicles.vehicle_number',
                    'vehicles.vehicle_type',
                    'transport_drivers.name as driver_name',
                    'transport_drivers.phone as driver_phone',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                    DB::raw('(select count(*) from trip_boarding_logs where trip_boarding_logs.trip_id = transport_trips.id and trip_boarding_logs.boarding_status = "Boarded") as boarded_count'),
                    DB::raw('(select count(*) from trip_boarding_logs where trip_boarding_logs.trip_id = transport_trips.id and trip_boarding_logs.drop_status = "Dropped") as dropped_count'),
                    DB::raw('(select count(*) from student_transport where student_transport.route_id = transport_trips.route_id and student_transport.status = "Active") as total_riders')
                );

            $this->scopeQuery($request, $query, 'transport_trips');

            // If logged-in user is a Driver, restrict strictly to their own assigned trips and today/future records only
            $user = $request->user();
            if ($user && ($user->role === 'Driver' || $user->user_type === 'Driver')) {
                $driverId = DB::table('transport_drivers')
                    ->where('user_id', $user->id)
                    ->orWhere('email', $user->email)
                    ->value('id');

                if ($driverId) {
                    $query->where('transport_trips.transport_driver_id', $driverId);
                }

                // Driver role: ONLY today and future records (no past data anywhere)
                $today = now()->toDateString();
                $query->where(function ($q) use ($today) {
                    $q->whereNull('transport_trips.trip_date')
                        ->orWhere('transport_trips.trip_date', '>=', $today);
                });
            }

            // Support active_only filter (for Active Trip Run dropdowns)
            if ($request->boolean('active_only') || $request->input('status') === 'active_only') {
                $query->whereNotIn('transport_trips.status', ['Completed', 'Cancelled']);
                $today = now()->toDateString();
                $query->where(function ($q) use ($today) {
                    $q->whereNull('transport_trips.trip_date')
                        ->orWhere('transport_trips.trip_date', '>=', $today);
                });
            }

            if ($request->filled('branch_id')) {
                $query->where('transport_trips.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('route_id')) {
                $query->where('transport_trips.route_id', (int) $request->route_id);
            }
            if ($request->filled('status') && $request->input('status') !== 'active_only') {
                $query->where('transport_trips.status', $request->status);
            }
            if ($request->filled('trip_type')) {
                $query->where('transport_trips.trip_type', $request->trip_type);
            }
            if ($request->filled('date')) {
                $d = $request->date;
                $query->where(function ($q) use ($d) {
                    $q->where('transport_trips.trip_date', $d)
                        ->orWhereNull('transport_trips.trip_date');
                });
            }
            if ($request->filled('from_date')) {
                $query->where('transport_trips.trip_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->where('transport_trips.trip_date', '<=', $request->to_date);
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transport_trips.trip_code', 'like', "{$s}%")
                        ->orWhere('transport_routes.route_name', 'like', "{$s}%")
                        ->orWhere('transport_routes.route_number', 'like', "{$s}%")
                        ->orWhere('vehicles.vehicle_number', 'like', "{$s}%")
                        ->orWhere('transport_drivers.name', 'like', "{$s}%");
                });
            }

            $sortable = [
                'transport_trips.trip_date',
                'transport_trips.trip_code',
                'transport_trips.status',
                'transport_routes.route_number',
                'branches.name',
            ];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_trips.trip_date', 'desc');
            $data = collect($rows->items())->map(fn ($r) => $this->shape($r))->toArray();

            return $this->envelope($rows, $data, 'Trips retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List trips error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch trips', $e);
        }
    }

    public function store(StoreTripRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $branchId = (int) $request->branch_id;
                if (! $this->canAccessBranch($request, $branchId)) {
                    return $this->forbiddenResponse();
                }

                $route = TransportRoute::withoutTenantScope()->findOrFail($request->route_id);
                $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);

                // Auto-resolve vehicle & driver from route if not explicitly passed
                $vehicleId = $request->vehicle_id;
                if (! $vehicleId) {
                    $vehicle = DB::table('vehicles')->where('route_id', $route->id)->where('status', 'Active')->first();
                    $vehicleId = $vehicle?->id;
                }

                $driverId = $request->transport_driver_id;
                if (! $driverId && $vehicleId) {
                    $driverId = DB::table('vehicles')->where('id', $vehicleId)->value('transport_driver_id');
                }

                $dateStr = $request->trip_date ? str_replace('-', '', $request->trip_date) : 'DAILY';
                $countToday = DB::table('transport_trips')->count() + 1;
                $tripCode = 'TRP-'.$dateStr.'-'.str_pad($countToday, 3, '0', STR_PAD_LEFT);

                $trip = TransportTrip::create([
                    'trip_code' => $tripCode,
                    'branch_id' => $branchId,
                    'school_id' => $schoolId,
                    'route_id' => $route->id,
                    'vehicle_id' => $vehicleId,
                    'transport_driver_id' => $driverId,
                    'trip_date' => $request->trip_date ?: null,
                    'trip_type' => $request->trip_type ?? 'Pickup',
                    'status' => 'Scheduled',
                    'notes' => $request->notes,
                ]);

                // Pre-populate student passenger boarding logs for this route
                $students = DB::table('student_transport')
                    ->where('route_id', $route->id)
                    ->where('status', 'Active')
                    ->get();

                foreach ($students as $st) {
                    TripBoardingLog::create([
                        'trip_id' => $trip->id,
                        'student_id' => $st->student_id,
                        'route_id' => $route->id,
                        'pickup_stop_id' => $st->pickup_stop_id,
                        'drop_stop_id' => $st->drop_stop_id,
                        'boarding_status' => 'Not Boarded',
                        'drop_status' => 'Pending',
                    ]);
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Trip scheduled successfully',
                    'data' => $trip->load(['route', 'vehicle', 'driver', 'branch']),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Create trip error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to create trip', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()
                ->with(['route.stops', 'vehicle', 'driver', 'branch', 'currentStop'])
                ->findOrFail($id);

            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $boardingSummary = [
                'total_riders' => DB::table('trip_boarding_logs')->where('trip_id', $trip->id)->count(),
                'boarded_count' => DB::table('trip_boarding_logs')->where('trip_id', $trip->id)->where('boarding_status', 'Boarded')->count(),
                'dropped_count' => DB::table('trip_boarding_logs')->where('trip_id', $trip->id)->where('drop_status', 'Dropped')->count(),
            ];

            return response()->json([
                'success' => true,
                'data' => $trip,
                'boarding_summary' => $boardingSummary,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }
    }

    public function startTrip(Request $request, string $id)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $trip->update([
                'status' => 'Started',
                'started_at' => now(),
                'completed_at' => null,
                'odometer_start' => $request->input('odometer_start', $trip->odometer_start),
            ]);

            // If it is a daily recurring trip being started/restarted, refresh boarding roster
            if (is_null($trip->trip_date)) {
                DB::table('trip_boarding_logs')
                    ->where('trip_id', $trip->id)
                    ->update([
                        'boarding_status' => 'Not Boarded',
                        'boarded_at' => null,
                        'drop_status' => 'Pending',
                        'dropped_at' => null,
                    ]);

                $existingStudentIds = DB::table('trip_boarding_logs')
                    ->where('trip_id', $trip->id)
                    ->pluck('student_id')
                    ->toArray();

                $newStudents = DB::table('student_transport')
                    ->where('route_id', $trip->route_id)
                    ->where('status', 'Active')
                    ->whereNotIn('student_id', $existingStudentIds)
                    ->get();

                foreach ($newStudents as $st) {
                    TripBoardingLog::create([
                        'trip_id' => $trip->id,
                        'student_id' => $st->student_id,
                        'route_id' => $trip->route_id,
                        'pickup_stop_id' => $st->pickup_stop_id,
                        'drop_stop_id' => $st->drop_stop_id,
                        'boarding_status' => 'Not Boarded',
                        'drop_status' => 'Pending',
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => "Trip {$trip->trip_code} has started",
                'data' => $trip->fresh(['route', 'vehicle', 'driver']),
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to start trip', $e);
        }
    }

    public function completeTrip(Request $request, string $id)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $trip->update([
                'status' => 'Completed',
                'completed_at' => now(),
                'odometer_end' => $request->input('odometer_end', $trip->odometer_end),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Trip {$trip->trip_code} marked as completed",
                'data' => $trip->fresh(['route', 'vehicle', 'driver']),
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to complete trip', $e);
        }
    }

    public function changeDriver(Request $request, string $id)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $request->validate(['transport_driver_id' => 'required|exists:transport_drivers,id']);
            $trip->update(['transport_driver_id' => $request->transport_driver_id]);

            return response()->json([
                'success' => true,
                'message' => 'Trip driver reassigned successfully',
                'data' => $trip->fresh(['driver']),
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to change driver', $e);
        }
    }

    public function changeVehicle(Request $request, string $id)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $request->validate(['vehicle_id' => 'required|exists:vehicles,id']);
            $trip->update(['vehicle_id' => $request->vehicle_id]);

            return response()->json([
                'success' => true,
                'message' => 'Trip vehicle reassigned successfully',
                'data' => $trip->fresh(['vehicle']),
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to change vehicle', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $trip->delete();

            return response()->json(['success' => true, 'message' => 'Trip deleted successfully']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete trip', $e);
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

    private function shape($t): array
    {
        $t->branch = ['id' => $t->branch_id, 'name' => $t->branch_name ?? null, 'code' => $t->branch_code ?? null];
        $t->driver = $t->transport_driver_id ? ['id' => $t->transport_driver_id, 'name' => $t->driver_name ?? null, 'phone' => $t->driver_phone ?? null] : null;
        $t->vehicle = $t->vehicle_id ? ['id' => $t->vehicle_id, 'number' => $t->vehicle_number ?? null, 'type' => $t->vehicle_type ?? null] : null;
        $t->route = $t->route_id ? ['id' => $t->route_id, 'number' => $t->route_number ?? null, 'name' => $t->route_name ?? null] : null;

        return (array) $t;
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
