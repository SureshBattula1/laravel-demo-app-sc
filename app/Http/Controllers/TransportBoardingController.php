<?php

namespace App\Http\Controllers;

use App\Models\TransportTrip;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportBoardingController extends Controller
{
    /** Passenger roster for a trip with boarding and drop status. */
    public function getRoster(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->with(['route', 'vehicle', 'driver'])->findOrFail($tripId);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            // Auto-sync any active students assigned to this route who do not have a boarding log yet
            $existingStudentIds = DB::table('trip_boarding_logs')
                ->where('trip_id', $trip->id)
                ->pluck('student_id')
                ->toArray();

            $assignedStudents = DB::table('student_transport')
                ->where('route_id', $trip->route_id)
                ->where('status', 'Active')
                ->whereNotIn('student_id', $existingStudentIds)
                ->get();

            if ($assignedStudents->isNotEmpty()) {
                $newLogs = [];
                $now = now();
                foreach ($assignedStudents as $st) {
                    $newLogs[] = [
                        'trip_id' => $trip->id,
                        'student_id' => $st->student_id,
                        'route_id' => $trip->route_id,
                        'pickup_stop_id' => $st->pickup_stop_id,
                        'drop_stop_id' => $st->drop_stop_id,
                        'boarding_status' => 'Not Boarded',
                        'drop_status' => 'Pending',
                        'verification_method' => 'Manual',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
                DB::table('trip_boarding_logs')->insert($newLogs);
            }

            $isDropTrip = in_array(strtolower($trip->trip_type ?? ''), ['drop', 'afternoon'], true);

            $rawLogs = DB::table('trip_boarding_logs')
                ->leftJoin('users as u', 'trip_boarding_logs.student_id', '=', 'u.id')
                ->leftJoin('students as s', 'u.id', '=', 's.user_id')
                ->leftJoin('route_stops as ps', 'trip_boarding_logs.pickup_stop_id', '=', 'ps.id')
                ->leftJoin('route_stops as ds', 'trip_boarding_logs.drop_stop_id', '=', 'ds.id')
                ->where('trip_boarding_logs.trip_id', $trip->id)
                ->select(
                    'trip_boarding_logs.*',
                    DB::raw("CONCAT(COALESCE(u.first_name, ''), ' ', COALESCE(u.last_name, '')) as student_name"),
                    'u.email as student_email',
                    's.admission_number',
                    's.admission_number as admission_no',
                    's.grade',
                    's.grade as class_name',
                    's.section',
                    's.section as section_name',
                    'ps.stop_name as pickup_stop_name',
                    'ps.pickup_time as scheduled_pickup_time',
                    'ds.stop_name as drop_stop_name',
                    'ds.drop_time as scheduled_drop_time'
                )
                ->orderBy('trip_boarding_logs.pickup_stop_id', 'asc')
                ->orderBy('u.first_name', 'asc')
                ->get();

            $logs = $rawLogs->map(function ($l) use ($isDropTrip) {
                $item = (object) (array) $l;
                if ($isDropTrip) {
                    $item->status = ($item->drop_status === 'Dropped') ? 'Dropped' : 'Pending';
                } else {
                    $item->status = ($item->boarding_status === 'Boarded')
                        ? 'Boarded'
                        : (($item->boarding_status === 'Absent') ? 'Absent' : 'Pending');
                }

                return $item;
            });

            $summary = [
                'total' => $logs->count(),
                'boarded' => $logs->where('status', 'Boarded')->count(),
                'not_boarded' => $logs->where('status', '!=', 'Boarded')->count(),
                'dropped' => $logs->where('status', 'Dropped')->count(),
                'pending_drop' => $logs->where('status', '!=', 'Dropped')->count(),
            ];

            // Resolve route stops with per-stop passenger counts
            $stops = DB::table('route_stops')
                ->where('route_id', $trip->route_id)
                ->orderBy('sequence_no', 'asc')
                ->get();

            if ($stops->isEmpty() && $trip->route) {
                $rawStops = is_array($trip->route->stops) ? $trip->route->stops : (is_string($trip->route->stops) ? json_decode($trip->route->stops, true) : []);
                $seq = 1;
                $stops = collect($rawStops)->map(function ($s) use (&$seq) {
                    if (is_string($s)) {
                        return (object) ['id' => $seq, 'stop_name' => $s, 'sequence_no' => $seq++];
                    }
                    $item = (object) $s;
                    if (! isset($item->id)) {
                        $item->id = $seq;
                    }
                    if (! isset($item->sequence_no)) {
                        $item->sequence_no = $seq++;
                    }

                    return $item;
                });
            }

            $enrichedStops = $stops->map(function ($stop) use ($logs, $isDropTrip, $trip) {
                $stopObj = (object) (array) $stop;
                if ($isDropTrip) {
                    $matching = $logs->filter(fn ($l) => ($l->drop_stop_id && $l->drop_stop_id == $stopObj->id) || ($l->drop_stop_name && $l->drop_stop_name == $stopObj->stop_name));
                    $stopObj->total_students = $matching->count();
                    $stopObj->verified_students = $matching->where('status', 'Dropped')->count();
                    $stopObj->pending_students = $matching->where('status', '!=', 'Dropped')->count();
                } else {
                    $matching = $logs->filter(fn ($l) => ($l->pickup_stop_id && $l->pickup_stop_id == $stopObj->id) || ($l->pickup_stop_name && $l->pickup_stop_name == $stopObj->stop_name));
                    $stopObj->total_students = $matching->count();
                    $stopObj->verified_students = $matching->where('status', 'Boarded')->count();
                    $stopObj->pending_students = $matching->where('status', '!=', 'Boarded')->count();
                }
                $stopObj->is_current = ($trip->current_stop_id && $trip->current_stop_id == $stopObj->id);

                return $stopObj;
            });

            return response()->json([
                'success' => true,
                'data' => $logs,
                'summary' => $summary,
                'stops' => $enrichedStops,
                'current_stop_id' => $trip->current_stop_id,
                'trip' => [
                    'id' => $trip->id,
                    'trip_code' => $trip->trip_code,
                    'trip_date' => $trip->trip_date,
                    'trip_type' => $trip->trip_type,
                    'status' => $trip->status,
                    'current_stop_id' => $trip->current_stop_id,
                    'route_id' => $trip->route_id,
                    'route_name' => $trip->route?->route_name,
                    'route_number' => $trip->route?->route_number,
                    'vehicle_number' => $trip->vehicle?->vehicle_number,
                    'driver_name' => $trip->driver?->name,
                    'driver_phone' => $trip->driver?->phone,
                    'scheduled_start_time' => $trip->scheduled_start_time,
                ],
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Trip not found'], 404);
        }
    }

    /** Mark individual or multiple students as Boarded during trip. */
    public function markBoarded(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($tripId);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $studentIds = (array) $request->input('student_ids', [$request->input('student_id')]);
            $studentIds = array_filter($studentIds);

            if (empty($studentIds)) {
                return response()->json(['success' => false, 'message' => 'No student specified'], 422);
            }

            $method = $request->input('verification_method', 'Manual');
            $markerId = $request->user()?->id;

            DB::table('trip_boarding_logs')
                ->where('trip_id', $trip->id)
                ->whereIn('student_id', $studentIds)
                ->update([
                    'boarding_status' => 'Boarded',
                    'boarded_at' => now(),
                    'verification_method' => $method,
                    'marked_by' => $markerId,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Student(s) marked as Boarded',
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to record boarding', $e);
        }
    }

    /** Toggle status back to Not Boarded / Absent if marked in error. */
    public function unmarkBoarded(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($tripId);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $studentId = $request->input('student_id');
            $status = $request->input('status', 'Not Boarded');

            DB::table('trip_boarding_logs')
                ->where('trip_id', $trip->id)
                ->where('student_id', $studentId)
                ->update([
                    'boarding_status' => $status,
                    'boarded_at' => null,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Boarding status updated',
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update boarding', $e);
        }
    }

    /** Mark student as Dropped at their destination stop. */
    public function markDropped(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($tripId);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $studentIds = (array) $request->input('student_ids', [$request->input('student_id')]);
            $studentIds = array_filter($studentIds);

            if (empty($studentIds)) {
                return response()->json(['success' => false, 'message' => 'No student specified'], 422);
            }

            $markerId = $request->user()?->id;

            DB::table('trip_boarding_logs')
                ->where('trip_id', $trip->id)
                ->whereIn('student_id', $studentIds)
                ->update([
                    'drop_status' => 'Dropped',
                    'dropped_at' => now(),
                    'marked_by' => $markerId,
                    'updated_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'Student(s) marked as Dropped',
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to record drop', $e);
        }
    }

    /** Mark stop as reached, optionally auto-verifying passenger boarding or drops. */
    public function reachStop(Request $request, string $tripId)
    {
        try {
            $trip = TransportTrip::withoutTenantScope()->findOrFail($tripId);
            if (! $this->canAccessBranch($request, (int) $trip->branch_id)) {
                return $this->forbiddenResponse();
            }

            $stopId = $request->input('stop_id');
            $autoVerify = $request->boolean('auto_verify', true);
            $markerId = $request->user()?->id;

            // If stopId is not provided, advance to the next stop in sequence
            if (! $stopId) {
                $stops = DB::table('route_stops')->where('route_id', $trip->route_id)->orderBy('sequence_no', 'asc')->get();
                if ($stops->isNotEmpty()) {
                    $currentSeq = 0;
                    if ($trip->current_stop_id) {
                        $current = $stops->firstWhere('id', $trip->current_stop_id);
                        $currentSeq = $current?->sequence_no ?? 0;
                    }
                    $next = $stops->first(fn ($s) => $s->sequence_no > $currentSeq) ?? $stops->first();
                    $stopId = $next?->id;
                }
            }

            if ($stopId) {
                $trip->current_stop_id = $stopId;
                if ($trip->status === 'Scheduled') {
                    $trip->status = 'In Progress';
                    $trip->started_at = $trip->started_at ?: now();
                }
                $trip->save();
            }

            $affectedCount = 0;
            if ($autoVerify && $stopId) {
                $isDropTrip = in_array(strtolower($trip->trip_type ?? ''), ['drop', 'afternoon'], true);
                if ($isDropTrip) {
                    $affectedCount = DB::table('trip_boarding_logs')
                        ->where('trip_id', $trip->id)
                        ->where('drop_stop_id', $stopId)
                        ->where('drop_status', 'Pending')
                        ->update([
                            'drop_status' => 'Dropped',
                            'dropped_at' => now(),
                            'marked_by' => $markerId,
                            'updated_at' => now(),
                        ]);
                } else {
                    $affectedCount = DB::table('trip_boarding_logs')
                        ->where('trip_id', $trip->id)
                        ->where('pickup_stop_id', $stopId)
                        ->where('boarding_status', '!=', 'Boarded')
                        ->update([
                            'boarding_status' => 'Boarded',
                            'boarded_at' => now(),
                            'marked_by' => $markerId,
                            'updated_at' => now(),
                        ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Stop updated'.($affectedCount > 0 ? " — {$affectedCount} passenger(s) marked." : '.'),
                'current_stop_id' => $trip->current_stop_id,
                'affected_count' => $affectedCount,
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update stop reach status', $e);
        }
    }
}
