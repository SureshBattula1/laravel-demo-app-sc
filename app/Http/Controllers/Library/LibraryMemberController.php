<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\BookIssue;
use App\Models\LibraryFine;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LibraryMemberController extends Controller
{
    use PaginatesAndSorts;

    /**
     * List library members with live borrow & fine counts.
     */
    public function getMembers(Request $request)
    {
        $role = $request->get('role', 'all'); // 'Student', 'Teacher', 'all'
        $query = User::select('id', 'first_name', 'last_name', 'email', 'phone', 'role', 'user_type', 'branch_id', 'is_active');

        if ($role === 'Student') {
            $query->where('role', 'Student');
        } elseif ($role === 'Teacher' || $role === 'Staff') {
            $query->whereIn('role', ['Teacher', 'Staff', 'Admin']);
        } else {
            $query->whereIn('role', ['Student', 'Teacher', 'Staff', 'Admin']);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                    ->orWhere('last_name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            });
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        $members = $this->paginateAndSort($query, $request, ['first_name', 'last_name', 'email', 'role'], 'first_name', 'asc');

        // Enhance with active loan count & pending fines
        $memberIds = collect($members->items())->pluck('id')->toArray();

        $activeLoans = BookIssue::withoutTenantScope()
            ->where('status', 'Issued')
            ->where(function ($q) use ($memberIds) {
                $q->whereIn('student_id', $memberIds)->orWhereIn('teacher_id', $memberIds);
            })
            ->selectRaw('COALESCE(student_id, teacher_id) as member_id, count(*) as count, sum(case when due_date < ? then 1 else 0 end) as overdue_count', [Carbon::today()->toDateString()])
            ->groupBy('member_id')
            ->get()
            ->keyBy('member_id');

        $pendingFines = LibraryFine::withoutTenantScope()
            ->whereIn('member_id', $memberIds)
            ->where('status', 'Pending')
            ->groupBy('member_id')
            ->selectRaw('member_id, sum(amount) as pending_total')
            ->pluck('pending_total', 'member_id');

        $items = collect($members->items())->map(function ($m) use ($activeLoans, $pendingFines) {
            $mArray = $m->toArray();
            $loanInfo = $activeLoans->get($m->id);
            $mArray['active_loans_count'] = $loanInfo?->count ?? 0;
            $mArray['overdue_loans_count'] = $loanInfo?->overdue_count ?? 0;
            $mArray['pending_fines'] = (float) ($pendingFines->get($m->id) ?? 0);

            return $mArray;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $members->currentPage(),
                'per_page' => $members->perPage(),
                'total' => $members->total(),
                'last_page' => $members->lastPage(),
            ],
        ]);
    }

    /**
     * Full Library Member Profile.
     */
    public function getMemberProfile(string $id)
    {
        $member = User::with('branch')->findOrFail($id);

        $activeLoans = BookIssue::withoutTenantScope()
            ->with(['book', 'copy'])
            ->where('status', 'Issued')
            ->where(fn ($q) => $q->where('student_id', $member->id)->orWhere('teacher_id', $member->id))
            ->orderBy('due_date', 'asc')
            ->get();

        $history = BookIssue::withoutTenantScope()
            ->with(['book', 'copy'])
            ->where('status', '!=', 'Issued')
            ->where(fn ($q) => $q->where('student_id', $member->id)->orWhere('teacher_id', $member->id))
            ->orderByDesc('return_date')
            ->limit(20)
            ->get();

        $fines = LibraryFine::withoutTenantScope()
            ->where('member_id', $member->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'member' => $member,
                'active_loans' => $activeLoans,
                'loan_history' => $history,
                'fines' => $fines,
                'summary' => [
                    'active_count' => $activeLoans->count(),
                    'overdue_count' => $activeLoans->filter(fn ($l) => Carbon::parse($l->due_date)->lt(Carbon::today()))->count(),
                    'total_borrowed' => $activeLoans->count() + $history->count(),
                    'total_pending_fines' => (float) $fines->where('status', 'Pending')->sum('amount'),
                ],
            ],
        ]);
    }
}
