<?php

namespace App\Http\Controllers\Library;

use App\Http\Controllers\Controller;
use App\Http\Traits\PaginatesAndSorts;
use App\Models\Book;
use App\Models\LibraryBookCopy;
use App\Models\LibraryShelf;
use App\Models\LibraryStockVerification;
use App\Models\LibraryStockVerificationItem;
use App\Services\Library\LibraryInventoryService;
use Illuminate\Http\Request;

class LibraryInventoryController extends Controller
{
    use PaginatesAndSorts;

    public function __construct(
        protected LibraryInventoryService $inventoryService
    ) {}

    // ================= BOOK COPIES =================
    public function getCopies(Request $request)
    {
        $query = LibraryBookCopy::with(['book:id,title,author,isbn,category', 'shelf:id,code,rack_number,shelf_number']);

        if ($request->filled('book_id')) {
            $query->where('book_id', (int) $request->book_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('condition')) {
            $query->where('condition', $request->condition);
        }
        if ($request->filled('shelf_id')) {
            $query->where('shelf_id', (int) $request->shelf_id);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('accession_number', 'like', "%{$s}%")
                    ->orWhere('barcode', 'like', "%{$s}%")
                    ->orWhereHas('book', fn ($bq) => $bq->where('title', 'like', "%{$s}%"));
            });
        }

        $this->applyBranchFilter($query, $request);

        $copies = $this->paginateAndSort($query, $request, ['accession_number', 'barcode', 'copy_number', 'status', 'condition', 'created_at'], 'copy_number', 'asc');

        return response()->json(['success' => true, 'data' => $copies->items(), 'meta' => $this->formatMeta($copies)]);
    }

    public function storeCopy(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'shelf_id' => 'nullable|exists:library_shelves,id',
            'condition' => 'nullable|in:New,Good,Fair,Damaged,Lost,Weeded',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'vendor_name' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',
        ]);

        $book = Book::withoutTenantScope()->findOrFail($validated['book_id']);
        $copies = $this->inventoryService->batchCreateCopies(
            $book->id,
            1,
            $validated['shelf_id'] ?? null,
            $validated['purchase_price'] ?? null,
            $validated['purchase_date'] ?? null,
            $validated['vendor_name'] ?? null,
            $validated['condition'] ?? 'Good'
        );

        return response()->json(['success' => true, 'message' => 'Book copy created successfully', 'data' => $copies[0]], 201);
    }

    public function batchGenerateCopies(Request $request)
    {
        $validated = $request->validate([
            'book_id' => 'required|exists:books,id',
            'count' => 'required|integer|min:1|max:200',
            'shelf_id' => 'nullable|exists:library_shelves,id',
            'purchase_price' => 'nullable|numeric|min:0',
            'purchase_date' => 'nullable|date',
            'vendor_name' => 'nullable|string|max:255',
            'condition' => 'nullable|in:New,Good,Fair,Damaged,Lost,Weeded',
            'barcode_prefix' => 'nullable|string|max:20',
            'accession_prefix' => 'nullable|string|max:20',
            'copies' => 'nullable|array',
            'copies.*.barcode' => 'nullable|string|max:100',
            'copies.*.accession_number' => 'nullable|string|max:100',
            'copies.*.shelf_id' => 'nullable|exists:library_shelves,id',
            'copies.*.condition' => 'nullable|in:New,Good,Fair,Damaged,Lost,Weeded',
            'copies.*.purchase_price' => 'nullable|numeric|min:0',
        ]);

        $copies = $this->inventoryService->batchCreateCopies(
            (int) $validated['book_id'],
            (int) $validated['count'],
            $validated['shelf_id'] ?? null,
            $validated['purchase_price'] ?? null,
            $validated['purchase_date'] ?? null,
            $validated['vendor_name'] ?? null,
            $validated['condition'] ?? 'Good',
            $validated['barcode_prefix'] ?? 'BC',
            $validated['accession_prefix'] ?? 'ACC-',
            $validated['copies'] ?? null
        );

        $num = count($copies);

        return response()->json(['success' => true, 'message' => "Generated {$num} copies successfully", 'data' => $copies], 201);
    }

    public function updateCopy(Request $request, string $id)
    {
        $copy = LibraryBookCopy::withoutTenantScope()->findOrFail($id);
        $validated = $request->validate([
            'shelf_id' => 'nullable|exists:library_shelves,id',
            'condition' => 'sometimes|in:New,Good,Fair,Damaged,Lost,Weeded',
            'status' => 'sometimes|in:Available,Issued,Reserved,Maintenance,Lost,Weeded',
            'remarks' => 'nullable|string',
        ]);

        $copy->update($validated);

        return response()->json(['success' => true, 'message' => 'Copy updated successfully', 'data' => $copy]);
    }

    public function destroyCopy(string $id)
    {
        $copy = LibraryBookCopy::withoutTenantScope()->findOrFail($id);
        if ($copy->status === 'Issued') {
            return response()->json(['success' => false, 'message' => 'Cannot delete a copy that is currently issued'], 422);
        }

        $copy->delete();
        $copy->book()->decrement('total_copies');
        if ($copy->status === 'Available') {
            $copy->book()->decrement('available_copies');
        }

        return response()->json(['success' => true, 'message' => 'Copy deleted successfully']);
    }

    // ================= SHELVES =================
    public function getShelves(Request $request)
    {
        $query = LibraryShelf::with(['category:id,name'])->withCount(['copies' => fn ($q) => $q->whereNull('deleted_at')]);
        $this->applyBranchFilter($query, $request);

        $shelves = $this->paginateAndSort($query, $request, ['code', 'rack_number', 'shelf_number', 'capacity', 'copies_count'], 'rack_number', 'asc');

        return response()->json(['success' => true, 'data' => $shelves->items(), 'meta' => $this->formatMeta($shelves)]);
    }

    public function storeShelf(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'rack_number' => 'required|string|max:50',
            'shelf_number' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'room' => 'nullable|string|max:50',
            'code' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'category_id' => 'nullable|exists:library_categories,id',
            'is_active' => 'boolean',
        ]);

        $code = $validated['code'] ?? ($validated['rack_number'].($validated['shelf_number'] ? '-'.$validated['shelf_number'] : ''));

        $shelf = LibraryShelf::create(array_merge($validated, [
            'school_id' => $this->getCurrentSchoolId($request),
            'code' => $code,
        ]));

        return response()->json(['success' => true, 'message' => 'Shelf created successfully', 'data' => $shelf], 201);
    }

    public function updateShelf(Request $request, string $id)
    {
        $shelf = LibraryShelf::findOrFail($id);
        $validated = $request->validate([
            'rack_number' => 'sometimes|required|string|max:50',
            'shelf_number' => 'nullable|string|max:50',
            'floor' => 'nullable|string|max:50',
            'room' => 'nullable|string|max:50',
            'code' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer|min:1',
            'category_id' => 'nullable|exists:library_categories,id',
            'is_active' => 'boolean',
        ]);

        $shelf->update($validated);

        return response()->json(['success' => true, 'message' => 'Shelf updated successfully', 'data' => $shelf]);
    }

    public function destroyShelf(string $id)
    {
        $shelf = LibraryShelf::findOrFail($id);
        if ($shelf->copies()->whereNull('deleted_at')->exists()) {
            return response()->json(['success' => false, 'message' => 'Cannot delete shelf holding active book copies'], 422);
        }
        $shelf->delete();

        return response()->json(['success' => true, 'message' => 'Shelf deleted successfully']);
    }

    // ================= STOCK VERIFICATION =================
    public function getStockAudits(Request $request)
    {
        $query = LibraryStockVerification::with(['verifier:id,first_name,last_name,email'])->withCount('items');
        $this->applyBranchFilter($query, $request);

        $audits = $this->paginateAndSort($query, $request, ['session_title', 'started_at', 'status', 'total_copies_checked', 'missing_count'], 'started_at', 'desc');

        return response()->json(['success' => true, 'data' => $audits->items(), 'meta' => $this->formatMeta($audits)]);
    }

    public function startStockAudit(Request $request)
    {
        $validated = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'session_title' => 'required|string|max:255',
            'notes' => 'nullable|string',
        ]);

        $audit = LibraryStockVerification::create([
            'school_id' => $this->getCurrentSchoolId($request),
            'branch_id' => (int) $validated['branch_id'],
            'session_title' => $validated['session_title'],
            'verified_by' => $request->user()->id,
            'started_at' => now(),
            'status' => 'In Progress',
            'notes' => $validated['notes'] ?? null,
        ]);

        return response()->json(['success' => true, 'message' => 'Stock verification started', 'data' => $audit], 201);
    }

    public function scanAuditBarcode(Request $request, string $id)
    {
        $audit = LibraryStockVerification::findOrFail($id);
        if ($audit->status !== 'In Progress') {
            return response()->json(['success' => false, 'message' => 'Audit session is already closed'], 422);
        }

        $validated = $request->validate([
            'barcode' => 'required|string',
            'shelf_id' => 'nullable|exists:library_shelves,id',
        ]);

        $barcode = trim($validated['barcode']);
        $copy = LibraryBookCopy::withoutTenantScope()
            ->where('branch_id', $audit->branch_id)
            ->where(fn ($q) => $q->where('barcode', $barcode)->orWhere('accession_number', $barcode))
            ->first();

        if (! $copy) {
            return response()->json(['success' => false, 'message' => "No copy found matching barcode/accession '{$barcode}'"], 404);
        }

        // Record scan
        $item = LibraryStockVerificationItem::updateOrCreate(
            ['verification_id' => $audit->id, 'copy_id' => $copy->id],
            [
                'scanned_barcode' => $copy->barcode,
                'shelf_id' => $validated['shelf_id'] ?? $copy->shelf_id,
                'status' => 'Found',
                'scanned_at' => now(),
            ]
        );

        $audit->increment('total_copies_checked');

        return response()->json(['success' => true, 'message' => 'Copy verified', 'data' => $item->load(['copy.book', 'shelf'])]);
    }

    public function completeStockAudit(string $id)
    {
        $audit = LibraryStockVerification::findOrFail($id);
        $totalCopies = LibraryBookCopy::withoutTenantScope()->where('branch_id', $audit->branch_id)->whereNull('deleted_at')->count();
        $scannedCount = $audit->items()->where('status', 'Found')->count();
        $missingCount = max(0, $totalCopies - $scannedCount);

        $audit->update([
            'status' => 'Completed',
            'completed_at' => now(),
            'missing_count' => $missingCount,
        ]);

        return response()->json(['success' => true, 'message' => 'Stock audit completed', 'data' => $audit]);
    }

    private function formatMeta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
