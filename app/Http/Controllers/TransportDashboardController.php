<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportDashboardController extends Controller
{
    public function index(Request $request)
    {
        try {
            $date = $request->input('date', date('Y-m-d'));
            $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
            $schoolId = $this->getCurrentSchoolId($request);
            $accessibleBranches = $this->getAccessibleBranchIds($request);

            // Base queries helper
            $applyScope = function ($query, string $table) use ($schoolId, $accessibleBranches, $branchId) {
                if ($schoolId) {
                    $query->where(function ($q) use ($schoolId, $table) {
                        $q->where("$table.school_id", $schoolId)->orWhereNull("$table.school_id");
                    });
                }
                if ($branchId) {
                    $query->where("$table.branch_id", $branchId);
                } elseif ($accessibleBranches !== 'all') {
                    ! empty($accessibleBranches) ? $query->whereIn("$table.branch_id", $accessibleBranches) : $query->whereRaw('1 = 0');
                }

                return $query;
            };

            // 1. Students Count (Unique students currently assigned active transport)
            $studentsCount = $applyScope(DB::table('student_transport')->where('status', 'Active'), 'student_transport')
                ->distinct('student_id')
                ->count('student_id');

            // 2. Active Routes Count
            $routesCount = $applyScope(DB::table('transport_routes')->whereNull('deleted_at')->where('is_active', 1), 'transport_routes')
                ->count();

            // 3. Active Vehicles Count
            $vehiclesCount = $applyScope(DB::table('vehicles')->whereNull('deleted_at')->where('status', 'Active'), 'vehicles')
                ->count();

            // 4. Active Drivers Count
            $driversCount = $applyScope(DB::table('transport_drivers')->whereNull('deleted_at')->where('is_active', 1), 'transport_drivers')
                ->count();

            // 5. Today's Trips Count
            $todayTripsQuery = DB::table('transport_trips')
                ->leftJoin('transport_routes', 'transport_trips.route_id', '=', 'transport_routes.id')
                ->leftJoin('vehicles', 'transport_trips.vehicle_id', '=', 'vehicles.id')
                ->leftJoin('transport_drivers', 'transport_trips.transport_driver_id', '=', 'transport_drivers.id')
                ->whereNull('transport_trips.deleted_at')
                ->where(function ($q) use ($date) {
                    $q->where('transport_trips.trip_date', $date)
                        ->orWhereNull('transport_trips.trip_date');
                });

            $applyScope($todayTripsQuery, 'transport_trips');

            $todayTripsCount = (clone $todayTripsQuery)->count();

            // Today's Trips live list
            $todayTrips = $todayTripsQuery
                ->select(
                    'transport_trips.id',
                    'transport_trips.trip_code',
                    'transport_trips.trip_date',
                    'transport_trips.trip_type',
                    'transport_trips.status',
                    'transport_trips.started_at',
                    'transport_trips.completed_at',
                    'transport_routes.route_number',
                    'transport_routes.route_name',
                    'vehicles.vehicle_number',
                    'vehicles.vehicle_type',
                    'transport_drivers.name as driver_name',
                    'transport_drivers.phone as driver_phone',
                    DB::raw('(select count(*) from trip_boarding_logs where trip_boarding_logs.trip_id = transport_trips.id and trip_boarding_logs.boarding_status = "Boarded") as boarded_count'),
                    DB::raw('(select count(*) from trip_boarding_logs where trip_boarding_logs.trip_id = transport_trips.id and trip_boarding_logs.drop_status = "Dropped") as dropped_count'),
                    DB::raw('(select count(*) from student_transport where student_transport.route_id = transport_trips.route_id and student_transport.status = "Active") as total_riders')
                )
                ->orderByRaw("CASE 
                    WHEN transport_trips.status IN ('Started', 'In Progress') THEN 1 
                    WHEN transport_trips.status = 'Scheduled' THEN 2 
                    ELSE 3 END")
                ->orderBy('transport_trips.id', 'desc')
                ->limit(50)
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'kpis' => [
                        'students' => $studentsCount,
                        'routes' => $routesCount,
                        'vehicles' => $vehiclesCount,
                        'drivers' => $driversCount,
                        'today_trips' => $todayTripsCount,
                    ],
                    'today_trips' => $todayTrips,
                ],
                'message' => 'Transport dashboard metrics retrieved',
            ]);
        } catch (\Exception $e) {
            Log::error('Transport dashboard error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch transport dashboard', $e);
        }
    }
}
