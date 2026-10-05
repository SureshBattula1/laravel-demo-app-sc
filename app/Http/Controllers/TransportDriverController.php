<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDriverRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Role;
use App\Models\TransportDriver;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class TransportDriverController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        try {
            $query = DB::table('transport_drivers')
                ->leftJoin('branches', 'transport_drivers.branch_id', '=', 'branches.id')
                ->leftJoin('users', 'transport_drivers.user_id', '=', 'users.id')
                ->whereNull('transport_drivers.deleted_at')
                ->select(
                    'transport_drivers.*',
                    'branches.name as branch_name',
                    'branches.code as branch_code',
                    'users.email as user_account_email',
                    'users.is_active as user_active'
                );

            $this->scopeQuery($request, $query, 'transport_drivers');

            if ($request->filled('branch_id')) {
                $query->where('transport_drivers.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transport_drivers.name', 'like', "{$s}%")
                        ->orWhere('transport_drivers.phone', 'like', "{$s}%")
                        ->orWhere('transport_drivers.email', 'like', "{$s}%")
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
            if (! $this->canAccessBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            DB::beginTransaction();

            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);
            $rawName = trim(strip_tags($request->name));
            $email = $request->filled('email') ? filter_var($request->email, FILTER_SANITIZE_EMAIL) : null;
            $userId = null;

            // Create login user account if requested or credentials provided
            if ($email && ($request->boolean('create_account') || $request->filled('password'))) {
                $parts = explode(' ', $rawName, 2);
                $firstName = $parts[0] ?? 'Driver';
                $lastName = $parts[1] ?? 'Staff';
                $password = $request->filled('password') ? $request->password : 'Driver@123';

                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $request->phone,
                    'password' => Hash::make($password),
                    'role' => 'Driver',
                    'user_type' => 'Driver',
                    'branch_id' => $branchId,
                    'company_id' => DB::table('schools')->where('id', $schoolId)->value('company_id'),
                    'is_active' => $request->boolean('is_active', true),
                ]);

                $driverRole = Role::where('slug', 'driver')->first();
                if ($driverRole) {
                    $user->roles()->attach($driverRole->id, [
                        'is_primary' => true,
                        'branch_id' => $branchId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $userId = $user->id;
            }

            $driver = TransportDriver::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'user_id' => $userId,
                'name' => $rawName,
                'phone' => $request->phone,
                'email' => $email,
                'license_number' => $request->license_number,
                'license_expiry' => $request->license_expiry,
                'address' => $request->address ? strip_tags($request->address) : null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Driver created successfully'.($userId ? ' with login access' : ''),
                'data' => $driver->load(['branch', 'user:id,first_name,last_name,email,phone,is_active']),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create driver error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to create driver', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $driver = TransportDriver::withoutTenantScope()->with(['branch', 'user:id,first_name,last_name,email,phone,is_active'])->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $driver->branch_id)) {
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
            if (! $this->canAccessBranch($request, (int) $driver->branch_id)) {
                return $this->forbiddenResponse();
            }

            DB::beginTransaction();

            $updateFields = $request->only(['name', 'phone', 'license_number', 'license_expiry', 'address', 'is_active']);
            $email = $request->filled('email') ? filter_var($request->email, FILTER_SANITIZE_EMAIL) : null;
            if ($request->has('email')) {
                $updateFields['email'] = $email;
            }

            // Sync with User account or create if requested
            if ($driver->user_id) {
                $user = User::find($driver->user_id);
                if ($user) {
                    $rawName = trim(strip_tags($request->input('name', $driver->name)));
                    $parts = explode(' ', $rawName, 2);
                    $userUpdates = [
                        'first_name' => $parts[0] ?? $user->first_name,
                        'last_name' => $parts[1] ?? $user->last_name,
                        'is_active' => $request->boolean('is_active', $driver->is_active),
                    ];
                    if ($email) {
                        $userUpdates['email'] = $email;
                    }
                    if ($request->filled('phone')) {
                        $userUpdates['phone'] = $request->phone;
                    }
                    if ($request->filled('password')) {
                        $userUpdates['password'] = Hash::make($request->password);
                    }
                    $user->update($userUpdates);
                }
            } elseif ($email && ($request->boolean('create_account') || $request->filled('password'))) {
                $rawName = trim(strip_tags($request->input('name', $driver->name)));
                $parts = explode(' ', $rawName, 2);
                $firstName = $parts[0] ?? 'Driver';
                $lastName = $parts[1] ?? 'Staff';
                $password = $request->filled('password') ? $request->password : 'Driver@123';
                $schoolId = $driver->school_id ?? $this->getCurrentSchoolId($request);

                $user = User::create([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $email,
                    'phone' => $request->input('phone', $driver->phone),
                    'password' => Hash::make($password),
                    'role' => 'Driver',
                    'user_type' => 'Driver',
                    'branch_id' => $driver->branch_id,
                    'company_id' => DB::table('schools')->where('id', $schoolId)->value('company_id'),
                    'is_active' => $request->boolean('is_active', true),
                ]);

                $driverRole = Role::where('slug', 'driver')->first();
                if ($driverRole) {
                    $user->roles()->attach($driverRole->id, [
                        'is_primary' => true,
                        'branch_id' => $driver->branch_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $updateFields['user_id'] = $user->id;
            }

            $driver->update($updateFields);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Driver updated successfully',
                'data' => $driver->fresh(['branch', 'user:id,first_name,last_name,email,phone,is_active']),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'Driver not found'], 404);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update driver error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to update driver', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $driver = TransportDriver::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $driver->branch_id)) {
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
            ! empty($branches) ? $query->whereIn("$table.branch_id", $branches) : $query->whereRaw('1 = 0');
        }
    }

    private function shape($d): array
    {
        $d->branch = ['id' => $d->branch_id, 'name' => $d->branch_name, 'code' => $d->branch_code];
        $d->has_account = ! empty($d->user_id);
        $d->email = $d->email ?? $d->user_account_email ?? null;
        unset($d->branch_name, $d->branch_code, $d->user_account_email);

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
