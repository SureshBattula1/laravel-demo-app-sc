<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\TransportStop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportStopController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            $query = DB::table('transport_stops_master')
                ->leftJoin('branches', 'transport_stops_master.branch_id', '=', 'branches.id')
                ->whereNull('transport_stops_master.deleted_at')
                ->select(
                    'transport_stops_master.*',
                    'branches.name as branch_name',
                    'branches.code as branch_code'
                );

            $this->scopeQuery($request, $query, 'transport_stops_master');

            if ($request->filled('branch_id')) {
                $query->where('transport_stops_master.branch_id', (int) $request->branch_id);
            }
            if ($request->has('is_active')) {
                $query->where('transport_stops_master.is_active', $request->boolean('is_active'));
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transport_stops_master.stop_name', 'like', "{$s}%")
                        ->orWhere('transport_stops_master.location', 'like', "{$s}%")
                        ->orWhere('transport_stops_master.landmark', 'like', "{$s}%");
                });
            }

            $sortable = ['transport_stops_master.stop_name', 'transport_stops_master.default_pickup_time', 'branches.name'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_stops_master.stop_name', 'asc');

            return $this->envelope($rows, $rows->items(), 'Stops retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List stops error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch stops', $e);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'stop_name' => 'required|string|max:255',
                'location' => 'nullable|string|max:255',
                'landmark' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'geofence_radius' => 'nullable|integer|min:10|max:5000',
                'default_pickup_time' => 'nullable',
                'default_drop_time' => 'nullable',
                'is_active' => 'boolean',
            ]);

            // Resolve branch_id with auto-fallback
            $branchId = $request->filled('branch_id')
                ? (int) $request->branch_id
                : ($request->user()?->branch_id
                    ?? (is_array($this->getAccessibleBranchIds($request)) ? ($this->getAccessibleBranchIds($request)[0] ?? null) : null)
                    ?? DB::table('branches')->where('school_id', $this->getCurrentSchoolId($request))->value('id')
                    ?? DB::table('branches')->value('id'));

            if (! $branchId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No accessible branch found. Please specify branch_id.',
                ], 422);
            }

            if (! $this->canAccessBranch($request, (int) $branchId)) {
                return $this->forbiddenResponse();
            }

            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);

            $stop = TransportStop::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'stop_name' => strip_tags($request->stop_name),
                'location' => $request->location ? strip_tags($request->location) : null,
                'landmark' => $request->landmark ? strip_tags($request->landmark) : null,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'geofence_radius' => (int) $request->input('geofence_radius', 50),
                'default_pickup_time' => $request->default_pickup_time,
                'default_drop_time' => $request->default_drop_time,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stop created successfully',
                'data' => $stop,
            ], 201);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create stop', $e);
        }
    }

    public function update(Request $request, string $id)
    {
        try {
            $stop = TransportStop::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $stop->branch_id)) {
                return $this->forbiddenResponse();
            }

            $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'stop_name' => 'sometimes|required|string|max:255',
                'location' => 'nullable|string|max:255',
                'landmark' => 'nullable|string|max:255',
                'latitude' => 'nullable|numeric|between:-90,90',
                'longitude' => 'nullable|numeric|between:-180,180',
                'geofence_radius' => 'nullable|integer|min:10|max:5000',
                'default_pickup_time' => 'nullable',
                'default_drop_time' => 'nullable',
                'is_active' => 'boolean',
            ]);

            $stop->update($request->only([
                'branch_id', 'stop_name', 'location', 'landmark', 'latitude', 'longitude',
                'geofence_radius', 'default_pickup_time', 'default_drop_time', 'is_active',
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Stop updated successfully',
                'data' => $stop,
            ]);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to update stop', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $stop = TransportStop::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $stop->branch_id)) {
                return $this->forbiddenResponse();
            }

            $stop->delete();

            return response()->json(['success' => true, 'message' => 'Stop deleted successfully']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete stop', $e);
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
