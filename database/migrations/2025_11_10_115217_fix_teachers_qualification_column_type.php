<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('teachers', 'qualification')) {
            return;
        }

        // Change qualification from JSON to TEXT column (driver-portable).
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            // SQLite stores JSON as TEXT and has no MODIFY COLUMN; nothing to change.
            return;
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->text('qualification')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('teachers', 'qualification')) {
            return;
        }

        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('teachers', function (Blueprint $table) {
            $table->json('qualification')->nullable()->change();
        });
    }
};
