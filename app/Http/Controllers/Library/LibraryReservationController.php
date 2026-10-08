<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Book;
use App\Models\LibraryReservation;
use Illuminate\Http\Request;

class LibraryReservationController extends Controller
{
    use PaginatesAndSorts;

    public function index(Request $request)
    {
        $query = LibraryReservation::with(['book:id,title,author,isbn,cover_image', 'copy:id,barcode,accession_number', 'member:id,first_name,last_name,email,role']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('book_id')) {
            $query->where('book_id', (int) $request->book_id);
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

        $reservations = $this->paginateAndSort($query, $request, ['reserved_at', 'hold_until', 'status'], 'reserved_at', 'asc');

        return response()->json([
            'success' => true,
            'data' => $reservations->items(),
            'meta' => [
                'current_page' => $reservations->currentPage(),
                'per_page' => $reservations->perPage(),
                'total' => $reservations->total(),
                'last_page' => $reservations->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'book_id' => 'required|exists:books,id',
            'member_id' => 'required|exists:users,id',
            'borrower_type' => 'required|in:Student,Teacher',
            'notes' => 'nullable|string',
        ]);

        $book = Book::withoutTenantScope()->findOrFail($validated['book_id']);

        // Check if member already has active hold on this book
        $existing = LibraryReservation::withoutTenantScope()
            ->where('book_id', $book->id)
            ->where('member_id', (int) $validated['member_id'])
            ->whereIn('status', ['Pending', 'Ready for Pickup'])
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Member already has an active hold/reservation for this book',
            ], 422);
        }

        $reservation = LibraryReservation::create([
            'school_id' => $this->getCurrentSchoolId($request),
            'branch_id' => (int) $validated['branch_id'],
            'book_id' => $book->id,
            'member_id' => (int) $validated['member_id'],
            'borrower_type' => $validated['borrower_type'],
            'reserved_at' => now(),
            'status' => 'Pending',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Reservation placed successfully',
            'data' => $reservation->load(['book', 'member']),
        ], 201);
    }

    public function cancel(string $id)
    {
        $res = LibraryReservation::findOrFail($id);
        $res->update(['status' => 'Cancelled']);

        return response()->json(['success' => true, 'message' => 'Reservation cancelled successfully']);
    }

    public function fulfill(string $id)
    {
        $res = LibraryReservation::findOrFail($id);
        $res->update(['status' => 'Fulfilled']);

        return response()->json(['success' => true, 'message' => 'Reservation fulfilled successfully']);
    }
}
