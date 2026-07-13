<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRouteRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\RouteStop;
use App\Models\TransportRoute;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportRouteController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            $query = DB::table('transport_routes')
                ->leftJoin('branches', 'transport_routes.branch_id', '=', 'branches.id')
                ->whereNull('transport_routes.deleted_at')
                ->select(
                    'transport_routes.*',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                    DB::raw('(select count(*) from route_stops where route_stops.route_id = transport_routes.id) as stops_count')
                );

            $this->scopeQuery($request, $query, 'transport_routes');

            if ($request->filled('branch_id')) {
                $query->where('transport_routes.branch_id', (int) $request->branch_id);
            }
            if ($request->has('is_active')) {
                $query->where('transport_routes.is_active', $request->boolean('is_active'));
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transport_routes.route_name', 'like', "{$s}%")
                        ->orWhere('transport_routes.route_number', 'like', "{$s}%");
                });
            }

            $sortable = ['transport_routes.route_number', 'transport_routes.route_name', 'transport_routes.fare', 'transport_routes.created_at', 'branches.name'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_routes.route_number', 'asc');
            $data = collect($rows->items())->map(fn ($r) => $this->shape($r))->toArray();

            return $this->envelope($rows, $data, 'Routes retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List routes error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to fetch routes', $e);
        }
    }

    public function store(StoreRouteRequest $request)
    {
        try {
            return DB::transaction(function () use ($request) {
                $branchId = (int) $request->branch_id;
                if (!$this->canAccessBranch($request, $branchId)) {
                    return $this->forbiddenResponse();
                }
                $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);
                $stops = $request->input('stops', []);

                $route = TransportRoute::create([
                    'branch_id' => $branchId,
                    'school_id' => $schoolId,
                    'route_number' => $request->route_number,
                    'route_name' => strip_tags($request->route_name),
                    'description' => $request->description ? strip_tags($request->description) : null,
                    'stops' => collect($stops)->pluck('stop_name')->values()->all(), // denormalized names (back-compat)
                    'distance' => $request->distance,
                    'estimated_time' => $request->estimated_time,
                    'fare' => $request->fare,
                    'is_active' => $request->boolean('is_active', true),
                ]);

                $this->syncStops($route, $stops, $branchId, $schoolId);

                return response()->json([
                    'success' => true,
                    'message' => 'Route created successfully',
                    'data' => $route->load(['branch', 'stops']),
                ], 201);
            });
        } catch (\Exception $e) {
            Log::error('Create route error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to create route', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $route = TransportRoute::withoutTenantScope()->with(['branch', 'stops'])->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $route->branch_id)) {
                return $this->forbiddenResponse();
            }
            return response()->json(['success' => true, 'data' => $route]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }
    }

    public function update(StoreRouteRequest $request, string $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $route = TransportRoute::withoutTenantScope()->findOrFail($id);
                if (!$this->canAccessBranch($request, (int) $route->branch_id)) {
                    return $this->forbiddenResponse();
                }
                $data = $request->only(['route_number', 'route_name', 'description', 'distance', 'estimated_time', 'fare', 'is_active']);

                // Replace stops when provided.
                if ($request->has('stops')) {
                    $stops = $request->input('stops', []);
                    $data['stops'] = collect($stops)->pluck('stop_name')->values()->all();
                    $route->update($data);
                    $route->stops()->delete();
                    $this->syncStops($route, $stops, (int) $route->branch_id, $route->school_id);
                } else {
                    $route->update($data);
                }

                return response()->json(['success' => true, 'message' => 'Route updated successfully', 'data' => $route->fresh(['branch', 'stops'])]);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        } catch (\Exception $e) {
            Log::error('Update route error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to update route', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $route = TransportRoute::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $route->branch_id)) {
                return $this->forbiddenResponse();
            }
            $route->delete();
            return response()->json(['success' => true, 'message' => 'Route deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        } catch (\Exception $e) {
            Log::error('Delete route error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to delete route', $e);
        }
    }

    /** Ordered stops for a route. */
    public function getRouteStops(Request $request, string $id)
    {
        try {
            $route = TransportRoute::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $route->branch_id)) {
                return $this->forbiddenResponse();
            }
            return response()->json(['success' => true, 'data' => $route->stops()->get()]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }
    }

    /** Students assigned to a route (with member name + stop names). */
    public function getRouteStudents(Request $request, string $id)
    {
        try {
            $route = TransportRoute::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $route->branch_id)) {
                return $this->forbiddenResponse();
            }
            $students = DB::table('student_transport')
                ->leftJoin('users as u', 'student_transport.student_id', '=', 'u.id')
                ->leftJoin('route_stops as ps', 'student_transport.pickup_stop_id', '=', 'ps.id')
                ->leftJoin('route_stops as ds', 'student_transport.drop_stop_id', '=', 'ds.id')
                ->where('student_transport.route_id', $route->id)
                ->select(
                    'student_transport.*',
                    DB::raw("CONCAT(u.first_name, ' ', u.last_name) as student_name"),
                    'ps.stop_name as pickup_stop_name',
                    'ds.stop_name as drop_stop_name'
                )
                ->orderBy('student_transport.id', 'desc')
                ->get();
            return response()->json(['success' => true, 'data' => $students]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Route not found'], 404);
        }
    }

    /** Persist ordered stops for a route. */
    private function syncStops(TransportRoute $route, array $stops, int $branchId, $schoolId): void
    {
        $seq = 1;
        foreach ($stops as $s) {
            if (empty($s['stop_name'])) {
                continue;
            }
            RouteStop::create([
                'route_id' => $route->id,
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'sequence_no' => $s['sequence_no'] ?? $seq,
                'stop_name' => strip_tags($s['stop_name']),
                'pickup_time' => $s['pickup_time'] ?? null,
                'drop_time' => $s['drop_time'] ?? null,
                'latitude' => $s['latitude'] ?? null,
                'longitude' => $s['longitude'] ?? null,
                'geofence_radius' => $s['geofence_radius'] ?? null,
            ]);
            $seq++;
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
            !empty($branches) ? $query->whereIn("$table.branch_id", $branches) : $query->whereRaw('1 = 0');
        }
    }

    private function shape($r): array
    {
        $r->branch = ['id' => $r->branch_id, 'name' => $r->branch_name, 'code' => $r->branch_code];
        unset($r->branch_name, $r->branch_code);
        return (array) $r;
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
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
                'has_more_pages' => $paginator->hasMorePages(),
            ],
        ]);
    }
}
