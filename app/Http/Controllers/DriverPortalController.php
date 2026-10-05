<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\RouteStop;
use App\Models\TransportDriver;
use App\Models\TransportRoute;
use App\Models\TransportTrip;
use App\Models\Vehicle;
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

        // Try direct user_id link first
        $driver = TransportDriver::where('user_id', $user->id)->first();
        if ($driver) {
            return $driver;
        }

        // Fallback: match by email or phone in same branch
        if ($user->email) {
            $driver = TransportDriver::where('email', $user->email)
                ->where('branch_id', $user->branch_id)
                ->first();
            if ($driver) {
                $driver->update(['user_id' => $user->id]);

                return $driver;
            }
        }

        if ($user->phone) {
            $driver = TransportDriver::where('phone', $user->phone)
                ->where('branch_id', $user->branch_id)
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
                            'name' => $user->first_name.' '.$user->last_name,
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
                        ],
                    ],
                ]);
            }

            // 1. Assigned Vehicle
            $vehicle = Vehicle::where('transport_driver_id', $driver->id)
                ->with('route')
                ->first();

            // 2. Assigned Route & Ordered Stops
            $route = null;
            $stops = [];
            if ($vehicle && $vehicle->route_id) {
                $route = TransportRoute::find($vehicle->route_id);
                if ($route) {
                    $stops = RouteStop::where('route_id', $route->id)
                        ->orderBy('sequence_no', 'asc')
                        ->get();
                }
            }

            // 3. Today's Trips
            $today = now()->toDateString();
            $todayTrips = TransportTrip::where(function ($q) use ($driver, $vehicle) {
                $q->where('transport_driver_id', $driver->id);
                if ($vehicle) {
                    $q->orWhere('vehicle_id', $vehicle->id);
                }
            })
                ->where(function ($q) use ($today) {
                    $q->whereNull('trip_date')->orWhere('trip_date', $today);
                })
                ->with(['route', 'vehicle'])
                ->orderBy('created_at', 'asc')
                ->get();

            // If no trips exist for today yet, check if there's an assigned route and create default morning/evening trips
            if ($todayTrips->isEmpty() && $route && $vehicle) {
                $pickupTrip = TransportTrip::create([
                    'trip_code' => 'TRP-'.strtoupper(uniqid()),
                    'branch_id' => $driver->branch_id,
                    'school_id' => $driver->school_id,
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
                ->first();

            $duty = [
                'clocked_in' => $attendance && $attendance->check_in_time !== null,
                'clock_in_time' => $attendance?->check_in_time ? $attendance->check_in_time->format('H:i') : null,
                'clocked_out' => $attendance && $attendance->check_out_time !== null,
                'clock_out_time' => $attendance?->check_out_time ? $attendance->check_out_time->format('H:i') : null,
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
                        'is_active' => $driver->is_active,
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
                        'route_code' => $route->route_code,
                        'start_location' => $route->start_location,
                        'end_location' => $route->end_location,
                        'total_distance_km' => $route->total_distance_km,
                        'estimated_duration_minutes' => $route->estimated_duration_minutes,
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
     * Driver shift clock-in.
     */
    public function clockIn(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();

            $attendance = Attendance::firstOrNew([
                'user_id' => $user->id,
                'attendance_date' => $today,
            ]);

            if ($attendance->exists && $attendance->check_in_time) {
                return response()->json([
                    'success' => true,
                    'message' => 'Already clocked in today at '.$attendance->check_in_time->format('H:i'),
                    'data' => $attendance,
                ]);
            }

            $attendance->branch_id = $user->branch_id;
            $attendance->school_id = $user->school_id ?? DB::table('branches')->where('id', $user->branch_id)->value('school_id');
            $attendance->user_type = 'Driver';
            $attendance->status = 'present';
            $attendance->check_in_time = now();
            $attendance->remarks = $request->input('remarks', 'Driver shift check-in');
            $attendance->marked_by = $user->id;
            $attendance->save();

            return response()->json([
                'success' => true,
                'message' => 'Duty started! Clock-in recorded at '.now()->format('H:i'),
                'data' => $attendance,
            ]);
        } catch (\Exception $e) {
            Log::error('Driver clock in error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Clock in failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Driver shift clock-out.
     */
    public function clockOut(Request $request)
    {
        try {
            $user = $request->user();
            $today = now()->toDateString();

            $attendance = Attendance::where('user_id', $user->id)
                ->whereDate('attendance_date', $today)
                ->first();

            if (! $attendance || ! $attendance->check_in_time) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot clock out: no check-in recorded for today.',
                ], 400);
            }

            $attendance->check_out_time = now();
            $hours = $attendance->check_in_time->diffInMinutes(now()) / 60.0;
            $attendance->total_hours = round($hours, 2);
            if ($request->filled('remarks')) {
                $attendance->remarks = $attendance->remarks.' | '.$request->input('remarks');
            }
            $attendance->save();

            return response()->json([
                'success' => true,
                'message' => 'Duty ended! Clock-out recorded. Total shift: '.round($hours, 1).' hrs.',
                'data' => $attendance,
            ]);
        } catch (\Exception $e) {
            Log::error('Driver clock out error', ['error' => $e->getMessage()]);

            return response()->json(['success' => false, 'message' => 'Clock out failed', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Get driver's recent attendance logs.
     */
    public function attendanceHistory(Request $request)
    {
        try {
            $user = $request->user();
            $logs = Attendance::where('user_id', $user->id)
                ->orderBy('attendance_date', 'desc')
                ->limit(30)
                ->get()
                ->map(fn ($a) => [
                    'id' => $a->id,
                    'date' => $a->attendance_date ? $a->attendance_date->format('Y-m-d') : null,
                    'status' => $a->status,
                    'check_in' => $a->check_in_time ? $a->check_in_time->format('H:i') : null,
                    'check_out' => $a->check_out_time ? $a->check_out_time->format('H:i') : null,
                    'total_hours' => $a->total_hours,
                    'remarks' => $a->remarks,
                ]);

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
