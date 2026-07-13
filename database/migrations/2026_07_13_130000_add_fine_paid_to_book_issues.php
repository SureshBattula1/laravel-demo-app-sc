<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track late-fine collection on book returns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            if (!Schema::hasColumn('book_issues', 'fine_paid')) {
                $table->boolean('fine_paid')->default(false)->after('fine_amount');
            }
            if (!Schema::hasColumn('book_issues', 'fine_paid_at')) {
                $table->timestamp('fine_paid_at')->nullable()->after('fine_paid');
            }
        });
    }

    public function down(): void
    {
        Schema::table('book_issues', function (Blueprint $table) {
            if (Schema::hasColumn('book_issues', 'fine_paid_at')) {
                $table->dropColumn('fine_paid_at');
            }
            if (Schema::hasColumn('book_issues', 'fine_paid')) {
                $table->dropColumn('fine_paid');
            }
        });
    }
};
