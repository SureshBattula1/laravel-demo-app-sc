<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStudentTransportRequest;
use App\Models\RouteStop;
use App\Models\StudentTransport;
use App\Models\TransportRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentTransportController extends Controller
{
    /** Assign a student to a route (+ pickup/drop stops). */
    public function store(StoreStudentTransportRequest $request)
    {
        try {
            $route = TransportRoute::withoutTenantScope()->findOrFail($request->route_id);
            if (!$this->canAccessBranch($request, (int) $route->branch_id)) {
                return $this->forbiddenResponse();
            }

            [$stopName, $pickupTime, $dropTime] = $this->resolveStopDetails($request);

            $assignment = StudentTransport::create([
                'student_id' => (int) $request->student_id,
                'route_id' => $route->id,
                'vehicle_id' => $request->vehicle_id ?: null,
                'branch_id' => $route->branch_id,
                'school_id' => $route->school_id,
                'stop_name' => $stopName,
                'pickup_stop_id' => $request->pickup_stop_id ?: null,
                'drop_stop_id' => $request->drop_stop_id ?: null,
                'pickup_time' => $pickupTime,
                'drop_time' => $dropTime,
                'monthly_fee' => $request->monthly_fee,
                'status' => $request->status ?? 'Active',
            ]);

            return response()->json(['success' => true, 'message' => 'Student assigned to transport', 'data' => $assignment], 201);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        } catch (\Exception $e) {
            Log::error('Assign student transport error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to assign student', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $assignment = StudentTransport::withoutTenantScope()->with(['route', 'vehicle', 'student'])->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $assignment->branch_id)) {
                return $this->forbiddenResponse();
            }
            return response()->json(['success' => true, 'data' => $assignment]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        }
    }

    public function update(StoreStudentTransportRequest $request, string $id)
    {
        try {
            $assignment = StudentTransport::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $assignment->branch_id)) {
                return $this->forbiddenResponse();
            }
            $data = $request->only(['vehicle_id', 'pickup_stop_id', 'drop_stop_id', 'monthly_fee', 'status', 'stop_name', 'pickup_time', 'drop_time']);
            $assignment->update(array_filter($data, fn ($v) => $v !== null));
            return response()->json(['success' => true, 'message' => 'Assignment updated', 'data' => $assignment->fresh(['route', 'vehicle', 'student'])]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Exception $e) {
            Log::error('Update student transport error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to update assignment', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $assignment = StudentTransport::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $assignment->branch_id)) {
                return $this->forbiddenResponse();
            }
            $assignment->delete();
            return response()->json(['success' => true, 'message' => 'Assignment removed']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Assignment not found'], 404);
        } catch (\Exception $e) {
            Log::error('Delete student transport error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to remove assignment', $e);
        }
    }

    /** Fill the NOT NULL stop_name/times from the chosen stops or the request. */
    private function resolveStopDetails(Request $request): array
    {
        $pickup = $request->pickup_stop_id ? RouteStop::find($request->pickup_stop_id) : null;
        $drop = $request->drop_stop_id ? RouteStop::find($request->drop_stop_id) : null;

        $stopName = $request->stop_name ?: ($pickup?->stop_name ?? 'N/A');
        $pickupTime = $request->pickup_time ?: ($pickup?->pickup_time ?? '00:00:00');
        $dropTime = $request->drop_time ?: ($drop?->drop_time ?? '00:00:00');

        return [$stopName, $pickupTime, $dropTime];
    }
}
