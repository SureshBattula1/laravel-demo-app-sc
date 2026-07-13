<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\TransportDriver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportDriverController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            $query = DB::table('transport_drivers')
                ->leftJoin('branches', 'transport_drivers.branch_id', '=', 'branches.id')
                ->whereNull('transport_drivers.deleted_at')
                ->select('transport_drivers.*', 'branches.name as branch_name', 'branches.code as branch_code');

            $this->scopeQuery($request, $query, 'transport_drivers');

            if ($request->filled('branch_id')) {
                $query->where('transport_drivers.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transport_drivers.name', 'like', "{$s}%")
                        ->orWhere('transport_drivers.phone', 'like', "{$s}%")
                        ->orWhere('transport_drivers.license_number', 'like', "{$s}%");
                });
            }

            $sortable = ['transport_drivers.name', 'transport_drivers.license_expiry', 'transport_drivers.created_at', 'branches.name'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_drivers.name', 'asc');
            $data = collect($rows->items())->map(fn ($d) => $this->shape($d))->toArray();

            return $this->envelope($rows, $data, 'Drivers retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List drivers error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to fetch drivers', $e);
        }
    }

    public function store(StoreDriverRequest $request)
    {
        try {
            $branchId = (int) $request->branch_id;
            if (!$this->canAccessBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }
            $driver = TransportDriver::create([
                'branch_id' => $branchId,
                'school_id' => DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request),
                'name' => strip_tags($request->name),
                'phone' => $request->phone,
                'license_number' => $request->license_number,
                'license_expiry' => $request->license_expiry,
                'address' => $request->address ? strip_tags($request->address) : null,
                'is_active' => $request->boolean('is_active', true),
            ]);
            return response()->json(['success' => true, 'message' => 'Driver created successfully', 'data' => $driver->load('branch')], 201);
        } catch (\Exception $e) {
            Log::error('Create driver error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to create driver', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $driver = TransportDriver::withoutTenantScope()->with('branch')->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $driver->branch_id)) {
                return $this->forbiddenResponse();
            }
            return response()->json(['success' => true, 'data' => $driver]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Driver not found'], 404);
        }
    }

    public function update(StoreDriverRequest $request, string $id)
    {
        try {
            $driver = TransportDriver::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $driver->branch_id)) {
                return $this->forbiddenResponse();
            }
            $driver->update($request->only(['name', 'phone', 'license_number', 'license_expiry', 'address', 'is_active']));
            return response()->json(['success' => true, 'message' => 'Driver updated successfully', 'data' => $driver->fresh('branch')]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Driver not found'], 404);
        } catch (\Exception $e) {
            Log::error('Update driver error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to update driver', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $driver = TransportDriver::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $driver->branch_id)) {
                return $this->forbiddenResponse();
            }
            $driver->delete();
            return response()->json(['success' => true, 'message' => 'Driver deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Driver not found'], 404);
        } catch (\Exception $e) {
            Log::error('Delete driver error', ['error' => $e->getMessage()]);
            return $this->serverErrorResponse('Failed to delete driver', $e);
        }
    }

    /** School + accessible-branch scoping for a DB::table query. */
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

    private function shape($d): array
    {
        $d->branch = ['id' => $d->branch_id, 'name' => $d->branch_name, 'code' => $d->branch_code];
        unset($d->branch_name, $d->branch_code);
        return (array) $d;
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
