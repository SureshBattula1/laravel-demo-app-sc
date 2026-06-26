<?php

namespace App\Http\Controllers;

use App\Http\Traits\PaginatesAndSorts;
use App\Models\Transaction;
use App\Models\SalaryPayment;
use App\Exports\TransactionsExport;
use App\Services\PdfExportService;
use App\Services\CsvExportService;
use App\Services\ExportService;
use Maatwebsite\Excel\Facades\Excel;
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
            // Only load relations needed for list display (category, branch) - skip createdBy/approvedBy
            $query = Transaction::with(['category:id,name', 'branch:id,name,code']);

            // 🔥 APPLY SCHOOL FILTERING - School-level isolation
            // Use qualified column names (transactions.*) - join with account_categories adds ambiguous columns
            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where('transactions.school_id', $schoolId);
            }

            // Apply branch access filtering (qualified columns for join with account_categories)
            $this->applyBranchFilter($query, $request, 'transactions.branch_id', 'transactions.school_id');

            // Filters (use transactions.* - join with account_categories adds ambiguous columns)
            if ($request->filled('branch_id')) {
                $query->where('transactions.branch_id', $request->branch_id);
            }

            if ($request->has('type')) {
                $query->where('transactions.type', $request->type);
            }

            if ($request->filled('category_id')) {
                $query->where('transactions.category_id', $request->category_id);
            }

            if ($request->filled('status')) {
                $query->where('transactions.status', $request->status);
            }

            if ($request->filled('payment_method')) {
                $query->where('transactions.payment_method', $request->payment_method);
            }

            if ($request->has('financial_year')) {
                $query->where('transactions.financial_year', $request->financial_year);
            }

            // Filter by academic year (via category) - use join for better performance than whereHas
            $academicYearId = $request->query('academic_year_id')
                ?? $request->header('X-Academic-Year-Id');
            $academicYearId = ($academicYearId !== null && $academicYearId !== '') ? (int) $academicYearId : null;
            if ($academicYearId != null) {
                $query->join('account_categories', function ($j) use ($academicYearId) {
                        $j->on('account_categories.id', '=', 'transactions.category_id')
                            ->whereNull('account_categories.deleted_at')
                            ->where(function ($q) use ($academicYearId) {
                                $q->whereNull('account_categories.academic_year_id')
                                    ->orWhere('account_categories.academic_year_id', $academicYearId);
                            });
                    })
                    ->select('transactions.*');
            }

            if ($request->filled('from_date')) {
                $query->where('transactions.transaction_date', '>=', $request->from_date);
            }
            if ($request->filled('to_date')) {
                $query->where('transactions.transaction_date', '<=', $request->to_date);
            }

            // Global text search (basic search box): tx #, party, description, payment method,
            // branch name/code, category name/code, amount (numeric or substring in string form)
            if ($request->filled('search')) {
                $search = trim(strip_tags($request->search));
                if ($search !== '') {
                    $like = '%' . addcslashes($search, '%_\\') . '%';

                    $query->where(function ($q) use ($search, $like) {
                        $q->where('transactions.transaction_number', 'like', $like)
                            ->orWhere('transactions.party_name', 'like', $like)
                            ->orWhere('transactions.description', 'like', $like)
                            ->orWhere('transactions.payment_method', 'like', $like)
                            ->orWhere('transactions.payment_reference', 'like', $like)
                            ->orWhereHas('branch', function ($bq) use ($like) {
                                $bq->where('name', 'like', $like)
                                    ->orWhere('code', 'like', $like);
                            })
                            ->orWhereExists(function ($sub) use ($like) {
                                $sub->select(DB::raw(1))
                                    ->from('account_categories')
                                    ->whereColumn('account_categories.id', 'transactions.category_id')
                                    ->whereNull('account_categories.deleted_at')
                                    ->where(function ($cq) use ($like) {
                                        $cq->where('account_categories.name', 'like', $like)
                                            ->orWhere('account_categories.code', 'like', $like);
                                    });
                            });

                        $normalized = str_replace([',', ' ', "\xc2\xa0"], '', $search);
                        if (is_numeric($normalized)) {
                            $num = filter_var($normalized, FILTER_VALIDATE_FLOAT);
                            if ($num !== false) {
                                $q->orWhereRaw('ABS(CAST(transactions.amount AS DECIMAL(15,4)) - ?) < 0.00005', [$num]);
                            }
                        }

                        $q->orWhereRaw('CAST(transactions.amount AS CHAR(64)) LIKE ?', ['%' . addcslashes($search, '%_\\') . '%']);
                    });
                }
            }

            // Define sortable columns (use qualified names when join present - avoids ambiguous column)
            $sortableColumns = [
                'id', 'transaction_number', 'transaction_date', 'type', 'category_id',
                'amount', 'status', 'payment_method', 'financial_year', 'created_at'
            ];
            $sortPrefix = $academicYearId != null ? 'transactions.' : '';

            // Apply pagination and sorting (default: 25 per page, sorted by created_at desc - newest first)
            $transactions = $this->paginateAndSort(
                $query, $request, $sortableColumns, 'created_at', 'desc', $sortPrefix
            );

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

            // Tenant guard: user must be able to manage the target branch.
            if (!$this->canManageBranch($request, (int) $request->branch_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have access to this branch'
                ], 403);
            }

            // Category must belong to the same branch (or be a global category) and match the type.
            $category = \App\Models\AccountCategory::find($request->category_id);
            if (!$category) {
                return response()->json(['success' => false, 'message' => 'Account category not found'], 404);
            }
            if ($category->branch_id !== null && (int) $category->branch_id !== (int) $request->branch_id) {
                return response()->json([
                    'success' => false,
                    'errors' => ['category_id' => ['The selected category does not belong to this branch.']]
                ], 422);
            }
            if ($category->type !== $request->type) {
                return response()->json([
                    'success' => false,
                    'errors' => ['category_id' => ["The selected category is for {$category->type}, not {$request->type}."]]
                ], 422);
            }

            DB::beginTransaction();

            // Generate transaction number
            $transactionNumber = $this->generateTransactionNumber($request->type);

            // Get financial year and month
            $date = new \DateTime($request->transaction_date);
            $financialYear = $this->getFinancialYear($date);
            $month = $date->format('F');

            $branch = \App\Models\Branch::find($request->branch_id);
            $transaction = Transaction::create([
                'branch_id' => $request->branch_id,
                'school_id' => $branch ? $branch->school_id : null,
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
    public function show(Request $request, $id)
    {
        try {
            $transaction = Transaction::with([
                'category',
                'branch',
                'createdBy',
                'approvedBy',
                'salaryPayment.employee'
            ])->findOrFail($id);

            // Tenant guard.
            if (!$this->canAccessBranch($request, (int) $transaction->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

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

            // Tenant guard.
            if (!$this->canManageBranch($request, (int) $transaction->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

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
    public function approve(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $transaction = Transaction::findOrFail($id);

            // Tenant guard.
            if (!$this->canManageBranch($request, (int) $transaction->branch_id)) {
                DB::rollBack();
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            // Only pending transactions can be approved.
            if ($transaction->status !== 'Pending') {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => "Only pending transactions can be approved (current status: {$transaction->status})."
                ], 422);
            }

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
    public function reject(Request $request, $id)
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Tenant guard.
            if (!$this->canManageBranch($request, (int) $transaction->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            // Only pending transactions can be rejected.
            if ($transaction->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => "Only pending transactions can be rejected (current status: {$transaction->status})."
                ], 422);
            }

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
     * Download transaction receipt as PDF (approved transactions only)
     */
    public function downloadReceipt(Request $request, string $id)
    {
        try {
            $transaction = Transaction::with(['category', 'branch', 'createdBy', 'approvedBy'])->findOrFail($id);

            // Tenant guard — don't leak another school's receipt PDF.
            if (!$this->canAccessBranch($request, (int) $transaction->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

            if ($transaction->status !== 'Approved') {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt is only available for approved transactions',
                ], 403);
            }

            $html = view('pdf.transaction_receipt', [
                'transaction' => $transaction,
            ])->render();

            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('a4', 'portrait');

            $filename = sprintf(
                '%s-receipt-%s.pdf',
                strtolower($transaction->type),
                str_replace([' ', '#'], ['-', ''], $transaction->transaction_number)
            );

            return $pdf->download($filename);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error generating transaction receipt PDF: ' . $e->getMessage(), [
                'transaction_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Unable to generate receipt PDF',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
            ], 500);
        }
    }

    /**
     * Delete transaction
     */
    public function destroy(Request $request, $id)
    {
        try {
            $transaction = Transaction::findOrFail($id);

            // Tenant guard.
            if (!$this->canManageBranch($request, (int) $transaction->branch_id)) {
                return response()->json(['success' => false, 'message' => 'Transaction not found'], 404);
            }

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

    /**
     * Export transactions data
     * Supports Excel, PDF, and CSV formats with filtering
     * Works for both Income and Expense transactions
     */
    public function export(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'format' => 'required|in:excel,pdf,csv',
                'type' => 'required|in:Income,Expense',
                'columns' => 'nullable|array',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            // Build query with same filters as index method
            $query = $this->buildTransactionQuery($request);

            // Get all matching records (no pagination for export)
            $transactions = $query->get();

            // Transform data for export
            $exportData = collect($transactions)->map(function($transaction) {
                $createdByName = '';
                if ($transaction->createdBy) {
                    $createdByName = $transaction->createdBy->first_name . ' ' . $transaction->createdBy->last_name;
                }

                $approvedByName = '';
                if ($transaction->approvedBy) {
                    $approvedByName = $transaction->approvedBy->first_name . ' ' . $transaction->approvedBy->last_name;
                }

                return [
                    'id' => $transaction->id,
                    'transaction_number' => $transaction->transaction_number,
                    'transaction_date' => $transaction->transaction_date,
                    'category_name' => $transaction->category->name ?? '',
                    'description' => $transaction->description,
                    'party_name' => $transaction->party_name,
                    'party_type' => $transaction->party_type,
                    'amount' => $transaction->amount,
                    'payment_method' => $transaction->payment_method,
                    'payment_reference' => $transaction->payment_reference,
                    'bank_name' => $transaction->bank_name,
                    'branch_name' => $transaction->branch->name ?? '',
                    'status' => $transaction->status,
                    'financial_year' => $transaction->financial_year,
                    'month' => $transaction->month,
                    'created_by_name' => $createdByName,
                    'approved_by_name' => $approvedByName,
                    'approved_at' => $transaction->approved_at,
                    'created_at' => $transaction->created_at,
                ];
            });

            $format = $request->format;
            $type = strtolower($request->type); // 'income' or 'expense'
            $columns = $request->columns;

            return match($format) {
                'excel' => $this->exportExcel($exportData, $type, $columns),
                'pdf' => $this->exportPdf($exportData, $type, $columns),
                'csv' => $this->exportCsv($exportData, $type, $columns),
            };

        } catch (\Exception $e) {
            Log::error('Export transactions error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to export transactions',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Build transaction query with filters (reusable for index and export)
     */
    protected function buildTransactionQuery(Request $request)
    {
        $query = Transaction::with(['category', 'branch', 'createdBy', 'approvedBy']);

        // 🔒 TENANT SCOPING — must mirror index(), otherwise export leaks every school's data.
        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $query->where('transactions.school_id', $schoolId);
        }
        $this->applyBranchFilter($query, $request, 'transactions.branch_id', 'transactions.school_id');

        // Filter by branch (only within the accessible set above)
        if ($request->has('branch_id') && $request->branch_id !== '') {
            $query->where('branch_id', $request->branch_id);
        }

        // Filter by type (Income/Expense)
        if ($request->has('type') && $request->type !== '') {
            $query->where('type', $request->type);
        }

        // Filter by category
        if ($request->has('category_id') && $request->category_id !== '') {
            $query->where('category_id', $request->category_id);
        }

        // Filter by status
        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        // Filter by financial year
        if ($request->has('financial_year') && $request->financial_year !== '') {
            $query->where('financial_year', $request->financial_year);
        }

        // Filter by date range
        if ($request->has('from_date') && $request->from_date !== '') {
            $query->where('transaction_date', '>=', $request->from_date);
        }

        if ($request->has('to_date') && $request->to_date !== '') {
            $query->where('transaction_date', '<=', $request->to_date);
        }

        // Global search
        if ($request->has('search') && $request->search !== '') {
            $searchTerm = $request->search;
            $query->where(function($q) use ($searchTerm) {
                $q->where('transaction_number', 'like', '%' . $searchTerm . '%')
                  ->orWhere('party_name', 'like', '%' . $searchTerm . '%')
                  ->orWhere('description', 'like', '%' . $searchTerm . '%');
            });
        }

        return $query;
    }

    /**
     * Export to Excel
     */
    protected function exportExcel($data, string $type, ?array $columns)
    {
        $export = new TransactionsExport($data, $type, $columns);
        $module = $type === 'income' ? 'income_transactions' : 'expense_transactions';
        $filename = (new ExportService($module))->generateFilename('xlsx');
        
        return Excel::download($export, $filename);
    }

    /**
     * Export to PDF
     */
    protected function exportPdf($data, string $type, ?array $columns)
    {
        $module = $type === 'income' ? 'income_transactions' : 'expense_transactions';
        $pdfService = new PdfExportService($module);
        
        if ($columns) {
            $pdfService->setColumns($columns);
        }
        
        // Use A3 paper for transactions to accommodate more columns
        $pdfService->setPaperSize('a3');
        $pdfService->setOrientation('landscape');
        
        $title = $type === 'income' ? 'Income Transactions Report' : 'Expense Transactions Report';
        $pdf = $pdfService->generate($data, $title);
        $filename = (new ExportService($module))->generateFilename('pdf');
        
        return $pdf->download($filename);
    }

    /**
     * Export to CSV
     */
    protected function exportCsv($data, string $type, ?array $columns)
    {
        $module = $type === 'income' ? 'income_transactions' : 'expense_transactions';
        $csvService = new CsvExportService($module);
        
        if ($columns) {
            $csvService->setColumns($columns);
        }
        
        $filename = (new ExportService($module))->generateFilename('csv');
        
        return $csvService->generate($data, $filename);
    }
}

