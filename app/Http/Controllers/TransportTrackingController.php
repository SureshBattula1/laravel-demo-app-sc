<?php

namespace App\Http\Controllers;

use App\Models\TransportRoute;
use App\Models\TransportTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransportTrackingController extends Controller
{
    /** Driver or Mobile GPS update for an active trip. */
    public function updateGps(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($tripId);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            // Sanitize input if strings are passed
            $lat = $request->latitude;
            $lng = $request->longitude;
            if (is_string($lat)) {
                // Remove any unexpected extra characters if string concatenation accidentally occurred
                if (preg_match('/^(-?\d+\.?\d*)/', trim($lat), $m)) {
                    $request->merge(['latitude' => (float) $m[1]]);
                }
            }
            if (is_string($lng)) {
                if (preg_match('/^(-?\d+\.?\d*)/', trim($lng), $m)) {
                    $request->merge(['longitude' => (float) $m[1]]);
                }
            }

            $validated = $request->validate([
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'speed' => 'nullable|numeric|min:0',
                'current_stop_id' => 'nullable',
            ]);

            $trip->update([
                'current_latitude' => (float) $validated['latitude'],
                'current_longitude' => (float) $validated['longitude'],
                'current_speed' => isset($validated['speed']) ? (float) $validated['speed'] : null,
                'current_stop_id' => ! empty($validated['current_stop_id']) ? $validated['current_stop_id'] : $trip->current_stop_id,
                'status' => in_array($trip->status, ['Scheduled', 'Started'], true) ? 'In Progress' : $trip->status,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'GPS telemetry updated',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update GPS: validation error',
                'error' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update GPS', $e);
        }
    }

    /** Live tracking telemetry and ETA for a specific trip. */
    public function getLiveTracking(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()
                ->with(['route', 'vehicle', 'driver', 'currentStop'])
                ->findOrFail($tripId);

            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $stops = $this->resolveRouteStops($trip->route);

            // Resolve next stop
            $currentStopSeq = $trip->currentStop?->sequence_no ?? 0;
            $nextStop = $stops->first(fn ($s) => ($s->sequence_no ?? 0) > $currentStopSeq) ?? $stops->last();

            // Approximate ETA: estimate 2.5 minutes per KM remaining or 4 minutes per stop
            $remainingStopsCount = $stops->filter(fn ($s) => ($s->sequence_no ?? 0) >= ($nextStop?->sequence_no ?? 0))->count();
            $estimatedMinutes = max(3, $remainingStopsCount * 4);
            $etaTimestamp = now()->addMinutes($estimatedMinutes)->format('h:i A');

            return response()->json([
                'success' => true,
                'data' => [
                    'trip_id' => $trip->id,
                    'trip_code' => $trip->trip_code,
                    'status' => $trip->status,
                    'route_name' => $trip->route?->route_name,
                    'route_number' => $trip->route?->route_number,
                    'vehicle_number' => $trip->vehicle?->vehicle_number,
                    'driver_name' => $trip->driver?->name,
                    'driver_phone' => $trip->driver?->phone,
                    'current_latitude' => $trip->current_latitude,
                    'current_longitude' => $trip->current_longitude,
                    'current_speed' => $trip->current_speed,
                    'current_stop' => $trip->currentStop?->stop_name ?? 'School / Depot',
                    'next_stop' => $nextStop?->stop_name ?? 'School',
                    'remaining_stops_count' => $remainingStopsCount,
                    'eta_minutes' => $estimatedMinutes,
                    'expected_time' => $etaTimestamp,
                    'stops' => $stops,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }
    }

    /** Student/Parent live tracking for their assigned bus run. */
    public function getStudentTracking(Request $request, string $studentUserId)
    {
        try {
            // Find active student transport assignment (support student_id matching users.id or students.user_id)
            $userId = DB::table('users')->where('id', $studentUserId)->value('id')
                ?? DB::table('students')->where('id', $studentUserId)->value('user_id');

            $assignment = DB::table('student_transport')
                ->where('student_id', $userId ?: $studentUserId)
                ->where('status', 'Active')
                ->first();

            if (! $assignment) {
                return response()->json(['success' => false, 'message' => 'No active transport assignment for student'], 404);
            }

            // Find running trip for this route today (including standing daily trips)
            $today = date('Y-m-d');
            $activeTrip = TransportTrip::withoutTenantScope()
                ->with(['route', 'vehicle', 'driver', 'currentStop'])
                ->where('route_id', $assignment->route_id)
                ->where(function ($q) use ($today) {
                    $q->where('trip_date', $today)->orWhereNull('trip_date');
                })
                ->whereIn('status', ['Started', 'In Progress'])
                ->latest('id')
                ->first();

            if (! $activeTrip) {
                // Return scheduled/idle info
                $scheduledTrip = TransportTrip::withoutTenantScope()
                    ->with(['vehicle', 'driver'])
                    ->where('route_id', $assignment->route_id)
                    ->where(function ($q) use ($today) {
                        $q->where('trip_date', $today)->orWhereNull('trip_date');
                    })
                    ->latest('id')
                    ->first();

                return response()->json([
                    'success' => true,
                    'data' => [
                        'status' => $scheduledTrip ? $scheduledTrip->status : 'No Active Trip',
                        'vehicle_number' => $scheduledTrip?->vehicle?->vehicle_number ?? 'Assigned Bus',
                        'driver_name' => $scheduledTrip?->driver?->name ?? 'Assigned Driver',
                        'driver_phone' => $scheduledTrip?->driver?->phone,
                        'stop_name' => $assignment->stop_name,
                        'pickup_time' => $assignment->pickup_time,
                        'drop_time' => $assignment->drop_time,
                        'is_running' => false,
                    ],
                ]);
            }

            // Active trip running — compute ETA to student's stop
            $stops = $this->resolveRouteStops($activeTrip->route);
            $studentStop = $stops->first(fn ($s) => (isset($s->id) && $s->id == $assignment->pickup_stop_id) || ($s->stop_name ?? '') == $assignment->stop_name);
            $currentStopSeq = $activeTrip->currentStop?->sequence_no ?? 0;
            $studentStopSeq = $studentStop?->sequence_no ?? ($currentStopSeq + 1);

            $stopsAway = max(0, $studentStopSeq - $currentStopSeq);
            $etaMinutes = max(2, $stopsAway * 4);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'On Route',
                    'is_running' => true,
                    'trip_code' => $activeTrip->trip_code,
                    'vehicle_number' => $activeTrip->vehicle?->vehicle_number,
                    'driver_name' => $activeTrip->driver?->name,
                    'driver_phone' => $activeTrip->driver?->phone,
                    'current_stop' => $activeTrip->currentStop?->stop_name ?? 'In Transit',
                    'next_stop' => $studentStop?->stop_name ?? $assignment->stop_name,
                    'eta_minutes' => $etaMinutes,
                    'expected_time' => now()->addMinutes($etaMinutes)->format('h:i A'),
                    'student_stop_name' => $assignment->stop_name,
                    'pickup_time' => $assignment->pickup_time,
                    'current_latitude' => $activeTrip->current_latitude,
                    'current_longitude' => $activeTrip->current_longitude,
                ],
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to fetch student tracking', $e);
        }
    }

    /** Helper to safely resolve route stops collection from DB or model attribute. */
    private function resolveRouteStops(?TransportRoute $route)
    {
        if (! $route) {
            return collect();
        }

        // Query route_stops relation table first
        $dbStops = DB::table('route_stops')->where('route_id', $route->id)->orderBy('sequence_no')->get();
        if ($dbStops->isNotEmpty()) {
            return $dbStops;
        }

        // Fallback to JSON array attribute if present
        $rawStops = is_array($route->stops) ? $route->stops : (is_string($route->stops) ? json_decode($route->stops, true) : []);
        $seq = 1;

        return collect($rawStops)->map(function ($s) use (&$seq) {
            if (is_string($s)) {
                $item = (object) ['stop_name' => $s, 'sequence_no' => $seq++];
            } else {
                $item = (object) $s;
                if (! isset($item->sequence_no)) {
                    $item->sequence_no = $seq++;
                }
            }

            return $item;
        })->sortBy('sequence_no')->values();
    }
}
