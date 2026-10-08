<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\LibraryFine;
use Illuminate\Http\Request;

class LibraryFineController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        $query = LibraryFine::with(['member:id,first_name,last_name,email,role', 'issue.book:id,title,author', 'collector:id,first_name,last_name']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('member_id')) {
            $query->where('member_id', (int) $request->member_id);
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        $fines = $this->paginateAndSort($query, $request, ['amount', 'paid_amount', 'status', 'created_at'], 'created_at', 'desc');

        return response()->json([
            'success' => true,
            'data' => $fines->items(),
            'meta' => [
                'current_page' => $fines->currentPage(),
                'per_page' => $fines->perPage(),
                'total' => $fines->total(),
                'last_page' => $fines->lastPage(),
            ],
            'summary' => [
                'pending_total' => (float) LibraryFine::where('status', 'Pending')->sum('amount'),
                'collected_total' => (float) LibraryFine::sum('paid_amount'),
                'waived_total' => (float) LibraryFine::sum('waived_amount'),
            ],
        ]);
    }

    public function collectPayment(Request $request, string $id)
    {
        $fine = LibraryFine::findOrFail($id);

        if ($fine->status === 'Paid') {
            return response()->json(['success' => false, 'message' => 'Fine is already fully paid'], 422);
        }

        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|in:Cash,Online,Fee Account,Other',
            'transaction_reference' => 'nullable|string|max:255',
        ]);

        $payingAmount = (float) $validated['amount'];
        $remaining = (float) $fine->amount - (float) $fine->paid_amount - (float) $fine->waived_amount;

        $newPaid = min((float) $fine->amount, (float) $fine->paid_amount + $payingAmount);
        $newStatus = ($newPaid >= (float) $fine->amount) ? 'Paid' : 'Partially Paid';

        $fine->update([
            'paid_amount' => $newPaid,
            'status' => $newStatus,
            'payment_method' => $validated['payment_method'],
            'transaction_reference' => $validated['transaction_reference'] ?? null,
            'collected_by' => $request->user()->id,
            'paid_at' => now(),
        ]);

        if ($fine->issue) {
            $fine->issue->update([
                'fine_paid' => $newStatus === 'Paid',
                'fine_paid_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Fine payment recorded successfully',
            'data' => $fine->fresh(['member', 'collector', 'issue.book']),
        ]);
    }

    public function waiveFine(Request $request, string $id)
    {
        $fine = LibraryFine::findOrFail($id);

        if ($fine->status === 'Paid' || $fine->status === 'Waived') {
            return response()->json(['success' => false, 'message' => 'Fine cannot be waived in its current status'], 422);
        }

        $validated = $request->validate([
            'waived_reason' => 'required|string|max:255',
        ]);

        $fine->update([
            'waived_amount' => (float) $fine->amount - (float) $fine->paid_amount,
            'status' => 'Waived',
            'waived_reason' => $validated['waived_reason'],
            'collected_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Fine waived successfully',
            'data' => $fine->fresh(),
        ]);
    }
}
