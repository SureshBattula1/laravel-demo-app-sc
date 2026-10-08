<?php

namespace App\Services\Library;

use App\Models\Book;
use App\Models\LibraryBookCopy;
use App\Models\LibraryShelf;
use Illuminate\Support\Facades\DB;

class LibraryInventoryService
{
    /**
     * Generate the next accession number for a branch.
     */
    public function generateNextAccessionNumber(int $branchId, int $bookId): string
    {
        $maxCopy = LibraryBookCopy::withoutTenantScope()
            ->where('branch_id', $branchId)
            ->where('book_id', $bookId)
            ->max('copy_number') ?? 0;

        $nextCopy = $maxCopy + 1;

        return sprintf('ACC-%d-%d-%03d', $branchId, $bookId, $nextCopy);
    }

    /**
     * Generate barcode string for a copy.
     */
    public function generateBarcode(int $branchId, int $bookId, int $copyNumber): string
    {
        return sprintf('BC%d%d%03d', $branchId, $bookId, $copyNumber);
    }

    /**
     * Batch create book copies for a title.
     */
    public function batchCreateCopies(
        int $bookId,
        int $count,
        ?int $shelfId = null,
        ?float $price = null,
        ?string $purchaseDate = null,
        ?string $vendorName = null,
        ?string $condition = 'Good',
        ?string $barcodePrefix = 'BC',
        ?string $accessionPrefix = 'ACC-',
        ?array $customCopies = null
    ): array {
        return DB::transaction(function () use (
            $bookId, $count, $shelfId, $price, $purchaseDate, $vendorName, $condition, $barcodePrefix, $accessionPrefix, $customCopies
        ) {
            $book = Book::withoutTenantScope()->lockForUpdate()->findOrFail($bookId);
            $branchId = (int) $book->branch_id;
            $schoolId = $book->school_id;

            $maxCopy = LibraryBookCopy::withoutTenantScope()
                ->where('branch_id', $branchId)
                ->where('book_id', $bookId)
                ->max('copy_number') ?? 0;

            $createdCopies = [];
            $bcPrefix = trim((string) ($barcodePrefix ?: 'BC'));
            $accPrefix = trim((string) ($accessionPrefix ?: 'ACC-'));

            $actualCount = ($customCopies && count($customCopies) > 0) ? count($customCopies) : $count;

            for ($i = 1; $i <= $actualCount; $i++) {
                $copyNum = $maxCopy + $i;
                $custom = ($customCopies && isset($customCopies[$i - 1])) ? $customCopies[$i - 1] : null;

                $barcode = (! empty($custom['barcode']))
                    ? trim($custom['barcode'])
                    : sprintf('%s%d%d%03d', $bcPrefix, $branchId, $bookId, $copyNum);

                $accessionNumber = (! empty($custom['accession_number']))
                    ? trim($custom['accession_number'])
                    : sprintf('%s%d-%d-%03d', $accPrefix, $branchId, $bookId, $copyNum);

                $copyShelfId = ! empty($custom['shelf_id']) ? (int) $custom['shelf_id'] : $shelfId;
                $copyCondition = ! empty($custom['condition']) ? $custom['condition'] : ($condition ?: 'Good');
                $copyPrice = isset($custom['purchase_price']) && is_numeric($custom['purchase_price']) ? (float) $custom['purchase_price'] : $price;

                $copy = LibraryBookCopy::create([
                    'school_id' => $schoolId,
                    'branch_id' => $branchId,
                    'book_id' => $bookId,
                    'accession_number' => $accessionNumber,
                    'barcode' => $barcode,
                    'copy_number' => $copyNum,
                    'shelf_id' => $copyShelfId,
                    'condition' => $copyCondition,
                    'status' => 'Available',
                    'purchase_price' => $copyPrice,
                    'purchase_date' => $purchaseDate,
                    'vendor_name' => $vendorName,
                    'is_active' => true,
                ]);

                $createdCopies[] = $copy;
            }

            // Sync total and available copies counters on book
            $book->increment('total_copies', $actualCount);
            $book->increment('available_copies', $actualCount);

            return $createdCopies;
        });
    }

    /**
     * Get live occupancy statistics for all shelves in a branch.
     */
    public function getShelfOccupancy(int $branchId): array
    {
        return LibraryShelf::withoutTenantScope()
            ->where('branch_id', $branchId)
            ->withCount(['copies' => function ($q) {
                $q->whereNull('deleted_at')->where('status', '!=', 'Lost');
            }])
            ->get()
            ->map(function ($shelf) {
                $occupied = $shelf->copies_count;
                $capacity = max(1, (int) $shelf->capacity);
                $percentage = min(100, round(($occupied / $capacity) * 100, 1));

                return [
                    'id' => $shelf->id,
                    'code' => $shelf->code,
                    'floor' => $shelf->floor,
                    'room' => $shelf->room,
                    'rack_number' => $shelf->rack_number,
                    'shelf_number' => $shelf->shelf_number,
                    'capacity' => $capacity,
                    'occupied' => $occupied,
                    'percentage' => $percentage,
                    'is_full' => $occupied >= $capacity,
                ];
            })
            ->toArray();
    }
}
