<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Book;
use App\Models\LibraryProcurement;
use App\Models\LibraryProcurementItem;
use App\Services\Library\LibraryInventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LibraryProcurementController extends Controller
{
    use PaginatesAndSorts;

    public function __construct(
        protected LibraryInventoryService $inventoryService
    ) {}

    public function index(Request $request)
    {
        $query = LibraryProcurement::with(['creator:id,first_name,last_name'])->withCount('items');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn ($q) => $q->where('po_number', 'like', "%{$s}%")->orWhere('vendor_name', 'like', "%{$s}%"));
        }

        if ($request->filled('branch_id')) {
            $query->where('branch_id', (int) $request->branch_id);
        } else {
            $branches = $this->getAccessibleBranchIds($request);
            if ($branches !== 'all' && ! empty($branches)) {
                $query->whereIn('branch_id', $branches);
            }
        }

        $procurements = $this->paginateAndSort($query, $request, ['po_number', 'order_date', 'total_amount', 'status'], 'order_date', 'desc');

        return response()->json([
            'success' => true,
            'data' => $procurements->items(),
            'meta' => [
                'current_page' => $procurements->currentPage(),
                'per_page' => $procurements->perPage(),
                'total' => $procurements->total(),
                'last_page' => $procurements->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'po_number' => 'required|string|max:100',
            'vendor_name' => 'required|string|max:255',
            'vendor_contact' => 'nullable|string|max:255',
            'order_date' => 'required|date',
            'delivery_date' => 'nullable|date',
            'remarks' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.title' => 'required|string|max:255',
            'items.*.author' => 'nullable|string|max:255',
            'items.*.isbn' => 'nullable|string|max:50',
            'items.*.publisher' => 'nullable|string|max:255',
            'items.*.quantity_ordered' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.book_id' => 'nullable|exists:books,id',
        ]);

        return DB::transaction(function () use ($validated, $request) {
            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += ((int) $item['quantity_ordered'] * (float) $item['unit_price']);
            }

            $procurement = LibraryProcurement::create([
                'school_id' => $this->getCurrentSchoolId($request),
                'branch_id' => (int) $validated['branch_id'],
                'po_number' => $validated['po_number'],
                'vendor_name' => $validated['vendor_name'],
                'vendor_contact' => $validated['vendor_contact'] ?? null,
                'order_date' => $validated['order_date'],
                'delivery_date' => $validated['delivery_date'] ?? null,
                'total_amount' => $totalAmount,
                'status' => 'Ordered',
                'created_by' => $request->user()->id,
                'remarks' => $validated['remarks'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                LibraryProcurementItem::create([
                    'procurement_id' => $procurement->id,
                    'book_id' => $item['book_id'] ?? null,
                    'title' => $item['title'],
                    'author' => $item['author'] ?? null,
                    'isbn' => $item['isbn'] ?? null,
                    'publisher' => $item['publisher'] ?? null,
                    'quantity_ordered' => (int) $item['quantity_ordered'],
                    'quantity_received' => 0,
                    'unit_price' => (float) $item['unit_price'],
                    'total_price' => (int) $item['quantity_ordered'] * (float) $item['unit_price'],
                    'accession_status' => 'Pending',
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Procurement order created successfully',
                'data' => $procurement->load('items'),
            ], 201);
        });
    }

    public function show(string $id)
    {
        $procurement = LibraryProcurement::with(['items.book', 'creator', 'branch'])->findOrFail($id);

        return response()->json(['success' => true, 'data' => $procurement]);
    }

    /**
     * Mark Received & Auto-Generate Accession Copies.
     */
    public function receiveAndAccession(Request $request, string $id)
    {
        $procurement = LibraryProcurement::with('items')->findOrFail($id);

        return DB::transaction(function () use ($procurement, $request) {
            $invoiceNumber = $request->get('invoice_number', 'INV-'.time());
            $shelfId = $request->get('shelf_id');

            foreach ($procurement->items as $item) {
                // If book_id doesn't exist, create or find Book
                $book = null;
                if ($item->book_id) {
                    $book = Book::withoutTenantScope()->find($item->book_id);
                }

                if (! $book) {
                    $book = Book::create([
                        'school_id' => $procurement->school_id,
                        'branch_id' => $procurement->branch_id,
                        'title' => $item->title,
                        'author' => $item->author ?: 'Unknown',
                        'isbn' => $item->isbn ?: 'ISBN-'.uniqid(),
                        'publisher' => $item->publisher,
                        'category' => 'General',
                        'language' => 'English',
                        'total_copies' => 0,
                        'available_copies' => 0,
                    ]);
                    $item->update(['book_id' => $book->id]);
                }

                $qty = (int) $item->quantity_ordered;
                $this->inventoryService->batchCreateCopies(
                    $book->id,
                    $qty,
                    $shelfId ? (int) $shelfId : null,
                    (float) $item->unit_price,
                    now()->toDateString(),
                    $procurement->vendor_name,
                    'New'
                );

                $item->update([
                    'quantity_received' => $qty,
                    'accession_status' => 'Generated',
                ]);
            }

            $procurement->update([
                'status' => 'Received',
                'invoice_number' => $invoiceNumber,
                'delivery_date' => now()->toDateString(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Procurement received and copies accessioned successfully',
                'data' => $procurement->fresh('items'),
            ]);
        });
    }
}
