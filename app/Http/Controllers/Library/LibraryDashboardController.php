<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\BookIssue;
use App\Models\LibraryBookCopy;
use App\Models\LibraryFine;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryDashboardController extends Controller
{
    /**
     * Library KPI Summary Cards.
     */
    public function getSummary(Request $request)
    {
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $accessibleBranches = $this->getAccessibleBranchIds($request);

        $copyQuery = LibraryBookCopy::withoutTenantScope();
        $bookQuery = Book::withoutTenantScope();
        $issueQuery = BookIssue::withoutTenantScope();
        $fineQuery = LibraryFine::withoutTenantScope();

        if ($branchId) {
            $copyQuery->where('branch_id', $branchId);
            $bookQuery->where('branch_id', $branchId);
            $issueQuery->where('branch_id', $branchId);
            $fineQuery->where('branch_id', $branchId);
        } elseif ($accessibleBranches !== 'all') {
            $copyQuery->whereIn('branch_id', $accessibleBranches);
            $bookQuery->whereIn('branch_id', $accessibleBranches);
            $issueQuery->whereIn('branch_id', $accessibleBranches);
            $fineQuery->whereIn('branch_id', $accessibleBranches);
        }

        $totalTitles = (clone $bookQuery)->whereNull('deleted_at')->count();
        $totalCopies = (clone $copyQuery)->whereNull('deleted_at')->count();
        $issuedCopies = (clone $copyQuery)->where('status', 'Issued')->whereNull('deleted_at')->count();
        $availableCopies = (clone $copyQuery)->where('status', 'Available')->whereNull('deleted_at')->count();

        $overdueCount = (clone $issueQuery)
            ->where('status', 'Issued')
            ->whereDate('due_date', '<', Carbon::today())
            ->count();

        $activeMembers = (clone $issueQuery)
            ->where('status', 'Issued')
            ->distinct()
            ->count(DB::raw('COALESCE(student_id, teacher_id)'));

        $pendingFines = (clone $fineQuery)->where('status', 'Pending')->sum('amount');
        $collectedFines = (clone $fineQuery)->sum('paid_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'total_titles' => $totalTitles,
                'total_copies' => $totalCopies ?: $bookQuery->sum('total_copies'),
                'issued_copies' => $issuedCopies,
                'available_copies' => $availableCopies,
                'overdue_copies' => $overdueCount,
                'active_members' => $activeMembers,
                'pending_fines' => (float) $pendingFines,
                'collected_fines' => (float) $collectedFines,
            ],
        ]);
    }

    /**
     * Monthly Circulation Trends (Issues vs Returns) for past 6 months.
     */
    public function getCirculationTrends(Request $request)
    {
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $accessibleBranches = $this->getAccessibleBranchIds($request);

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::today()->subMonths($i);
            $months[] = [
                'year' => $date->year,
                'month' => $date->month,
                'label' => $date->format('M Y'),
                'issues' => 0,
                'returns' => 0,
            ];
        }

        $start = Carbon::today()->subMonths(5)->startOfMonth();

        $issuesQuery = BookIssue::withoutTenantScope()
            ->where('issue_date', '>=', $start)
            ->selectRaw('YEAR(issue_date) as y, MONTH(issue_date) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm');

        $returnsQuery = BookIssue::withoutTenantScope()
            ->whereNotNull('return_date')
            ->where('return_date', '>=', $start)
            ->selectRaw('YEAR(return_date) as y, MONTH(return_date) as m, COUNT(*) as cnt')
            ->groupBy('y', 'm');

        if ($branchId) {
            $issuesQuery->where('branch_id', $branchId);
            $returnsQuery->where('branch_id', $branchId);
        } elseif ($accessibleBranches !== 'all') {
            $issuesQuery->whereIn('branch_id', $accessibleBranches);
            $returnsQuery->whereIn('branch_id', $accessibleBranches);
        }

        $issueResults = $issuesQuery->get()->keyBy(fn ($r) => "{$r->y}-{$r->m}");
        $returnResults = $returnsQuery->get()->keyBy(fn ($r) => "{$r->y}-{$r->m}");

        foreach ($months as &$m) {
            $key = "{$m['year']}-{$m['month']}";
            $m['issues'] = $issueResults->get($key)?->cnt ?? 0;
            $m['returns'] = $returnResults->get($key)?->cnt ?? 0;
        }

        return response()->json([
            'success' => true,
            'data' => $months,
        ]);
    }

    /**
     * Top Borrowed Books Leaderboard.
     */
    public function getPopularBooks(Request $request)
    {
        $branchId = $request->filled('branch_id') ? (int) $request->branch_id : null;
        $accessibleBranches = $this->getAccessibleBranchIds($request);

        $query = DB::table('book_issues')
            ->join('books', 'book_issues.book_id', '=', 'books.id')
            ->select(
                'books.id',
                'books.title',
                'books.author',
                'books.category',
                'books.cover_image',
                DB::raw('COUNT(book_issues.id) as borrow_count')
            )
            ->groupBy('books.id', 'books.title', 'books.author', 'books.category', 'books.cover_image')
            ->orderByDesc('borrow_count')
            ->limit(8);

        if ($branchId) {
            $query->where('book_issues.branch_id', $branchId);
        } elseif ($accessibleBranches !== 'all') {
            $query->whereIn('book_issues.branch_id', $accessibleBranches);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(),
        ]);
    }
}
