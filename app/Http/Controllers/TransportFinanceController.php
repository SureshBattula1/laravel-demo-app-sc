<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreExpenseRequest;
use App\Http\Requests\StoreFuelEntryRequest;
use App\Http\Requests\StoreMaintenanceRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\TransportExpense;
use App\Models\TransportFuelEntry;
use App\Models\TransportMaintenanceLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransportFinanceController extends Controller
{
    use PaginatesAndSorts;

    // ==========================================
    // 1. EXPENSES
    // ==========================================

    public function getExpenses(Request $request)
    {
        try {
            $query = DB::table('transport_expenses')
                ->leftJoin('vehicles', 'transport_expenses.vehicle_id', '=', 'vehicles.id')
                ->leftJoin('branches', 'transport_expenses.branch_id', '=', 'branches.id')
                ->whereNull('transport_expenses.deleted_at')
                ->select(
                    'transport_expenses.*',
                    'vehicles.vehicle_number',
                    'vehicles.vehicle_type',
                    'branches.name as branch_name'
                );

            $this->scopeQuery($request, $query, 'transport_expenses');

            if ($request->filled('branch_id')) {
                $query->where('transport_expenses.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('vehicle_id')) {
                $query->where('transport_expenses.vehicle_id', (int) $request->vehicle_id);
            }
            if ($request->filled('category')) {
                $query->where('transport_expenses.category', $request->category);
            }
            if ($request->filled('from_date')) {
                $query->where('transport_expenses.expense_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->where('transport_expenses.expense_date', '<=', $request->to_date);
            }
            if ($request->filled('search')) {
                $s = strip_tags($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transport_expenses.description', 'like', "{$s}%")
                        ->orWhere('transport_expenses.reference_no', 'like', "{$s}%")
                        ->orWhere('vehicles.vehicle_number', 'like', "{$s}%");
                });
            }

            $sortable = ['transport_expenses.expense_date', 'transport_expenses.amount', 'transport_expenses.category', 'vehicles.vehicle_number'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_expenses.expense_date', 'desc');

            return $this->envelope($rows, $rows->items(), 'Expenses retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List expenses error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch expenses', $e);
        }
    }

    public function storeExpense(StoreExpenseRequest $request)
    {
        try {
            $branchId = (int) $request->branch_id;
            if (! $this->canAccessBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);

            $expense = TransportExpense::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'vehicle_id' => $request->vehicle_id,
                'expense_date' => $request->expense_date,
                'category' => $request->category,
                'description' => $request->description,
                'amount' => $request->amount,
                'paid_by' => $request->paid_by,
                'reference_no' => $request->reference_no,
                'attachment_url' => $request->attachment_url,
                'created_by' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Expense recorded successfully',
                'data' => $expense,
            ], 201);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create expense', $e);
        }
    }

    public function deleteExpense(Request $request, string $id)
    {
        try {
            $expense = TransportExpense::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $expense->branch_id)) {
                return $this->forbiddenResponse();
            }

            $expense->delete();

            return response()->json(['success' => true, 'message' => 'Expense deleted successfully']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete expense', $e);
        }
    }

    // ==========================================
    // 2. FUEL ENTRIES
    // ==========================================

    public function getFuelEntries(Request $request)
    {
        try {
            $query = DB::table('transport_fuel_entries')
                ->leftJoin('vehicles', 'transport_fuel_entries.vehicle_id', '=', 'vehicles.id')
                ->leftJoin('branches', 'transport_fuel_entries.branch_id', '=', 'branches.id')
                ->whereNull('transport_fuel_entries.deleted_at')
                ->select(
                    'transport_fuel_entries.*',
                    'vehicles.vehicle_number',
                    'vehicles.vehicle_type',
                    'branches.name as branch_name'
                );

            $this->scopeQuery($request, $query, 'transport_fuel_entries');

            if ($request->filled('branch_id')) {
                $query->where('transport_fuel_entries.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('vehicle_id')) {
                $query->where('transport_fuel_entries.vehicle_id', (int) $request->vehicle_id);
            }
            if ($request->filled('from_date')) {
                $query->where('transport_fuel_entries.entry_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->where('transport_fuel_entries.entry_date', '<=', $request->to_date);
            }

            $sortable = ['transport_fuel_entries.entry_date', 'transport_fuel_entries.odometer_reading', 'transport_fuel_entries.total_amount'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_fuel_entries.entry_date', 'desc');

            return $this->envelope($rows, $rows->items(), 'Fuel entries retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List fuel entries error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch fuel entries', $e);
        }
    }

    public function storeFuelEntry(StoreFuelEntryRequest $request)
    {
        try {
            $branchId = (int) $request->branch_id;
            if (! $this->canAccessBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);
            $qty = (float) $request->quantity_litres;
            $rate = (float) $request->rate_per_litre;
            $total = $request->filled('total_amount') ? (float) $request->total_amount : round($qty * $rate, 2);

            // Calculate mileage from previous fuel entry odometer reading for this vehicle
            $prevEntry = TransportFuelEntry::withoutTenantScope()
                ->where('vehicle_id', $request->vehicle_id)
                ->where('odometer_reading', '<', (int) $request->odometer_reading)
                ->latest('odometer_reading')
                ->first();

            $mileage = null;
            if ($prevEntry && $qty > 0) {
                $kmDiff = (int) $request->odometer_reading - $prevEntry->odometer_reading;
                if ($kmDiff > 0) {
                    $mileage = round($kmDiff / $qty, 2); // KM per Litre
                }
            }

            $entry = TransportFuelEntry::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'vehicle_id' => $request->vehicle_id,
                'entry_date' => $request->entry_date,
                'fuel_type' => $request->fuel_type ?? 'Diesel',
                'quantity_litres' => $qty,
                'rate_per_litre' => $rate,
                'total_amount' => $total,
                'odometer_reading' => (int) $request->odometer_reading,
                'mileage_calculated' => $mileage,
                'invoice_no' => $request->invoice_no,
                'created_by' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Fuel entry recorded successfully',
                'data' => $entry,
            ], 201);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to record fuel entry', $e);
        }
    }

    public function deleteFuelEntry(Request $request, string $id)
    {
        try {
            $entry = TransportFuelEntry::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $entry->branch_id)) {
                return $this->forbiddenResponse();
            }

            $entry->delete();

            return response()->json(['success' => true, 'message' => 'Fuel entry deleted successfully']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete fuel entry', $e);
        }
    }

    // ==========================================
    // 3. MAINTENANCE LOGS
    // ==========================================

    public function getMaintenanceLogs(Request $request)
    {
        try {
            $query = DB::table('transport_maintenance_logs')
                ->leftJoin('vehicles', 'transport_maintenance_logs.vehicle_id', '=', 'vehicles.id')
                ->leftJoin('branches', 'transport_maintenance_logs.branch_id', '=', 'branches.id')
                ->whereNull('transport_maintenance_logs.deleted_at')
                ->select(
                    'transport_maintenance_logs.*',
                    'vehicles.vehicle_number',
                    'vehicles.vehicle_type',
                    'branches.name as branch_name'
                );

            $this->scopeQuery($request, $query, 'transport_maintenance_logs');

            if ($request->filled('branch_id')) {
                $query->where('transport_maintenance_logs.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('vehicle_id')) {
                $query->where('transport_maintenance_logs.vehicle_id', (int) $request->vehicle_id);
            }
            if ($request->filled('service_type')) {
                $query->where('transport_maintenance_logs.service_type', $request->service_type);
            }

            $sortable = ['transport_maintenance_logs.service_date', 'transport_maintenance_logs.amount', 'vehicles.vehicle_number'];
            $rows = $this->paginateAndSort($query, $request, $sortable, 'transport_maintenance_logs.service_date', 'desc');

            return $this->envelope($rows, $rows->items(), 'Maintenance logs retrieved successfully');
        } catch (\Exception $e) {
            Log::error('List maintenance logs error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch maintenance logs', $e);
        }
    }

    public function storeMaintenanceLog(StoreMaintenanceRequest $request)
    {
        try {
            $branchId = (int) $request->branch_id;
            if (! $this->canAccessBranch($request, $branchId)) {
                return $this->forbiddenResponse();
            }

            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id') ?? $this->getCurrentSchoolId($request);

            $log = TransportMaintenanceLog::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'vehicle_id' => $request->vehicle_id,
                'service_date' => $request->service_date,
                'service_type' => $request->service_type ?? 'Regular Service',
                'description' => $request->description,
                'amount' => $request->amount ?? 0,
                'garage_name' => $request->garage_name,
                'next_service_date' => $request->next_service_date,
                'next_service_km' => $request->next_service_km,
                'status' => $request->status ?? 'Completed',
                'created_by' => $request->user()?->id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Maintenance service recorded successfully',
                'data' => $log,
            ], 201);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to create maintenance log', $e);
        }
    }

    public function deleteMaintenanceLog(Request $request, string $id)
    {
        try {
            $log = TransportMaintenanceLog::withoutTenantScope()->findOrFail($id);
            if (! $this->canAccessBranch($request, (int) $log->branch_id)) {
                return $this->forbiddenResponse();
            }

            $log->delete();

            return response()->json(['success' => true, 'message' => 'Maintenance log deleted successfully']);
        } catch (\Exception $e) {
            return $this->serverErrorResponse('Failed to delete maintenance log', $e);
        }
    }

    // ==========================================
    // 4. TRANSPORT FEES INTEGRATION
    // ==========================================

    public function getFeesSummary(Request $request)
    {
        try {
            $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
            $schoolId = $this->getCurrentSchoolId($request);

            // Connects to existing fee_dues table with fee_type = 'Transport'
            $duesQuery = DB::table('fee_dues')
                ->where('fee_type', 'Transport')
                ->whereNull('deleted_at');

            if ($branchId) {
                $duesQuery->whereExists(function ($q) use ($branchId) {
                    $q->select(DB::raw(1))
                        ->from('students')
                        ->whereColumn('students.id', 'fee_dues.student_id')
                        ->where('students.branch_id', $branchId);
                });
            }

            $totalBilled = (clone $duesQuery)->sum('original_amount');
            $totalCollected = (clone $duesQuery)->sum('paid_amount');
            $totalOutstanding = (clone $duesQuery)->sum('balance_amount');

            // Breakdown by student transport assignments
            $assignmentsMonthlyTotal = DB::table('student_transport')
                ->where('status', 'Active')
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->sum('monthly_fee');

            return response()->json([
                'success' => true,
                'data' => [
                    'total_billed' => (float) $totalBilled,
                    'total_collected' => (float) $totalCollected,
                    'total_outstanding' => (float) $totalOutstanding,
                    'projected_monthly_revenue' => (float) $assignmentsMonthlyTotal,
                ],
                'message' => 'Transport fees summary retrieved',
            ]);
        } catch (\Exception $e) {
            Log::error('Transport fees summary error', ['error' => $e->getMessage()]);

            return $this->serverErrorResponse('Failed to fetch transport fees summary', $e);
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
