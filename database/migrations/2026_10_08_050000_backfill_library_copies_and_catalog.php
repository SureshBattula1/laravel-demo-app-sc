<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Normalize Categories, Authors, Publishers from existing books
        $books = DB::table('books')->whereNull('deleted_at')->get();

        foreach ($books as $book) {
            $branchId = $book->branch_id;
            $schoolId = $book->school_id;

            $categoryId = null;
            if (! empty($book->category)) {
                $categoryName = trim($book->category);
                $cat = DB::table('library_categories')
                    ->where('branch_id', $branchId)
                    ->where('name', $categoryName)
                    ->first();

                if (! $cat) {
                    $catCode = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $categoryName), 0, 4));
                    $categoryId = DB::table('library_categories')->insertGetId([
                        'school_id' => $schoolId,
                        'branch_id' => $branchId,
                        'name' => $categoryName,
                        'code' => $catCode ?: 'GEN',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $categoryId = $cat->id;
                }
            }

            $authorId = null;
            if (! empty($book->author)) {
                $authorName = trim($book->author);
                $aut = DB::table('library_authors')
                    ->where('branch_id', $branchId)
                    ->where('name', $authorName)
                    ->first();

                if (! $aut) {
                    $authorId = DB::table('library_authors')->insertGetId([
                        'school_id' => $schoolId,
                        'branch_id' => $branchId,
                        'name' => $authorName,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $authorId = $aut->id;
                }
            }

            $publisherId = null;
            if (! empty($book->publisher)) {
                $pubName = trim($book->publisher);
                $pub = DB::table('library_publishers')
                    ->where('branch_id', $branchId)
                    ->where('name', $pubName)
                    ->first();

                if (! $pub) {
                    $publisherId = DB::table('library_publishers')->insertGetId([
                        'school_id' => $schoolId,
                        'branch_id' => $branchId,
                        'name' => $pubName,
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $publisherId = $pub->id;
                }
            }

            // Update foreign keys on book
            DB::table('books')->where('id', $book->id)->update([
                'category_id' => $categoryId,
                'author_id' => $authorId,
                'publisher_id' => $publisherId,
            ]);

            // 2. Generate individual copies
            $totalCopies = (int) ($book->total_copies ?? 1);
            if ($totalCopies < 1) {
                $totalCopies = 1;
            }

            $activeIssues = DB::table('book_issues')
                ->where('book_id', $book->id)
                ->where('status', 'Issued')
                ->whereNull('copy_id')
                ->get();

            $activeIssueIndex = 0;

            for ($i = 1; $i <= $totalCopies; $i++) {
                $accessionNumber = sprintf('ACC-%d-%d-%03d', $branchId, $book->id, $i);
                $barcode = sprintf('BC%d%d%03d', $branchId, $book->id, $i);

                $isIssued = false;
                $linkedIssue = null;
                if ($activeIssueIndex < $activeIssues->count()) {
                    $linkedIssue = $activeIssues[$activeIssueIndex];
                    $isIssued = true;
                    $activeIssueIndex++;
                }

                $copyId = DB::table('library_book_copies')->insertGetId([
                    'school_id' => $schoolId,
                    'branch_id' => $branchId,
                    'book_id' => $book->id,
                    'accession_number' => $accessionNumber,
                    'barcode' => $barcode,
                    'copy_number' => $i,
                    'condition' => 'Good',
                    'status' => $isIssued ? 'Issued' : 'Available',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($linkedIssue) {
                    DB::table('book_issues')->where('id', $linkedIssue->id)->update([
                        'copy_id' => $copyId,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // Safe reversible cleanup if rolled back
        DB::table('library_book_copies')->truncate();
        DB::table('library_categories')->truncate();
        DB::table('library_authors')->truncate();
        DB::table('library_publishers')->truncate();
    }
};
