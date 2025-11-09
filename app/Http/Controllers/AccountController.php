<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\AccountCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AccountController extends Controller
{
    /**
     * Get accounts dashboard statistics - INDEX OPTIMIZED
     */
    public function getDashboard(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');
            $financialYear = $request->get('financial_year', $this->getCurrentFinancialYear());
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);

            // Early return for no access
            if ($accessibleBranchIds !== 'all' && empty($accessibleBranchIds)) {
                return response()->json([
                    'success' => true,
                    'data' => [
                        'summary' => ['total_income' => 0, 'total_expense' => 0, 'net_balance' => 0, 'financial_year' => $financialYear],
                        'income_by_category' => [],
                        'expense_by_category' => [],
                        'recent_transactions' => [],
                        'monthly_trend' => []
                    ]
                ]);
            }

            // OPTIMIZED: Use indexes - status + financial_year + branch_id
            $baseQuery = DB::table('transactions')
                ->where('status', 'Approved')
                ->where('financial_year', $financialYear)
                ->whereNull('deleted_at');

            if ($accessibleBranchIds !== 'all') {
                $baseQuery->whereIn('branch_id', $accessibleBranchIds);
            } elseif ($branchId) {
                $baseQuery->where('branch_id', $branchId);
            }

            // Query 1: Get totals (uses index on status, financial_year)
            $totals = (clone $baseQuery)
                ->selectRaw('SUM(IF(type = "Income", amount, 0)) as total_income')
                ->selectRaw('SUM(IF(type = "Expense", amount, 0)) as total_expense')
                ->first();

            $totalIncome = (float) ($totals->total_income ?? 0);
            $totalExpense = (float) ($totals->total_expense ?? 0);

            // Query 2: Category breakdown (uses index on category_id, financial_year)
            $categoryBreakdown = DB::table('transactions')
                ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
                ->where('transactions.status', 'Approved')
                ->where('transactions.financial_year', $financialYear)
                ->whereNull('transactions.deleted_at')
                ->when($accessibleBranchIds !== 'all', fn($q) => $q->whereIn('transactions.branch_id', $accessibleBranchIds))
                ->when($branchId, fn($q) => $q->where('transactions.branch_id', $branchId))
                ->selectRaw('transactions.type, account_categories.name as category, SUM(transactions.amount) as amount')
                ->groupBy('transactions.type', 'account_categories.name')
                ->get();

            $incomeByCategory = $categoryBreakdown->where('type', 'Income')->map(fn($i) => ['category' => $i->category, 'amount' => (float) $i->amount])->values();
            $expenseByCategory = $categoryBreakdown->where('type', 'Expense')->map(fn($i) => ['category' => $i->category, 'amount' => (float) $i->amount])->values();

            // Query 3: Recent transactions (uses index on transaction_date)
            $recentTransactions = Transaction::select('id', 'transaction_number', 'transaction_date', 'type', 'amount', 'description', 'status', 'category_id', 'branch_id')
                ->where('financial_year', $financialYear)
                ->whereNull('deleted_at')
                ->when($accessibleBranchIds !== 'all', fn($q) => $q->whereIn('branch_id', $accessibleBranchIds))
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->with(['category:id,name,code', 'branch:id,name,code'])
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get();

            // Query 4: Monthly trend (uses index on transaction_date)
            $monthlyTrend = DB::table('transactions')
                ->where('status', 'Approved')
                ->where('transaction_date', '>=', now()->subMonths(12)->format('Y-m-d'))
                ->whereNull('deleted_at')
                ->when($accessibleBranchIds !== 'all', fn($q) => $q->whereIn('branch_id', $accessibleBranchIds))
                ->when($branchId, fn($q) => $q->where('branch_id', $branchId))
                ->selectRaw('MONTH(transaction_date) as month, YEAR(transaction_date) as year, type, SUM(amount) as total')
                ->groupBy(DB::raw('YEAR(transaction_date), MONTH(transaction_date), type'))
                ->orderByRaw('year ASC, month ASC')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_income' => round($totalIncome, 2),
                        'total_expense' => round($totalExpense, 2),
                        'net_balance' => round($totalIncome - $totalExpense, 2),
                        'financial_year' => $financialYear
                    ],
                    'income_by_category' => $incomeByCategory,
                    'expense_by_category' => $expenseByCategory,
                    'recent_transactions' => $recentTransactions,
                    'monthly_trend' => $monthlyTrend
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get accounts dashboard error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to load dashboard',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get account categories - INDEX OPTIMIZED
     */
    public function getCategories(Request $request)
    {
        try {
            // OPTIMIZED: Select only needed columns, use indexes
            $query = AccountCategory::select([
                'id',
                'branch_id',
                'name', 
                'code', 
                'type', 
                'sub_type', 
                'is_active'
            ])->with('branch:id,name,code');

            // Use index on branch_id (null = global for all branches)
            if ($request->has('branch_id')) {
                $query->where(function($q) use ($request) {
                    $q->where('branch_id', $request->branch_id)
                      ->orWhereNull('branch_id'); // Include global categories
                });
            }

            // Use index on type column
            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            // Use index on is_active column
            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            // Order by name (no index needed for ORDER BY on small dataset)
            $categories = $query->orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $categories,
                'count' => $categories->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get categories error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch categories',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get single account category - INDEX OPTIMIZED
     */
    public function getCategory($id)
    {
        try {
            // OPTIMIZED: Load only necessary transaction fields
            $category = AccountCategory::select([
                'id', 'name', 'code', 'type', 'sub_type', 
                'description', 'is_active', 'created_at', 'updated_at'
            ])
            ->with([
                'transactions' => function ($query) {
                    $query->select('id', 'transaction_number', 'transaction_date', 'type', 'amount', 'status', 'category_id')
                        ->latest('transaction_date')
                        ->limit(10);
                },
                'budgets' => function ($query) {
                    $query->select('id', 'category_id', 'financial_year', 'allocated_amount', 'utilized_amount');
                }
            ])
            ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $category
            ]);

        } catch (\Exception $e) {
            Log::error('Get category error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Category not found',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 404);
        }
    }

    /**
     * Create new account category
     */
    public function createCategory(Request $request)
    {
        DB::beginTransaction();
        try {
            $validated = $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'name' => 'required|string|max:255|unique:account_categories,name',
                'code' => 'required|string|max:50|unique:account_categories,code',
                'type' => 'required|in:Income,Expense',
                'sub_type' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            // Convert empty strings to null for nullable fields
            $validated['sub_type'] = !empty($validated['sub_type']) ? $validated['sub_type'] : null;
            $validated['description'] = !empty($validated['description']) ? $validated['description'] : null;

            $category = AccountCategory::create($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Account category created successfully'
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create category error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create category',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Update account category
     */
    public function updateCategory(Request $request, $id)
    {
        DB::beginTransaction();
        try {
            $category = AccountCategory::findOrFail($id);

            $validated = $request->validate([
                'branch_id' => 'nullable|exists:branches,id',
                'name' => 'required|string|max:255|unique:account_categories,name,' . $id,
                'code' => 'required|string|max:50|unique:account_categories,code,' . $id,
                'type' => 'required|in:Income,Expense',
                'sub_type' => 'nullable|string|max:255',
                'description' => 'nullable|string',
                'is_active' => 'boolean'
            ]);

            // Convert empty strings to null for nullable fields
            if (isset($validated['sub_type'])) {
                $validated['sub_type'] = !empty($validated['sub_type']) ? $validated['sub_type'] : null;
            }
            if (isset($validated['description'])) {
                $validated['description'] = !empty($validated['description']) ? $validated['description'] : null;
            }

            $category->update($validated);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $category->fresh(),
                'message' => 'Account category updated successfully'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update category error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update category',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Delete account category
     */
    public function deleteCategory($id)
    {
        DB::beginTransaction();
        try {
            $category = AccountCategory::findOrFail($id);

            // Check if category has transactions
            if ($category->transactions()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete category with existing transactions. Consider deactivating instead.'
                ], 422);
            }

            $category->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Account category deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete category error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete category',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Toggle category status
     */
    public function toggleCategoryStatus($id)
    {
        DB::beginTransaction();
        try {
            $category = AccountCategory::findOrFail($id);
            $category->is_active = !$category->is_active;
            $category->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $category,
                'message' => 'Category status updated successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Toggle category status error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle status',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get current financial year
     */
    private function getCurrentFinancialYear(): string
    {
        $month = date('n');
        $year = date('Y');
        
        // Financial year starts from April (month 4)
        if ($month < 4) {
            return ($year - 1) . '-' . $year;
        } else {
            return $year . '-' . ($year + 1);
        }
    }
}

