<?php

namespace App\Http\Controllers;

use App\Models\Book;
use App\Models\BookIssue;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;

class LibraryController extends Controller
{
    /**
     * Display a listing of books
     */
    public function index(Request $request)
    {
        try {
            $query = Book::with(['branch', 'category', 'creator']);

            if ($request->has('branch_id')) {
                $query->where('branch_id', $request->branch_id);
            }

            if ($request->has('category_id')) {
                $query->where('category_id', $request->category_id);
            }

            if ($request->has('search')) {
                $search = strip_tags($request->search);
                $query->where(function($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('author', 'like', "%{$search}%")
                      ->orWhere('isbn', 'like', "%{$search}%");
                });
            }

            if ($request->has('available_only') && $request->boolean('available_only')) {
                $query->where('available_copies', '>', 0);
            }

            $books = $query->orderBy('title', 'asc')->get();

            return response()->json([
                'success' => true,
                'data' => $books,
                'message' => 'Books retrieved successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Error fetching books: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error fetching books',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Store a newly created book
     */
    public function store(Request $request)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'branch_id' => 'required|exists:branches,id',
                'title' => 'required|string|max:255',
                'author' => 'required|string|max:255',
                'isbn' => 'nullable|string|max:50|unique:books',
                'publisher' => 'nullable|string|max:255',
                'publication_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
                'category_id' => 'nullable|exists:categories,id',
                'total_copies' => 'required|integer|min:1',
                'available_copies' => 'nullable|integer|min:0',
                'price' => 'nullable|numeric|min:0',
                'rack_number' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $book = Book::create([
                'branch_id' => $request->branch_id,
                'title' => strip_tags($request->title),
                'author' => strip_tags($request->author),
                'isbn' => $request->isbn,
                'publisher' => strip_tags($request->publisher ?? ''),
                'publication_year' => $request->publication_year,
                'category_id' => $request->category_id,
                'total_copies' => $request->total_copies,
                'available_copies' => $request->available_copies ?? $request->total_copies,
                'price' => $request->price,
                'rack_number' => $request->rack_number,
                'description' => strip_tags($request->description ?? ''),
                'is_active' => $request->is_active ?? true,
                'created_by' => $request->user()->id
            ]);

            DB::commit();

            Log::info('Book created', ['book_id' => $book->id]);

            return response()->json([
                'success' => true,
                'message' => 'Book created successfully',
                'data' => $book->load(['branch', 'creator'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Create book error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create book',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Display the specified book
     */
    public function show(string $id)
    {
        try {
            $book = Book::with(['branch', 'category', 'creator', 'issues.student'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $book,
                'message' => 'Book retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Book not found',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 404);
        }
    }

    /**
     * Update the specified book
     */
    public function update(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $book = Book::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'title' => 'string|max:255',
                'author' => 'string|max:255',
                'isbn' => 'nullable|string|max:50|unique:books,isbn,' . $id,
                'publisher' => 'nullable|string|max:255',
                'publication_year' => 'nullable|integer|min:1800|max:' . (date('Y') + 1),
                'category_id' => 'nullable|exists:categories,id',
                'total_copies' => 'integer|min:1',
                'available_copies' => 'nullable|integer|min:0',
                'price' => 'nullable|numeric|min:0',
                'rack_number' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:1000',
                'is_active' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $updateData = [];
            if ($request->has('title')) $updateData['title'] = strip_tags($request->title);
            if ($request->has('author')) $updateData['author'] = strip_tags($request->author);
            if ($request->has('isbn')) $updateData['isbn'] = $request->isbn;
            if ($request->has('publisher')) $updateData['publisher'] = strip_tags($request->publisher);
            if ($request->has('publication_year')) $updateData['publication_year'] = $request->publication_year;
            if ($request->has('category_id')) $updateData['category_id'] = $request->category_id;
            if ($request->has('total_copies')) $updateData['total_copies'] = $request->total_copies;
            if ($request->has('available_copies')) $updateData['available_copies'] = $request->available_copies;
            if ($request->has('price')) $updateData['price'] = $request->price;
            if ($request->has('rack_number')) $updateData['rack_number'] = $request->rack_number;
            if ($request->has('description')) $updateData['description'] = strip_tags($request->description);
            if ($request->has('is_active')) $updateData['is_active'] = $request->is_active;
            
            $updateData['updated_by'] = $request->user()->id;

            $book->update($updateData);

            DB::commit();

            Log::info('Book updated', ['book_id' => $book->id]);

            return response()->json([
                'success' => true,
                'message' => 'Book updated successfully',
                'data' => $book->load(['branch', 'creator'])
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Update book error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update book',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Remove the specified book
     */
    public function destroy(string $id)
    {
        DB::beginTransaction();
        try {
            $book = Book::findOrFail($id);
            
            // Check if book has active issues
            if ($book->issues()->where('status', 'Issued')->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete book with active issues'
                ], 422);
            }

            $book->delete();
            DB::commit();

            Log::info('Book deleted', ['book_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Book deleted successfully'
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Delete book error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete book',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Issue a book to a student
     */
    public function issueBook(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $validator = Validator::make($request->all(), [
                'student_id' => 'required|exists:users,id',
                'issue_date' => 'required|date',
                'due_date' => 'required|date|after:issue_date',
                'remarks' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'errors' => $validator->errors()
                ], 422);
            }

            $book = Book::findOrFail($id);

            // Check if book is available
            if ($book->available_copies <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Book is not available for issue'
                ], 422);
            }

            // Check if student already has this book
            $existingIssue = BookIssue::where('book_id', $id)
                ->where('student_id', $request->student_id)
                ->where('status', 'Issued')
                ->first();

            if ($existingIssue) {
                return response()->json([
                    'success' => false,
                    'message' => 'Student already has this book issued'
                ], 422);
            }

            $issue = BookIssue::create([
                'book_id' => $id,
                'student_id' => $request->student_id,
                'issue_date' => $request->issue_date,
                'due_date' => $request->due_date,
                'status' => 'Issued',
                'remarks' => strip_tags($request->remarks ?? ''),
                'issued_by' => $request->user()->id,
                'created_by' => $request->user()->id
            ]);

            // Decrease available copies
            $book->decrement('available_copies');

            DB::commit();

            Log::info('Book issued', ['issue_id' => $issue->id, 'book_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Book issued successfully',
                'data' => $issue->load(['book', 'student', 'issuedBy'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Issue book error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to issue book',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Return an issued book
     */
    public function returnBook(Request $request, string $id)
    {
        DB::beginTransaction();
        try {
            $issue = BookIssue::findOrFail($id);

            if ($issue->status === 'Returned') {
                return response()->json([
                    'success' => false,
                    'message' => 'Book already returned'
                ], 422);
            }

            $returnDate = $request->return_date ?? Carbon::now();
            $lateFee = 0;

            // Calculate late fee if returned after due date
            if (Carbon::parse($returnDate)->gt(Carbon::parse($issue->due_date))) {
                $daysLate = Carbon::parse($issue->due_date)->diffInDays(Carbon::parse($returnDate));
                $lateFee = $daysLate * 5; // $5 per day late
            }

            $issue->update([
                'return_date' => $returnDate,
                'status' => 'Returned',
                'late_fee' => $lateFee,
                'returned_by' => $request->user()->id,
                'updated_by' => $request->user()->id
            ]);

            // Increase available copies
            $issue->book->increment('available_copies');

            DB::commit();

            Log::info('Book returned', ['issue_id' => $id]);

            return response()->json([
                'success' => true,
                'message' => 'Book returned successfully',
                'data' => $issue->load(['book', 'student', 'returnedBy']),
                'late_fee' => $lateFee
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Return book error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to return book',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get active book issues
     */
    public function getActiveIssues()
    {
        try {
            $issues = BookIssue::with(['book', 'student', 'issuedBy'])
                ->where('status', 'Issued')
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $issues
            ]);

        } catch (\Exception $e) {
            Log::error('Get active issues error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active issues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get overdue book issues
     */
    public function getOverdueIssues()
    {
        try {
            $issues = BookIssue::with(['book', 'student', 'issuedBy'])
                ->where('status', 'Issued')
                ->where('due_date', '<', Carbon::now())
                ->orderBy('due_date', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $issues,
                'count' => $issues->count()
            ]);

        } catch (\Exception $e) {
            Log::error('Get overdue issues error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch overdue issues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }

    /**
     * Get book issues for a specific student
     */
    public function getStudentIssues(string $studentId)
    {
        try {
            $issues = BookIssue::with(['book', 'issuedBy', 'returnedBy'])
                ->where('student_id', $studentId)
                ->orderBy('issue_date', 'desc')
                ->get();

            $active = $issues->where('status', 'Issued')->count();
            $returned = $issues->where('status', 'Returned')->count();
            $overdue = $issues->where('status', 'Issued')
                ->filter(function($issue) {
                    return Carbon::parse($issue->due_date)->lt(Carbon::now());
                })->count();

            return response()->json([
                'success' => true,
                'data' => [
                    'issues' => $issues,
                    'statistics' => [
                        'active' => $active,
                        'returned' => $returned,
                        'overdue' => $overdue,
                        'total' => $issues->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Get student issues error', ['error' => $e->getMessage()]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch student issues',
                'error' => app()->environment('local') ? $e->getMessage() : 'Server error'
            ], 500);
        }
    }
}
