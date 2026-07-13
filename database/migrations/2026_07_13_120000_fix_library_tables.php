<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns the library tables with the multi-tenant conventions:
 * - adds school_id to books + book_issues, academic_year_id to book_issues
 * - fixes the global unique on books.isbn (breaks multi-branch: the same ISBN
 *   can legitimately exist in more than one branch) -> unique per branch instead
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (!Schema::hasColumn('books', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('branch_id')
                    ->constrained('schools')->nullOnDelete();
                $table->index(['school_id', 'is_active']);
            }
        });

        // Replace the global unique isbn with a per-branch unique; allow null.
        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique('books_isbn_unique');
        });
        Schema::table('books', function (Blueprint $table) {
            $table->string('isbn')->nullable()->change();
            $table->unique(['branch_id', 'isbn']);
        });

        Schema::table('book_issues', function (Blueprint $table) {
            if (!Schema::hasColumn('book_issues', 'school_id')) {
                $table->foreignId('school_id')->nullable()->after('branch_id')
                    ->constrained('schools')->nullOnDelete();
                $table->index('school_id');
            }
            if (!Schema::hasColumn('book_issues', 'academic_year_id')) {
                $table->unsignedBigInteger('academic_year_id')->nullable()->after('school_id');
                $table->index('academic_year_id');
            }
        });

        // Backfill school_id from the branch (defensive; tables are empty today).
        // Portable across MySQL/sqlite: resolve branch->school in PHP.
        $branchSchool = DB::table('branches')->pluck('school_id', 'id');
        foreach (['books', 'book_issues'] as $table) {
            DB::table($table)->whereNull('school_id')->whereNotNull('branch_id')
                ->select('id', 'branch_id')->orderBy('id')->chunk(500, function ($rows) use ($table, $branchSchool) {
                    foreach ($rows as $row) {
                        if (isset($branchSchool[$row->branch_id])) {
                            DB::table($table)->where('id', $row->id)->update(['school_id' => $branchSchool[$row->branch_id]]);
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            if (Schema::hasColumn('book_issues', 'academic_year_id')) {
                $table->dropIndex(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            }
            if (Schema::hasColumn('book_issues', 'school_id')) {
                $table->dropConstrainedForeignId('school_id');
            }
        });

        Schema::table('books', function (Blueprint $table) {
            $table->dropUnique(['branch_id', 'isbn']);
            $table->unique('isbn');
            if (Schema::hasColumn('books', 'school_id')) {
                $table->dropIndex(['school_id', 'is_active']);
                $table->dropConstrainedForeignId('school_id');
            }
        });
    }
};
