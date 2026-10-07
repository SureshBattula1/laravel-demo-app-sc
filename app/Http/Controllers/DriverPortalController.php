<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RouteStop;
use App\Models\TransportDriver;
use App\Models\TransportRoute;
use App\Models\TransportTrip;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DriverPortalController extends Controller
{
    /**
     * Resolve the authenticated driver profile.
     */
    private function resolveDriver(Request $request): ?TransportDriver
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }

        // 1. Try direct user_id link
        $driver = TransportDriver::where('user_id', $user->id)->first();
        if ($driver) {
            return $driver;
        }

        // 2. Fallback: match by email
        if ($user->email) {
            $driver = TransportDriver::where('email', $user->email)->first();
            if ($driver) {
                $driver->update(['user_id' => $user->id]);

                return $driver;
            }
        }

        // 3. Fallback: match by phone
        if ($user->phone) {
            $driver = TransportDriver::where('phone', $user->phone)->first();
            if ($driver) {
                $driver->update(['user_id' => $user->id]);

                return $driver;
            }
        }

        // 4. Fallback: match by name in same branch
        if ($user->first_name) {
            $driver = TransportDriver::where('branch_id', $user->branch_id)
                ->where('name', 'like', $user->first_name.'%')
                ->first();
            if ($driver) {
                $driver->update(['user_id' => $user->id]);

                return $driver;
            }
        }

        return null;
    }

    /**
     * Get complete dashboard data for the authenticated driver.
     */
    public function dashboard(Request $request)
    {
        try {
            $user = $request->user();
            $driver = $this->resolveDriver($request);

            if (! $driver) {
                return response()->json([
                    'success' => true,
                    'message' => 'Driver profile not linked yet',
                    'data' => [
                        'driver' => [
                            'id' => null,
                            'name' => trim($user->first_name.' '.$user->last_name),
                            'phone' => $user->phone,
                            'email' => $user->email,
                            'license_number' => 'Pending Assignment',
                            'is_active' => true,
                        ],
                        'vehicle' => null,
                        'route' => null,
                        'stops' => [],
                        'today_trips' => [],
                        'duty' => [
                            'clocked_in' => false,
                            'clock_in_time' => null,
                            'clocked_out' => false,
                            'clock_out_time' => null,
                            'total_hours' => 0,
                            'status' => 'Not Checked In',
                        ],
                    ],
                ]);
            }

            // 1. Assigned Vehicle
            $vehicle = Vehicle::where(function ($q) use ($driver) {
                $q->where('transport_driver_id', $driver->id)
                    ->orWhere('driver_id', $driver->id);
            })
                ->with('route')
                ->first();

            // Fallback 1: check if driver has an existing trip with a vehicle
            if (! $vehicle) {
                $tripVehicleId = TransportTrip::where('transport_driver_id', $driver->id)
                    ->whereNotNull('vehicle_id')
                    ->latest()
                    ->value('vehicle_id');
                if ($tripVehicleId) {
                    $vehicle = Vehicle::with('route')->find($tripVehicleId);
                }
            }

            // Fallback 2: assign or link to an available vehicle in driver's branch
            if (! $vehicle) {
                $branchId = $driver->branch_id ?? $user->branch_id;
                $vehicle = Vehicle::where('branch_id', $branchId)
                    ->where(function ($q) {
                        $q->whereNull('transport_driver_id')
                            ->orWhereNull('driver_id');
                    })
                    ->with('route')
                    ->first();

                if (! $vehicle) {
                    $vehicle = Vehicle::where('branch_id', $branchId)->with('route')->first();
                }

                // If found, auto-link to this driver so lookups are consistent
                if ($vehicle && ! $vehicle->transport_driver_id) {
                    $vehicle->update(['transport_driver_id' => $driver->id]);
                }
            }

            // 2. Assigned Route & Ordered Stops
            $route = null;
            $stops = [];
            $routeId = $vehicle?->route_id;

            if (! $routeId) {
                $routeId = TransportTrip::where('transport_driver_id', $driver->id)
                    ->whereNotNull('route_id')
                    ->value('route_id');
            }

            if (! $routeId) {
                $branchId = $driver->branch_id ?? $user->branch_id;
                $routeId = TransportRoute::where('branch_id', $branchId)->where('is_active', true)->value('id');
            }

            if ($routeId) {
                $route = TransportRoute::find($routeId);
                if ($route) {
                    $rawStops = RouteStop::where('route_id', $route->id)
                        ->orderBy('sequence_no', 'asc')
                        ->get();

                    if ($rawStops->isNotEmpty()) {
                        $stops = $rawStops;
                    } elseif (! empty($route->stops)) {
                        $jsonStops = is_array($route->stops) ? $route->stops : json_decode($route->stops, true);
                        if (is_array($jsonStops)) {
                            $seq = 1;
                            $stops = collect($jsonStops)->map(function ($s) use ($route, &$seq) {
                                return [
                                    'id' => $seq,
                                    'route_id' => $route->id,
                                    'sequence_no' => $seq++,
                                    'stop_name' => is_array($s) ? ($s['name'] ?? $s['stop_name'] ?? 'Stop') : (string) $s,
                                    'pickup_time' => '08:00',
                                    'drop_time' => '15:30',
                                ];
                            });
                        }
                    }
                }
            }

            // 3. Today's / Active Trips
            $today = now()->toDateString();
            $todayTrips = TransportTrip::where(function ($q) use ($driver, $vehicle) {
                $q->where('transport_driver_id', $driver->id);
                if ($vehicle) {
                    $q->orWhere('vehicle_id', $vehicle->id);
                }
            })
                ->where(function ($q) use ($today) {
                    $q->whereNull('trip_date')
                        ->orWhere('trip_date', $today)
                        ->orWhereIn('status', ['Started', 'In Progress', 'Scheduled']);
                })
                ->with(['route', 'vehicle'])
                ->orderBy('created_at', 'asc')
                ->get();

            // If no trips exist for today yet, check if there's an assigned route and create a default pickup trip
            if ($todayTrips->isEmpty() && $route && $vehicle) {
                $pickupTrip = TransportTrip::create([
                    'trip_code' => 'TRP-'.strtoupper(uniqid()),
                    'branch_id' => $driver->branch_id ?? $vehicle->branch_id,
                    'school_id' => $driver->school_id ?? $vehicle->school_id,
                    'route_id' => $route->id,
                    'vehicle_id' => $vehicle->id,
                    'transport_driver_id' => $driver->id,
                    'trip_date' => $today,
                    'trip_type' => 'Pickup',
                    'status' => 'Scheduled',
                ]);
                $todayTrips = collect([$pickupTrip->load(['route', 'vehicle'])]);
            }

            // 4. Today's Duty / Attendance Status
            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', $today)
                ->latest()
                ->first();

            $checkInFormatted = null;
            if ($attendance?->check_in_time) {
                $checkInFormatted = $attendance->check_in_time instanceof Carbon
                    ? $attendance->check_in_time->format('H:i')
                    : Carbon::parse($attendance->check_in_time)->format('H:i');
            }

            $checkOutFormatted = null;
            if ($attendance?->check_out_time) {
                $checkOutFormatted = $attendance->check_out_time instanceof Carbon
                    ? $attendance->check_out_time->format('H:i')
                    : Carbon::parse($attendance->check_out_time)->format('H:i');
            }

            $duty = [
                'clocked_in' => $attendance && $attendance->check_in_time !== null,
                'clock_in_time' => $checkInFormatted,
                'clocked_out' => $attendance && $attendance->check_out_time !== null,
                'clock_out_time' => $checkOutFormatted,
                'total_hours' => $attendance?->total_hours ?? 0,
                'status' => $attendance?->status ?? 'Not Checked In',
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'driver' => [
                        'id' => $driver->id,
                        'name' => $driver->name,
                        'phone' => $driver->phone,
                        'email' => $driver->email,
                        'license_number' => $driver->license_number,
                        'license_expiry' => $driver->license_expiry,
                        'address' => $driver->address,
                        'is_active' => (bool) $driver->is_active,
                        'branch' => $driver->branch ? [
                            'id' => $driver->branch->id,
                            'name' => $driver->branch->name,
                        ] : null,
                    ],
                    'vehicle' => $vehicle ? [
                        'id' => $vehicle->id,
                        'vehicle_number' => $vehicle->vehicle_number,
                        'vehicle_type' => $vehicle->vehicle_type,
                        'capacity' => $vehicle->capacity,
                        'make' => $vehicle->make,
                        'model' => $vehicle->model,
                        'status' => $vehicle->status,
                        'insurance_expiry' => $vehicle->insurance_expiry,
                        'fitness_expiry' => $vehicle->fitness_expiry,
                    ] : null,
                    'route' => $route ? [
                        'id' => $route->id,
                        'route_name' => $route->route_name,
                        'route_number' => $route->route_number ?? null,
                        'route_code' => $route->route_number ?? null,
                        'start_location' => $route->start_location ?? null,
                        'end_location' => $route->end_location ?? null,
                        'distance' => $route->distance ?? null,
                        'total_distance_km' => $route->distance ?? null,
                        'estimated_time' => $route->estimated_time ?? null,
                        'estimated_duration_minutes' => $route->estimated_time ?? null,
                    ] : null,
                    'stops' => $stops,
                    'today_trips' => $todayTrips,
                    'duty' => $duty,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Driver dashboard error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to load driver dashboard', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get driver's current active shift and today's shift history.
     */
    public function currentShift(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();

            // Find any unclosed active shift
            $active = Attendance::where('user_id', $user->id)
                ->whereNull('check_out_time')
                ->latest('check_in_time')
                ->first();

            $activeFormatted = null;
            if ($active) {
                $checkIn = $active->check_in_time instanceof Carbon
                    ? $active->check_in_time
                    : Carbon::parse($active->check_in_time);

                $activeFormatted = [
                    'id' => $active->id,
                    'duty_type' => $active->duty_type ?? 'Duty Shift',
                    'status' => $active->status ?? 'In Progress',
                    'check_in_time' => $checkIn->toIso8601String(),
                    'check_in_time_formatted' => $checkIn->format('h:i A'),
                    'odometer_start' => (int) ($active->odometer_start ?? 0),
                    'remarks' => $active->remarks,
                ];
            }

            // Today's completed and active shifts
            $todayShifts = Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', $today)
                ->orderBy('check_in_time', 'desc')
                ->get()
                ->map(function ($s) {
                    $in = $s->check_in_time ? Carbon::parse($s->check_in_time) : null;
                    $out = $s->check_out_time ? Carbon::parse($s->check_out_time) : null;
                    $km = ($s->odometer_end && $s->odometer_start)
                        ? max(0, $s->odometer_end - $s->odometer_start)
                        : null;

                    return [
                        'id' => $s->id,
                        'duty_type' => $s->duty_type ?? 'Standard Duty',
                        'status' => ucfirst($s->status ?? 'Completed'),
                        'check_in_time' => $in ? $in->toIso8601String() : null,
                        'check_in_time_formatted' => $in ? $in->format('h:i A') : '—',
                        'check_out_time' => $out ? $out->toIso8601String() : null,
                        'check_out_time_formatted' => $out ? $out->format('h:i A') : 'Active',
                        'odometer_start' => $s->odometer_start ? (int) $s->odometer_start : null,
                        'odometer_end' => $s->odometer_end ? (int) $s->odometer_end : null,
                        'km_driven' => $km,
                        'total_hours' => round((float) ($s->total_hours ?? 0), 1),
                        'remarks' => $s->remarks,
                    ];
                });

            $totalKmToday = $todayShifts->sum('km_driven');
            $totalHoursToday = $todayShifts->sum('total_hours');

            return response()->json([
                'success' => true,
                'data' => [
                    'active_shift' => $activeFormatted,
                    'today_shifts' => $todayShifts,
                    'total_km_today' => $totalKmToday,
                    'total_hours_today' => round($totalHoursToday, 1),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Driver currentShift error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to retrieve current shift status'], 500);
        }
    }

    /**
     * Driver shift start (allows multiple shifts per day).
     */
    public function startShift(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();

            $request->validate([
                'odometer' => 'required|numeric|min:0',
                'duty_type' => 'required|string',
            ]);

            // Check if there is an unclosed shift in progress
            $activeShift = Attendance::where('user_id', $user->id)
                ->whereNull('check_out_time')
                ->latest('check_in_time')
                ->first();

            if ($activeShift) {
                $dutyName = $activeShift->duty_type ?? 'Duty Shift';
                $inTime = Carbon::parse($activeShift->check_in_time)->format('h:i A');

                return response()->json([
                    'success' => false,
                    'message' => "You already have an active shift ({$dutyName}) started at {$inTime}. Please stop it before starting another shift.",
                    'data' => $activeShift,
                ], 422);
            }

            $branchId = $user->branch_id ?? 1;
            $schoolId = $user->school_id ?? DB::table('branches')->where('id', $branchId)->value('school_id');

            $shift = Attendance::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'user_id' => $user->id,
                'user_type' => 'Driver',
                'duty_type' => $request->input('duty_type'),
                'attendance_date' => $today,
                'status' => 'In Progress',
                'check_in_time' => now(),
                'odometer_start' => (int) $request->odometer,
                'remarks' => $request->input('notes') ?? $request->input('remarks'),
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'marked_by' => $user->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Shift started! Duty: '.$shift->duty_type.' (Odometer: '.$shift->odometer_start.' km)',
                'data' => $shift,
            ]);
        } catch (\Exception $e) {
            Log::error('Driver startShift error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Shift start failed'], 500);
        }
    }

    /**
     * Backward-compatible alias for clock-in.
     */
    public function clockIn(Request $request)
    {
        return $this->startShift($request);
    }

    /**
     * Driver shift stop (confirmation with end odometer, status, notes).
     */
    public function stopShift(Request $request)
    {
        try {
            $user = $request->user();

            $request->validate([
                'odometer' => 'required|numeric|min:0',
                'status' => 'required|string|in:Completed,Pending,completed,pending',
            ]);

            $activeShift = Attendance::where('user_id', $user->id)
                ->whereNull('check_out_time')
                ->latest('check_in_time')
                ->first();

            if (! $activeShift) {
                return response()->json([
                    'success' => false,
                    'message' => 'No active shift in progress to stop.',
                ], 404);
            }

            $checkIn = $activeShift->check_in_time instanceof Carbon
                ? $activeShift->check_in_time
                : Carbon::parse($activeShift->check_in_time);

            $activeShift->check_out_time = now();
            $hours = max(0.1, round($checkIn->diffInMinutes(now()) / 60.0, 2));
            $activeShift->total_hours = $hours;
            $activeShift->odometer_end = (int) $request->odometer;

            $statusInput = ucfirst(strtolower($request->input('status', 'Completed')));
            $activeShift->status = in_array($statusInput, ['Completed', 'Pending']) ? $statusInput : 'Completed';

            $notes = $request->input('notes') ?? $request->input('remarks');
            if ($notes) {
                $activeShift->remarks = ($activeShift->remarks ? $activeShift->remarks.' | ' : '').$notes;
            }
            $activeShift->save();

            $kmDriven = max(0, ($activeShift->odometer_end ?? 0) - ($activeShift->odometer_start ?? 0));

            return response()->json([
                'success' => true,
                'message' => 'Shift ended! '.$activeShift->duty_type.' marked as '.$activeShift->status.'. ('.$kmDriven.' km driven, '.round($hours, 1).' hrs)',
                'data' => $activeShift,
            ]);
        } catch (\Exception $e) {
            Log::error('Driver stopShift error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => $e->getMessage() ?: 'Shift stop failed'], 500);
        }
    }

    /**
     * Backward-compatible alias for clock-out.
     */
    public function clockOut(Request $request)
    {
        return $this->stopShift($request);
    }

    /**
     * Get driver's recent attendance and duty shift logs.
     */
    public function attendanceHistory(Request $request)
    {
        try {
            $user = $request->user();
            $driver = $this->resolveDriver($request);
            $vehicleNumber = null;
            if ($driver) {
                $vehicleNumber = Vehicle::where('transport_driver_id', $driver->id)->value('vehicle_number');
            }

            $query = Attendance::where('user_id', $user->id);

            // Driver role: ONLY today and future records (no past data anywhere)
            if ($user && ($user->role === 'Driver' || $user->user_type === 'Driver')) {
                $query->whereDate('attendance_date', '>=', now()->toDateString());
            }

            $logs = $query->orderBy('check_in_time', 'desc')
                ->limit(60)
                ->get()
                ->map(function ($a) use ($vehicleNumber) {
                    $attDate = $a->attendance_date ? Carbon::parse($a->attendance_date) : null;
                    $km = ($a->odometer_end && $a->odometer_start)
                        ? max(0, $a->odometer_end - $a->odometer_start)
                        : null;

                    return [
                        'id' => $a->id,
                        'duty_type' => $a->duty_type ?? 'General Duty',
                        'date' => $attDate ? $attDate->format('Y-m-d') : null,
                        'formatted_date' => $attDate ? $attDate->format('M d, Y') : null,
                        'day' => $attDate ? $attDate->format('l') : null,
                        'status' => ucfirst($a->status ?? 'Completed'),
                        'check_in' => $a->check_in_time ? Carbon::parse($a->check_in_time)->format('H:i') : null,
                        'check_out' => $a->check_out_time ? Carbon::parse($a->check_out_time)->format('H:i') : ($a->check_in_time ? 'Active' : null),
                        'total_hours' => round((float) ($a->total_hours ?? 0), 1),
                        'odometer_start' => $a->odometer_start ? (int) $a->odometer_start : null,
                        'odometer_end' => $a->odometer_end ? (int) $a->odometer_end : null,
                        'km_driven' => $km,
                        'vehicle_number' => $vehicleNumber,
                        'remarks' => $a->remarks ?? 'Standard Duty',
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (\Exception $e) {
            Log::error('Driver attendance history error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to load attendance history'], 500);
        }
    }

    /**
     * Driver manually generates or initializes a trip run for their route (Pickup / Drop).
     */
    public function generateTrip(Request $request)
    {
        try {
            $user = $request->user();
            $driver = $this->resolveDriver($request);
            if (! $driver) {
                return response()->json(['success' => false, 'message' => 'Driver profile not linked'], 400);
            }

            $validated = $request->validate([
                'trip_type' => 'required|in:Pickup,Drop,Custom',
                'route_id' => 'nullable|exists:transport_routes,id',
                'vehicle_id' => 'nullable|exists:vehicles,id',
            ]);

            $vehicle = null;
            if (! empty($validated['vehicle_id'])) {
                $vehicle = Vehicle::find($validated['vehicle_id']);
            } else {
                $vehicle = Vehicle::where('transport_driver_id', $driver->id)->first()
                    ?? Vehicle::where('branch_id', $driver->branch_id)->first();
            }

            $routeId = $validated['route_id'] ?? $vehicle?->route_id;
            if (! $routeId) {
                $routeId = TransportRoute::where('branch_id', $driver->branch_id)->value('id');
            }

            if (! $routeId || ! $vehicle) {
                return response()->json(['success' => false, 'message' => 'Route or Vehicle not available to generate trip'], 422);
            }

            $today = now()->toDateString();
            $trip = TransportTrip::create([
                'trip_code' => 'TRP-'.strtoupper(uniqid()),
                'branch_id' => $driver->branch_id ?? $vehicle->branch_id,
                'school_id' => $driver->school_id ?? $vehicle->school_id,
                'route_id' => $routeId,
                'vehicle_id' => $vehicle->id,
                'transport_driver_id' => $driver->id,
                'trip_date' => $today,
                'trip_type' => $validated['trip_type'],
                'status' => 'Scheduled',
            ]);

            // Auto-populate roster from assigned students for this route
            $assignedStudents = DB::table('student_transport')
                ->where('route_id', $routeId)
                ->where('status', 'Active')
                ->get();

            $now = now();
            $newLogs = [];
            foreach ($assignedStudents as $st) {
                $newLogs[] = [
                    'trip_id' => $trip->id,
                    'student_id' => $st->student_id,
                    'route_id' => $routeId,
                    'pickup_stop_id' => $st->pickup_stop_id,
                    'drop_stop_id' => $st->drop_stop_id,
                    'boarding_status' => 'Not Boarded',
                    'drop_status' => 'Pending',
                    'verification_method' => 'Manual',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
            if (! empty($newLogs)) {
                DB::table('trip_boarding_logs')->insert($newLogs);
            }

            return response()->json([
                'success' => true,
                'message' => 'Trip created successfully',
                'data' => $trip->load(['route', 'vehicle']),
            ]);
        } catch (\Exception $e) {
            Log::error('Driver generate trip error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Failed to generate trip', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Driver updates live GPS coordinates for an active trip.
     */
    public function updateLocation(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::findOrFail($tripId);
            $trip->update([
                'current_latitude' => $request->latitude,
                'current_longitude' => $request->longitude,
                'current_speed' => $request->input('speed', 0),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Location updated',
            ]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to update location'], 500);
        }
    }
}
