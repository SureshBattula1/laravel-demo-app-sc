<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Models\BookIssue;
use App\Models\LibraryBookCopy;
use App\Models\LibraryFine;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LibraryReportController extends Controller
{
    /**
     * Circulation Report (Filter by date range, branch, borrower type).
     */
    public function circulationReport(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::today()->subMonths(1)->toDateString());
        $endDate = $request->get('end_date', Carbon::today()->toDateString());

        $query = BookIssue::withoutTenantScope()
            ->with(['book:id,title,isbn,category', 'copy:id,barcode,accession_number', 'borrower:id,first_name,last_name,email'])
            ->whereBetween('issue_date', [$startDate, $endDate]);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        if ($request->filled('borrower_type')) {
            $query->where('borrower_type', $request->borrower_type);
        }

        $issues = $query->orderByDesc('issue_date')->get();

        $summary = [
            'total_loans' => $issues->count(),
            'returned_count' => $issues->where('status', 'Returned')->count(),
            'active_count' => $issues->where('status', 'Issued')->count(),
            'total_fines' => (float) $issues->sum('fine_amount'),
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $issues,
        ]);
    }

    /**
     * Inventory Valuation & Condition Report.
     */
    public function inventoryReport(Request $request)
    {
        $query = LibraryBookCopy::withoutTenantScope()->with(['book:id,title,category', 'shelf:id,code']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        $copies = $query->get();

        $byCondition = $copies->groupBy('condition')->map->count();
        $byStatus = $copies->groupBy('status')->map->count();
        $totalValuation = $copies->sum('purchase_price');

        return response()->json([
            'success' => true,
            'summary' => [
                'total_copies' => $copies->count(),
                'total_valuation' => (float) $totalValuation,
                'by_condition' => $byCondition,
                'by_status' => $byStatus,
            ],
            'data' => $copies,
        ]);
    }

    /**
     * Fine Collection & Waiver Audit Report.
     */
    public function fineReport(Request $request)
    {
        $startDate = $request->get('start_date', Carbon::today()->subMonths(1)->toDateString());
        $endDate = $request->get('end_date', Carbon::today()->toDateString());

        $query = LibraryFine::withoutTenantScope()
            ->with(['member:id,first_name,last_name', 'issue.book:id,title', 'collector:id,first_name,last_name'])
            ->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        $fines = $query->orderByDesc('created_at')->get();

        return response()->json([
            'success' => true,
            'summary' => [
                'total_assessed' => (float) $fines->sum('amount'),
                'total_collected' => (float) $fines->sum('paid_amount'),
                'total_waived' => (float) $fines->sum('waived_amount'),
                'pending_balance' => (float) $fines->where('status', 'Pending')->sum('amount'),
            ],
            'data' => $fines,
        ]);
    }
}
