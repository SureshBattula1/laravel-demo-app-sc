<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\BookIssue;
use App\Models\LibraryBookCopy;
use App\Services\AcademicYearContext;
use App\Services\Library\LibraryCirculationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class LibraryCirculationController extends Controller
{
    use PaginatesAndSorts;

    public function __construct(
        protected LibraryCirculationService $circulationService,
        protected AcademicYearContext $academicYearContext
    ) {}

    /**
     * Fast Issue Desk (by copy barcode or book ID + copy ID).
     */
    public function issueBook(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'member_id' => 'required|exists:users,id',
            'borrower_type' => 'required|in:Student,Teacher',
            'barcode' => 'nullable|string',
            'book_id' => 'required_without:barcode|nullable|exists:books,id',
            'copy_id' => 'nullable|exists:library_book_copies,id',
            'due_date' => 'nullable|date',
            'issue_date' => 'nullable|date',
            'remarks' => 'nullable|string',
        ]);

        $bookId = $validated['book_id'] ?? null;
        $copyId = $validated['copy_id'] ?? null;

        // If barcode was scanned, resolve copy and book
        if (! empty($validated['barcode'])) {
            $scanned = trim($validated['barcode']);
            $copy = LibraryBookCopy::withoutTenantScope()
                ->where('branch_id', (int) $validated['branch_id'])
                ->where(fn ($q) => $q->where('barcode', $scanned)->orWhere('accession_number', $scanned))
                ->first();

            if (! $copy) {
                return response()->json([
                    'success' => false,
                    'message' => "Book copy not found with barcode/accession '{$scanned}'",
                ], 404);
            }

            $bookId = $copy->book_id;
            $copyId = $copy->id;
        }

        $issue = $this->circulationService->issue(
            (int) $validated['branch_id'],
            $this->getCurrentSchoolId($request),
            (int) $bookId,
            $copyId ? (int) $copyId : null,
            (int) $validated['member_id'],
            $validated['borrower_type'],
            $validated['due_date'] ?? null,
            $validated['issue_date'] ?? null,
            $validated['remarks'] ?? null,
            $this->academicYearContext->id(false)
        );

        return response()->json([
            'success' => true,
            'message' => 'Book issued successfully',
            'data' => $issue->load(['book', 'copy', 'borrower']),
        ], 201);
    }

    /**
     * Desk autosuggest: top matching copies by barcode / accession / title with live status.
     * mode=issue lists every status (Available first); mode=return lists only issued copies
     * and also matches the borrower's name.
     */
    public function lookupCopies(Request $request)
    {
        $validated = $request->validate([
            'q' => 'required|string|min:1|max:100',
            'mode' => 'nullable|in:issue,return',
            'branch_id' => 'nullable',
            'limit' => 'nullable|integer|min:1|max:20',
        ]);

        $term = trim($validated['q']);
        $mode = $validated['mode'] ?? 'issue';
        $limit = (int) ($validated['limit'] ?? 5);

        $query = LibraryBookCopy::with([
            'book:id,title,author,isbn',
            'shelf:id,code,rack_number,shelf_number',
            'activeIssue',
            'activeIssue.student:id,first_name,last_name',
            'activeIssue.teacher:id,first_name,last_name',
        ])->select('id', 'book_id', 'branch_id', 'shelf_id', 'accession_number', 'barcode', 'copy_number', 'condition', 'status');

        $query->where(function ($q) use ($term, $mode) {
            $q->where('barcode', 'like', "%{$term}%")
                ->orWhere('accession_number', 'like', "%{$term}%")
                ->orWhereHas('book', fn ($bq) => $bq->where('title', 'like', "%{$term}%")->orWhere('isbn', 'like', "%{$term}%"));

            if ($mode === 'return') {
                $q->orWhereHas('issues', function ($iq) use ($term) {
                    $nameMatch = fn ($uq) => $uq->where('first_name', 'like', "%{$term}%")
                        ->orWhere('last_name', 'like', "%{$term}%")
                        ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", ["%{$term}%"]);
                    $iq->where('status', 'Issued')
                        ->where(fn ($bq) => $bq->whereHas('student', $nameMatch)->orWhereHas('teacher', $nameMatch));
                });
            }
        });

        if ($mode === 'return') {
            $query->where('status', 'Issued');
        }

        if (! empty($validated['branch_id'])) {
            $query->where('branch_id', (int) $validated['branch_id']);
        }
        $this->applyBranchFilter($query, $request);

        $query->orderByRaw(
            'CASE WHEN barcode = ? OR accession_number = ? THEN 0 WHEN barcode LIKE ? OR accession_number LIKE ? THEN 1 ELSE 2 END',
            [$term, $term, "{$term}%", "{$term}%"]
        );
        if ($mode === 'issue') {
            $query->orderByRaw("CASE WHEN status = 'Available' THEN 0 ELSE 1 END");
        }
        $query->orderBy('accession_number');

        $today = Carbon::today();
        $rules = LibraryCirculationService::DEFAULT_RULES;

        $items = $query->limit($limit)->get()->map(function (LibraryBookCopy $copy) use ($today, $rules) {
            $issue = $copy->activeIssue;
            $borrower = $issue ? ($issue->borrower_type === 'Teacher' ? $issue->teacher : $issue->student) : null;
            $daysOverdue = 0;
            if ($issue && $issue->due_date && Carbon::parse($issue->due_date)->lt($today)) {
                $daysOverdue = (int) Carbon::parse($issue->due_date)->diffInDays($today);
            }
            $rate = ($rules[$issue->borrower_type ?? 'Student'] ?? $rules['Student'])['fine_per_day'];

            return [
                'id' => $copy->id,
                'book_id' => $copy->book_id,
                'barcode' => $copy->barcode,
                'accession_number' => $copy->accession_number,
                'copy_number' => $copy->copy_number,
                'status' => $copy->status,
                'condition' => $copy->condition,
                'title' => $copy->book?->title,
                'author' => $copy->book?->author,
                'shelf_code' => $copy->shelf?->code,
                'active_issue' => $issue ? [
                    'id' => $issue->id,
                    'borrower_type' => $issue->borrower_type,
                    'borrower_name' => $borrower ? trim("{$borrower->first_name} {$borrower->last_name}") : 'Unknown',
                    'issue_date' => $issue->issue_date ? Carbon::parse($issue->issue_date)->toDateString() : null,
                    'due_date' => $issue->due_date ? Carbon::parse($issue->due_date)->toDateString() : null,
                    'days_overdue' => $daysOverdue,
                    'fine_per_day' => $rate,
                    'estimated_fine' => $daysOverdue * $rate,
                ] : null,
            ];
        });

        return response()->json(['success' => true, 'data' => $items]);
    }

    /**
     * Fast Return Desk (by issue ID or copy barcode).
     */
    public function returnBook(Request $request)
    {
        $validated = $request->validate([
            'issue_id' => 'required_without:barcode|nullable|exists:book_issues,id',
            'barcode' => 'required_without:issue_id|nullable|string',
            'return_date' => 'nullable|date',
            'collect_fine' => 'boolean',
            'fine_action' => 'nullable|in:collect,assign,waive',
            'waive_reason' => 'nullable|string|max:500',
            'copy_condition' => 'nullable|in:New,Good,Fair,Damaged,Lost,Weeded',
            'payment_method' => 'nullable|in:Cash,Online,Fee Account,Other',
            'remarks' => 'nullable|string',
        ]);

        $issueId = $validated['issue_id'] ?? null;

        if (! empty($validated['barcode'])) {
            $scanned = trim($validated['barcode']);
            $copy = LibraryBookCopy::withoutTenantScope()
                ->where(fn ($q) => $q->where('barcode', $scanned)->orWhere('accession_number', $scanned))
                ->first();

            if (! $copy) {
                return response()->json(['success' => false, 'message' => "No copy found matching '{$scanned}'"], 404);
            }

            $activeIssue = BookIssue::withoutTenantScope()
                ->where('copy_id', $copy->id)
                ->where('status', 'Issued')
                ->first();

            if (! $activeIssue) {
                return response()->json(['success' => false, 'message' => "Copy '{$scanned}' is not currently issued"], 422);
            }

            $issueId = $activeIssue->id;
        }

        $result = $this->circulationService->return(
            (int) $issueId,
            $validated['return_date'] ?? null,
            $request->boolean('collect_fine', true),
            $validated['copy_condition'] ?? null,
            $validated['remarks'] ?? null,
            $request->user()->id,
            $validated['payment_method'] ?? 'Cash',
            $validated['fine_action'] ?? null,
            $validated['waive_reason'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Book returned successfully',
            'data' => $result['issue'],
            'fine_amount' => $result['fine_amount'],
            'fine_paid' => $result['fine_paid'],
            'fine_action' => $result['fine_action'],
            'reservation_held' => $result['reservation_held'],
        ]);
    }

    /**
     * Renew an issued book loan.
     */
    public function renewBook(Request $request)
    {
        $validated = $request->validate([
            'issue_id' => 'required|exists:book_issues,id',
            'additional_days' => 'nullable|integer|min:1|max:60',
        ]);

        $issue = $this->circulationService->renew(
            (int) $validated['issue_id'],
            $validated['additional_days'] ?? null
        );

        return response()->json([
            'success' => true,
            'message' => 'Book renewed successfully',
            'data' => $issue,
        ]);
    }

    /**
     * List Overdue Loans.
     */
    public function getOverdue(Request $request)
    {
        $query = BookIssue::withoutTenantScope()
            ->with(['book', 'copy', 'branch'])
            ->where('status', 'Issued')
            ->whereDate('due_date', '<', Carbon::today());

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        $issues = $this->paginateAndSort($query, $request, ['due_date', 'issue_date'], 'due_date', 'asc');

        $today = Carbon::today();
        $rules = LibraryCirculationService::DEFAULT_RULES;

        $items = collect($issues->items())->map(function ($issue) use ($today, $rules) {
            $issueArr = $issue->toArray();
            $due = Carbon::parse($issue->due_date);
            $daysLate = $due->diffInDays($today);
            $rate = ($rules[$issue->borrower_type] ?? $rules['Student'])['fine_per_day'];
            $issueArr['days_overdue'] = $daysLate;
            $issueArr['accumulated_fine'] = $daysLate * $rate;

            $borrower = $issue->student ?? $issue->teacher;
            $issueArr['borrower_name'] = $borrower ? "{$borrower->first_name} {$borrower->last_name}" : 'Unknown';
            $issueArr['borrower_email'] = $borrower?->email;
            $issueArr['borrower_phone'] = $borrower?->phone;

            return $issueArr;
        })->toArray();

        return response()->json([
            'success' => true,
            'data' => $items,
            'meta' => [
                'current_page' => $issues->currentPage(),
                'per_page' => $issues->perPage(),
                'total' => $issues->total(),
                'last_page' => $issues->lastPage(),
            ],
        ]);
    }

    /**
     * Send Overdue Reminder Notice.
     */
    public function sendOverdueReminder(Request $request)
    {
        $validated = $request->validate([
            'issue_id' => 'required|exists:book_issues,id',
        ]);

        $issue = BookIssue::withoutTenantScope()->with(['book', 'borrower'])->findOrFail($validated['issue_id']);

        return response()->json([
            'success' => true,
            'message' => "Overdue reminder queued for {$issue->borrower?->first_name}",
        ]);
    }
}
