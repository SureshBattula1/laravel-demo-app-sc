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
     * Get accounts dashboard statistics
     */
    public function getDashboard(Request $request)
    {
        try {
            $branchId = $request->get('branch_id');
            $financialYear = $request->get('financial_year', $this->getCurrentFinancialYear());

            $query = Transaction::with(['category', 'branch'])
                ->where('status', 'Approved')
                ->where('financial_year', $financialYear);

            // 🔥 APPLY BRANCH FILTERING - Restrict to accessible branches
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            } elseif ($branchId) {
                $query->where('branch_id', $branchId);
            }

            // OPTIMIZED: Single aggregated query for totals (both Income and Expense in one query)
            $totalsQuery = DB::table('transactions')
                ->where('status', 'Approved')
                ->where('financial_year', $financialYear);

            // Apply branch filter
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $totalsQuery->whereIn('branch_id', $accessibleBranchIds);
                } else {
                    // No access to any branches - return empty data
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
            } elseif ($branchId) {
                $totalsQuery->where('branch_id', $branchId);
            }

            $totals = $totalsQuery
                ->select(
                    DB::raw('SUM(CASE WHEN type = "Income" THEN amount ELSE 0 END) as total_income'),
                    DB::raw('SUM(CASE WHEN type = "Expense" THEN amount ELSE 0 END) as total_expense')
                )
                ->first();

            $totalIncome = (float) ($totals->total_income ?? 0);
            $totalExpense = (float) ($totals->total_expense ?? 0);
            $netBalance = $totalIncome - $totalExpense;

            // OPTIMIZED: Single aggregated query for category breakdown (both Income and Expense)
            $categoryQuery = DB::table('transactions')
                ->join('account_categories', 'transactions.category_id', '=', 'account_categories.id')
                ->where('transactions.status', 'Approved')
                ->where('transactions.financial_year', $financialYear);

            // Apply branch filter
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $categoryQuery->whereIn('transactions.branch_id', $accessibleBranchIds);
                }
            } elseif ($branchId) {
                $categoryQuery->where('transactions.branch_id', $branchId);
            }

            $categoryBreakdown = $categoryQuery
                ->select(
                    'transactions.type',
                    'account_categories.name as category',
                    DB::raw('SUM(transactions.amount) as amount')
                )
                ->groupBy('transactions.type', 'account_categories.name')
                ->get();

            // Separate income and expense categories
            $incomeByCategory = $categoryBreakdown
                ->filter(fn($item) => $item->type === 'Income')
                ->map(fn($item) => ['category' => $item->category, 'amount' => (float) $item->amount]);

            $expenseByCategory = $categoryBreakdown
                ->filter(fn($item) => $item->type === 'Expense')
                ->map(fn($item) => ['category' => $item->category, 'amount' => (float) $item->amount]);

            // Recent transactions
            $recentTransactions = Transaction::with(['category', 'branch', 'createdBy'])
                ->when($branchId, function ($q) use ($branchId) {
                    return $q->where('branch_id', $branchId);
                })
                ->where('financial_year', $financialYear)
                ->orderBy('transaction_date', 'desc')
                ->limit(10)
                ->get();

            // Monthly trend (last 12 months)
            $monthlyTrend = DB::table('transactions')
                ->where('status', 'Approved')
                ->where('transaction_date', '>=', now()->subMonths(12))
                ->whereNull('deleted_at')
                ->select(
                    DB::raw('MONTH(transaction_date) as month'),
                    DB::raw('YEAR(transaction_date) as year'),
                    'type',
                    DB::raw('SUM(amount) as total')
                )
                ->groupBy(DB::raw('YEAR(transaction_date)'), DB::raw('MONTH(transaction_date)'), 'type')
                ->orderBy(DB::raw('YEAR(transaction_date)'), 'asc')
                ->orderBy(DB::raw('MONTH(transaction_date)'), 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'total_income' => round((float)$totalIncome, 2),
                        'total_expense' => round((float)$totalExpense, 2),
                        'net_balance' => round((float)$netBalance, 2),
                        'financial_year' => $financialYear
                    ],
                    'income_by_category' => $incomeByCategory->toArray(),
                    'expense_by_category' => $expenseByCategory->toArray(),
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
     * Get account categories
     */
    public function getCategories(Request $request)
    {
        try {
            $query = AccountCategory::query();

            if ($request->has('type')) {
                $query->where('type', $request->type);
            }

            if ($request->has('is_active')) {
                $query->where('is_active', $request->boolean('is_active'));
            }

            $categories = $query->orderBy('name', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $categories
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
     * Get single account category
     */
    public function getCategory($id)
    {
        try {
            $category = AccountCategory::with(['transactions' => function ($query) {
                $query->latest()->limit(10);
            }, 'budgets'])->findOrFail($id);

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

