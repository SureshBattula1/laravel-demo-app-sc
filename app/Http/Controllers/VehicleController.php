<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreVehicleRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class VehicleController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            $query = DB::table('vehicles')
                ->leftJoin('branches', 'vehicles.branch_id', '=', 'branches.id')
                ->leftJoin('transport_drivers', 'vehicles.transport_driver_id', '=', 'transport_drivers.id')
                ->leftJoin('transport_routes', 'vehicles.route_id', '=', 'transport_routes.id')
                ->whereNull('vehicles.deleted_at')
                ->select(
                    'vehicles.*',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                    'transport_drivers.name as driver_name',
                    'transport_routes.route_name as route_name'
                );

            $this->scopeQuery($request, $query, 'vehicles');

            if ($request->filled('branch_id')) {
                $query->where('vehicles.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('status')) {
                $query->where('vehicles.status', $request->status);
            }
            if ($request->filled('vehicle_type')) {
                $query->where('vehicles.vehicle_type', $request->vehicle_type);
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('vehicles.vehicle_number', 'like', "{$s}%")
                        ->orWhere('vehicles.make', 'like', "{$s}%")
                        ->orWhere('vehicles.model', 'like', "{$s}%");
                });
            }

            $sortable = ['vehicles.vehicle_number', 'vehicles.vehicle_type', 'vehicles.capacity', 'vehicles.status', 'vehicles.created_at', 'branches.name'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'vehicles.vehicle_number', 'asc');
            $data = collect($rows->items())->map(fn ($v) => $this->shape($v))->toArray();

            return $this->envelope($rows, $data, 'Vehicles retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List vehicles error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to fetch vehicles', $e);
        }
    }

    public function store(StoreVehicleRequest $request)
    {
        try {
            $branchId = (int) $request->branch_id;
            if (!$this->canAccessBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }
            $vehicle = Vehicle::create([
                'branch_id' => $branchId,
                'school_id' => DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request),
                'vehicle_number' => $request->vehicle_number,
                'vehicle_type' => $request->vehicle_type,
                'make' => $request->make,
                'model' => $request->model,
                'capacity' => (int) $request->capacity,
                'insurance_expiry' => $request->insurance_expiry,
                'fitness_expiry' => $request->fitness_expiry,
                'transport_driver_id' => $request->transport_driver_id ?: null,
                'route_id' => $request->route_id ?: null,
                'status' => $request->status ?? 'Active',
            ]);
            return response()->json(['success' => true, 'message' => 'Vehicle created successfully', 'data' => $vehicle->load(['branch', 'driver', 'route'])], 201);
        } catch (\Exception $e) {
            Log::error('Create vehicle error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to create vehicle', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $vehicle = Vehicle::withoutTenantScope()->with(['branch', 'driver', 'route'])->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $vehicle->branch_id)) {
                return $this->forbiddenResponse();
            }
            return response()->json(['success' => true, 'data' => $vehicle]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found'], 404);
        }
    }

    public function update(StoreVehicleRequest $request, string $id)
    {
        try {
            $vehicle = Vehicle::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $vehicle->branch_id)) {
                return $this->forbiddenResponse();
            }
            $vehicle->update($request->only([
                'vehicle_number', 'vehicle_type', 'make', 'model', 'capacity',
                'insurance_expiry', 'fitness_expiry', 'transport_driver_id', 'route_id', 'status',
            ]));
            return response()->json(['success' => true, 'message' => 'Vehicle updated successfully', 'data' => $vehicle->fresh(['branch', 'driver', 'route'])]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found'], 404);
        } catch (\Exception $e) {
            Log::error('Update vehicle error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to update vehicle', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $vehicle = Vehicle::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $vehicle->branch_id)) {
                return $this->forbiddenResponse();
            }
            $vehicle->delete();
            return response()->json(['success' => true, 'message' => 'Vehicle deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Vehicle not found'], 404);
        } catch (\Exception $e) {
            Log::error('Delete vehicle error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to delete vehicle', $e);
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

    private function shape($v): array
    {
        $v->branch = ['id' => $v->branch_id, 'name' => $v->branch_name, 'code' => $v->branch_code];
        $v->driver = $v->transport_driver_id ? ['id' => $v->transport_driver_id, 'name' => $v->driver_name] : null;
        $v->route = $v->route_id ? ['id' => $v->route_id, 'name' => $v->route_name] : null;
        unset($v->branch_name, $v->branch_code, $v->driver_name, $v->route_name);
        return (array) $v;
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
