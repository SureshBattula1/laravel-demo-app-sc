<?php

namespace App\Http\Controllers;

use App\Http\Requests\IssueBookRequest;
use App\Http\Requests\StoreBookRequest;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Book;
use App\Models\BookIssue;
use App\Services\AcademicYearContext;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class LibraryController extends Controller
{
    use PaginatesAndSorts;

    /**
     * Fixed circulation rules per borrower type (v1 — not yet configurable):
     * loan period (days), max concurrent books, fine per overdue day (₹).
     */
    private const LOAN_RULES = [
        'Student' => ['days' => 14, 'max' => 3, 'fine_per_day' => 5],
        'Teacher' => ['days' => 30, 'max' => 10, 'fine_per_day' => 5],
    ];

    public function __construct(
        protected AcademicYearContext $academicYearContext
    ) {}

    /**
     * List books (branch/school scoped).
     */
    public function index(Request $request)
    {
        try {
            $query = DB::table('books')
                ->leftJoin('branches', 'books.branch_id', '=', 'branches.id')
                ->whereNull('books.deleted_at')
                ->select(
                    'books.*',
                    'branches.name as branch_name',
                    'branches.code as branch_code'
                );

            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where(function ($q) use ($schoolId) {
                    $q->where('books.school_id', $schoolId)
                        ->orWhere(function ($q2) use ($schoolId) {
                            $q2->whereNull('books.school_id')
                                ->whereIn('books.branch_id', function ($sq) use ($schoolId) {
                                    $sq->select('id')->from('branches')
                                        ->where('school_id', (int) $schoolId)
                                        ->whereNull('deleted_at');
                                });
                        });
                });
            }

            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('books.branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            if ($request->filled('branch_id')) {
                $query->where('books.branch_id', (int) $request->branch_id);
            }
            if ($request->filled('category')) {
                $query->where('books.category', $request->category);
            }
            if ($request->boolean('available_only')) {
                $query->where('books.available_copies', '>', 0);
            }
            if ($request->filled('search')) {
                $search = strip_tags($request->search);
                $query->where(function ($q) use ($search) {
                    $q->where('books.title', 'like', "{$search}%")
                        ->orWhere('books.author', 'like', "{$search}%")
                        ->orWhere('books.isbn', 'like', "{$search}%");
                });
            }

            $sortableColumns = [
                'books.title', 'books.author', 'books.category',
                'books.total_copies', 'books.available_copies', 'books.created_at', 'branches.name',
            ];
            $books = $this->paginateAndSort($query, $request, $sortableColumns, 'books.title', 'asc');

            $data = collect($books->items())->map(function ($book) {
                $book->branch = [
                    'id' => $book->branch_id,
                    'name' => $book->branch_name,
                    'code' => $book->branch_code,
                ];
                unset($book->branch_name, $book->branch_code);
                return $book;
            })->toArray();

            return response()->json([
                'success' => true,
                'message' => 'Books retrieved successfully',
                'data' => $data,
                'meta' => [
                    'current_page' => $books->currentPage(),
                    'per_page' => $books->perPage(),
                    'total' => $books->total(),
                    'last_page' => $books->lastPage(),
                    'from' => $books->firstItem(),
                    'to' => $books->lastItem(),
                    'has_more_pages' => $books->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching books', ['error' => $e->getMessage()]);
            return $this->serverError('Error fetching books', $e);
        }
    }

    public function store(StoreBookRequest $request)
    {
        try {
            $branchId = (int) $request->branch_id;
            if (!$this->canAccessBranch($request, $branchId)) {
                return $this->forbidden();
            }

            $schoolId = DB::table('branches')->where('id', $branchId)->value('school_id')
                ?? $this->getCurrentSchoolId($request);

            $total = (int) $request->total_copies;
            $available = $request->filled('available_copies') ? (int) $request->available_copies : $total;

            $book = Book::create([
                'branch_id' => $branchId,
                'school_id' => $schoolId,
                'title' => strip_tags($request->title),
                'author' => strip_tags($request->author),
                'isbn' => $request->isbn,
                'category' => strip_tags($request->category),
                'publisher' => $request->publisher ? strip_tags($request->publisher) : null,
                'published_year' => $request->published_year,
                'language' => $request->language,
                'edition' => $request->edition,
                'pages' => $request->pages,
                'total_copies' => $total,
                'available_copies' => min($available, $total),
                'location' => $request->location,
                'description' => $request->description ? strip_tags($request->description) : null,
                'is_active' => $request->boolean('is_active', true),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => $book->load('branch'),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Create book error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to create book', $e);
        }
    }

    public function show(Request $request, string $id)
    {
        try {
            $book = Book::withoutTenantScope()->with('branch')->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $book->branch_id)) {
                return $this->forbidden();
            }

            return response()->json(['success' => true, 'data' => $book, 'message' => 'Book retrieved successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Book not found'], 404);
        }
    }

    public function update(StoreBookRequest $request, string $id)
    {
        try {
            $book = Book::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $book->branch_id)) {
                return $this->forbidden();
            }

            $data = $request->only([
                'title', 'author', 'isbn', 'category', 'publisher', 'published_year',
                'language', 'edition', 'pages', 'total_copies', 'available_copies',
                'location', 'description', 'is_active',
            ]);

            // Keep available_copies consistent when total changes.
            if (array_key_exists('total_copies', $data)) {
                $issued = (int) $book->total_copies - (int) $book->available_copies;
                $newTotal = (int) $data['total_copies'];
                if (!array_key_exists('available_copies', $data)) {
                    $data['available_copies'] = max(0, $newTotal - $issued);
                }
            }

            $book->update($data);

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => $book->fresh('branch'),
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Book not found'], 404);
        } catch (\Exception $e) {
            Log::error('Update book error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to update book', $e);
        }
    }

    public function destroy(Request $request, string $id)
    {
        try {
            $book = Book::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $book->branch_id)) {
                return $this->forbidden();
            }

            if ($book->issues()->where('status', 'Issued')->exists()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete a book that has active issues',
                ], 422);
            }

            $book->delete();

            return response()->json(['success' => true, 'message' => 'Book deleted successfully']);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Book not found'], 404);
        } catch (\Exception $e) {
            Log::error('Delete book error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to delete book', $e);
        }
    }

    /**
     * Issue (check out) a book to a student or teacher.
     */
    public function issueBook(IssueBookRequest $request, string $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $book = Book::withoutTenantScope()->lockForUpdate()->findOrFail($id);
                if (!$this->canAccessBranch($request, (int) $book->branch_id)) {
                    return $this->forbidden();
                }

                if ((int) $book->available_copies <= 0) {
                    return response()->json(['success' => false, 'message' => 'No copies available to issue'], 422);
                }

                $borrowerType = $request->borrower_type;
                $memberId = (int) $request->member_id;
                $rules = self::LOAN_RULES[$borrowerType] ?? self::LOAN_RULES['Student'];
                $memberColumn = $borrowerType === 'Teacher' ? 'teacher_id' : 'student_id';

                // Same member cannot hold the same book twice.
                $already = BookIssue::withoutTenantScope()
                    ->where('book_id', $book->id)
                    ->where($memberColumn, $memberId)
                    ->where('status', 'Issued')
                    ->exists();
                if ($already) {
                    return response()->json(['success' => false, 'message' => 'This member already has this book issued'], 422);
                }

                // Enforce the per-borrower-type max book limit (branch-scoped).
                $activeCount = BookIssue::withoutTenantScope()
                    ->where($memberColumn, $memberId)
                    ->where('branch_id', $book->branch_id)
                    ->where('status', 'Issued')
                    ->count();
                if ($activeCount >= $rules['max']) {
                    return response()->json([
                        'success' => false,
                        'message' => "Borrowing limit reached ({$rules['max']} books for {$borrowerType}).",
                    ], 422);
                }

                $issueDate = $request->issue_date ? Carbon::parse($request->issue_date) : Carbon::today();
                $dueDate = $request->due_date
                    ? Carbon::parse($request->due_date)
                    : (clone $issueDate)->addDays($rules['days']);

                $issue = BookIssue::create([
                    'book_id' => $book->id,
                    'branch_id' => $book->branch_id,
                    'school_id' => $book->school_id,
                    'academic_year_id' => $this->academicYearContext->id(false),
                    'student_id' => $borrowerType === 'Student' ? $memberId : null,
                    'teacher_id' => $borrowerType === 'Teacher' ? $memberId : null,
                    'borrower_type' => $borrowerType,
                    'issue_date' => $issueDate->toDateString(),
                    'due_date' => $dueDate->toDateString(),
                    'status' => 'Issued',
                    'fine_amount' => 0,
                    'remarks' => $request->remarks ? strip_tags($request->remarks) : null,
                ]);

                $book->decrement('available_copies');

                return response()->json([
                    'success' => true,
                    'message' => 'Book issued successfully',
                    'data' => $issue->load(['book', 'branch']),
                ], 201);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Book not found'], 404);
        } catch (\Exception $e) {
            Log::error('Issue book error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to issue book', $e);
        }
    }

    /**
     * Return an issued book; compute the late fine from the loan rules.
     */
    public function returnBook(Request $request, string $id)
    {
        try {
            return DB::transaction(function () use ($request, $id) {
                $issue = BookIssue::withoutTenantScope()->with('book')->findOrFail($id);
                if (!$this->canAccessBranch($request, (int) $issue->branch_id)) {
                    return $this->forbidden();
                }

                if ($issue->status === 'Returned') {
                    return response()->json(['success' => false, 'message' => 'Book already returned'], 422);
                }

                $returnDate = $request->return_date ? Carbon::parse($request->return_date) : Carbon::today();
                $dueDate = Carbon::parse($issue->due_date);

                $fine = 0;
                if ($returnDate->gt($dueDate)) {
                    $daysLate = $dueDate->diffInDays($returnDate);
                    $rules = self::LOAN_RULES[$issue->borrower_type] ?? self::LOAN_RULES['Student'];
                    $fine = $daysLate * $rules['fine_per_day'];
                }

                // Collect the fine now unless the caller explicitly declines.
                $collectFine = $fine > 0 && $request->boolean('collect_fine', true);

                $issue->update([
                    'return_date' => $returnDate->toDateString(),
                    'status' => 'Returned',
                    'fine_amount' => $fine,
                    'fine_paid' => $collectFine,
                    'fine_paid_at' => $collectFine ? now() : null,
                ]);

                if ($issue->book) {
                    $issue->book->increment('available_copies');
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Book returned successfully',
                    'data' => $issue->fresh(['book', 'branch']),
                    'fine_amount' => $fine,
                    'fine_paid' => $collectFine,
                ]);
            });
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Issue not found'], 404);
        } catch (\Exception $e) {
            Log::error('Return book error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to return book', $e);
        }
    }

    public function getActiveIssues(Request $request)
    {
        return $this->issuesList($request, false);
    }

    public function getOverdueIssues(Request $request)
    {
        return $this->issuesList($request, true);
    }

    /**
     * Full borrowing history for one book (current holders + past loans).
     * Branch-guarded; returns the complete list (a single book's history is bounded).
     */
    public function getBookHistory(Request $request, string $id)
    {
        try {
            $book = Book::withoutTenantScope()->findOrFail($id);
            if (!$this->canAccessBranch($request, (int) $book->branch_id)) {
                return $this->forbidden();
            }

            $issues = DB::table('book_issues')
                ->leftJoin('users as m', DB::raw('COALESCE(book_issues.student_id, book_issues.teacher_id)'), '=', 'm.id')
                ->where('book_issues.book_id', $book->id)
                ->orderByDesc('book_issues.issue_date')
                ->orderByDesc('book_issues.id')
                ->select(
                    'book_issues.id',
                    'book_issues.borrower_type',
                    'book_issues.issue_date',
                    'book_issues.due_date',
                    'book_issues.return_date',
                    'book_issues.status',
                    'book_issues.fine_amount',
                    'book_issues.fine_paid',
                    DB::raw("CONCAT(m.first_name, ' ', m.last_name) as member_name"),
                    'm.email as member_email'
                )
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Book history retrieved successfully',
                'data' => $issues,
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Book not found'], 404);
        } catch (\Exception $e) {
            Log::error('Get book history error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to fetch book history', $e);
        }
    }

    /**
     * Shared list for active / overdue issues (branch + school scoped, paginated).
     */
    private function issuesList(Request $request, bool $overdueOnly)
    {
        try {
            $query = DB::table('book_issues')
                ->leftJoin('books', 'book_issues.book_id', '=', 'books.id')
                ->leftJoin('branches', 'book_issues.branch_id', '=', 'branches.id')
                ->leftJoin('users as m', DB::raw('COALESCE(book_issues.student_id, book_issues.teacher_id)'), '=', 'm.id')
                ->where('book_issues.status', 'Issued')
                ->select(
                    'book_issues.*',
                    'books.title as book_title',
                    'books.author as book_author',
                    'branches.name as branch_name',
                    DB::raw("CONCAT(m.first_name, ' ', m.last_name) as member_name"),
                    'm.email as member_email'
                );

            $schoolId = $this->getCurrentSchoolId($request);
            if ($schoolId) {
                $query->where(function ($q) use ($schoolId) {
                    $q->where('book_issues.school_id', $schoolId)->orWhereNull('book_issues.school_id');
                });
            }

            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds !== 'all') {
                if (!empty($accessibleBranchIds)) {
                    $query->whereIn('book_issues.branch_id', $accessibleBranchIds);
                } else {
                    $query->whereRaw('1 = 0');
                }
            }

            if ($overdueOnly) {
                $query->whereDate('book_issues.due_date', '<', Carbon::today());
            }
            if ($request->filled('branch_id')) {
                $query->where('book_issues.branch_id', (int) $request->branch_id);
            }

            $sortable = ['book_issues.due_date', 'book_issues.issue_date', 'books.title', 'member_name'];
            $issues = $this->paginateAndSort($query, $request, $sortable, 'book_issues.due_date', 'asc');

            return response()->json([
                'success' => true,
                'message' => 'Issues retrieved successfully',
                'data' => $issues->items(),
                'meta' => [
                    'current_page' => $issues->currentPage(),
                    'per_page' => $issues->perPage(),
                    'total' => $issues->total(),
                    'last_page' => $issues->lastPage(),
                    'from' => $issues->firstItem(),
                    'to' => $issues->lastItem(),
                    'has_more_pages' => $issues->hasMorePages(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get issues error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to fetch issues', $e);
        }
    }

    /**
     * All issues for a given member (user id).
     */
    public function getStudentIssues(Request $request, string $studentId)
    {
        try {
            $issues = BookIssue::with(['book'])
                ->where(function ($q) use ($studentId) {
                    $q->where('student_id', $studentId)->orWhere('teacher_id', $studentId);
                })
                ->orderBy('issue_date', 'desc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $issues,
                'statistics' => [
                    'active' => $issues->where('status', 'Issued')->count(),
                    'returned' => $issues->where('status', 'Returned')->count(),
                    'overdue' => $issues->where('status', 'Issued')
                        ->filter(fn ($i) => Carbon::parse($i->due_date)->lt(Carbon::today()))->count(),
                    'total' => $issues->count(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Get student issues error', ['error' => $e->getMessage()]);
            return $this->serverError('Failed to fetch member issues', $e);
        }
    }

    private function forbidden()
    {
        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this branch',
        ], 403);
    }

    private function serverError(string $message, \Exception $e)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
        ], 500);
    }
}
