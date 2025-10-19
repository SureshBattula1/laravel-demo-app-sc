<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Transaction;
use App\Models\SalaryPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

class TransactionController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Get all transactions with server-side pagination and sorting
     */
    public function index(Request $request)
    {
        try {
            $query = Transaction::with(['category', 'branch', 'createdBy', 'approvedBy']);

            // Filters
            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('financial_year')) {
                $query->where('financial_year', $request->financial_year);
            }

            if ($request->has('from_date')) {
                $query->where('transaction_date', '>=', $request->from_date);
            }

            if ($request->has('to_date')) {
                $query->where('transaction_date', '<=', $request->to_date);
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('transaction_number', 'like', '%' . $search . '%')
                      ->orWhere('party_name', 'like', '%' . $search . '%')
                      ->orWhere('description', 'like', '%' . $search . '%');
                });
            }

            // Define sortable columns
            $sortableColumns = [
                'id',
                'transaction_number',
                'transaction_date',
                'type',
                'category_id',
                'amount',
                'status',
                'payment_method',
                'financial_year',
                'created_at'
            ];

            // Apply pagination and sorting (default: 25 per page, sorted by transaction_date desc)
            $transactions = $this->paginateAndSort($query, $request, $sortableColumns, 'transaction_date', 'desc');

            // Return standardized paginated response
            return response()->json([
                'success' => true,
                'message' => 'Transactions retrieved successfully',
                'data' => $transactions->items(),
                'meta' => [
                    'current_page' => $transactions->currentPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total(),
                    'last_page' => $transactions->lastPage(),
                    'from' => $transactions->firstItem(),
                    'to' => $transactions->lastItem(),
                    'has_more_pages' => $transactions->hasMorePages()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get transactions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch transactions',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Create new transaction
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'category_id' => 'required|exists:account_categories,id',
                'transaction_date' => 'required|date',
                'type' => 'required|in:Income,Expense',
                'amount' => 'required|numeric|min:0',
                'description' => 'required|string',
                'payment_method' => 'required|in:Cash,Check,Card,Bank Transfer,UPI,Other',
                'party_name' => 'nullable|string|max:255',
                'party_type' => 'nullable|string|max:100',
                'payment_reference' => 'nullable|string|max:255',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            // Generate transaction number
            $transactionNumber = $this->generateTransactionNumber($request->type);

            // Get financial year and month
            $date = new \DateTime($request->transaction_date);
            $financialYear = $this->getFinancialYear($date);
            $month = $date->format('F');

            $transaction = Transaction::create([
                'branch_id' => $request->branch_id,
                'category_id' => $request->category_id,
                'transaction_number' => $transactionNumber,
                'transaction_date' => $request->transaction_date,
                'type' => $request->type,
                'amount' => $request->amount,
                'party_name' => $request->party_name ? strip_tags($request->party_name) : null,
                'party_type' => $request->party_type,
                'party_id' => $request->party_id ?? null,
                'payment_method' => $request->payment_method,
                'payment_reference' => $request->payment_reference ? strip_tags($request->payment_reference) : null,
                'bank_name' => $request->bank_name ? strip_tags($request->bank_name) : null,
                'description' => strip_tags($request->description),
                'notes' => $request->notes ? strip_tags($request->notes) : null,
                'status' => 'Pending',
                'created_by' => Auth::id(),
                'financial_year' => $financialYear,
                'month' => $month
            ]);

            // If salary payment, create salary record
            if ($request->has('is_salary') && $request->is_salary && $request->has('salary_details')) {
                $this->createSalaryPayment($transaction->id, $request->salary_details);
            }

            DB::commit();

            Log::info('Transaction created', ['transaction_id' => $transaction->id]);

            return response()->json([
                'success' => true,
                'message' => 'Transaction created successfully',
                'data' => $transaction->load(['category', 'branch', 'createdBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create transaction error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create transaction',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single transaction
     */
    public function show($id)
    {
        try {
            $transaction = Transaction::with([
                'category', 
                'branch', 
                'createdBy', 
                'approvedBy', 
                'salaryPayment.employee'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transaction
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }
    }

    /**
     * Update transaction
     */
    public function update(Request $request, $id)
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Only pending transactions can be edited
            if ($transaction->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending transactions can be edited'
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'category_id' => 'sometimes|exists:account_categories,id',
                'transaction_date' => 'sometimes|date',
                'amount' => 'sometimes|numeric|min:0',
                'description' => 'sometimes|string',
                'payment_method' => 'sometimes|in:Cash,Check,Card,Bank Transfer,UPI,Other',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            DB::beginTransaction();

            $updateData = $request->only([
                'category_id', 'transaction_date', 'amount', 'party_name', 
                'payment_method', 'payment_reference', 'bank_name', 
                'description', 'notes'
            ]);

            // Sanitize strings
            foreach (['party_name', 'payment_reference', 'bank_name', 'description', 'notes'] as $field) {
                if (isset($updateData[$field])) {
                    $updateData[$field] = strip_tags($updateData[$field]);
                }
            }

            $transaction->update($updateData);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction updated successfully',
                'data' => $transaction->fresh(['category', 'branch', 'createdBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update transaction error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update transaction'
            ], 500);
        }
    }

    /**
     * Approve transaction
     */
    public function approve($id)
    {
        try {
            DB::beginTransaction();

            $transaction = Transaction::findOrFail($id);

            $transaction->update([
                'status' => 'Approved',
                'approved_by' => Auth::id(),
                'approved_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Transaction approved successfully',
                'data' => $transaction->fresh(['category', 'approvedBy'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve transaction'
            ], 500);
        }
    }

    /**
     * Reject transaction
     */
    public function reject($id)
    {
        try {
            $transaction = Transaction::findOrFail($id);
            $transaction->update(['status' => 'Rejected']);

            return response()->json([
                'success' => true,
                'message' => 'Transaction rejected'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject transaction'
            ], 500);
        }
    }

    /**
     * Delete transaction
     */
    public function destroy($id)
    {
        try {
            $transaction = Transaction::findOrFail($id);

            if ($transaction->status === 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete approved transactions'
                ], 400);
            }

            $transaction->delete();

            return response()->json([
                'success' => true,
                'message' => 'Transaction deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete transaction'
            ], 500);
        }
    }

    /**
     * Generate unique transaction number
     */
    private function generateTransactionNumber($type): string
    {
        $prefix = $type === 'Income' ? 'INC' : 'EXP';
        $date = date('Ymd');
        $random = strtoupper(substr(md5(uniqid()), 0, 4));
        
        return "{$prefix}-{$date}-{$random}";
    }

    /**
     * Get financial year from date
     */
    private function getFinancialYear(\DateTime $date): string
    {
        $month = (int)$date->format('n');
        $year = (int)$date->format('Y');
        
        if ($month < 4) {
            return ($year - 1) . '-' . $year;
        } else {
            return $year . '-' . ($year + 1);
        }
    }

    /**
     * Create salary payment record
     */
    private function createSalaryPayment($transactionId, $salaryDetails): void
    {
        SalaryPayment::create([
            'transaction_id' => $transactionId,
            'employee_id' => $salaryDetails['employee_id'],
            'employee_type' => $salaryDetails['employee_type'] ?? 'Teacher',
            'basic_salary' => $salaryDetails['basic_salary'],
            'allowances' => $salaryDetails['allowances'] ?? 0,
            'deductions' => $salaryDetails['deductions'] ?? 0,
            'net_salary' => $salaryDetails['net_salary'],
            'salary_month' => $salaryDetails['salary_month'],
            'salary_year' => $salaryDetails['salary_year'],
            'remarks' => $salaryDetails['remarks'] ?? null
        ]);
    }
}

