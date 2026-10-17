<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add 'reverted' status to import_histories
     */
    public function up(): void
    {
        // For MySQL, alter the enum column to include 'reverted'
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE import_histories MODIFY COLUMN status ENUM('uploaded', 'validating', 'validated', 'importing', 'completed', 'failed', 'cancelled', 'reverted') NOT NULL DEFAULT 'uploaded'");
        }

        Schema::table('import_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('import_histories', 'reverted_by')) {
                $table->foreignId('reverted_by')->nullable()->after('uploaded_by')->constrained('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('import_histories', 'reverted_at')) {
                $table->timestamp('reverted_at')->nullable()->after('import_completed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE import_histories MODIFY COLUMN status ENUM('uploaded', 'validating', 'validated', 'importing', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'uploaded'");
        }

        Schema::table('import_histories', function (Blueprint $table) {
            if (Schema::hasColumn('import_histories', 'reverted_by')) {
                $table->dropForeign(['reverted_by']);
                $table->dropColumn('reverted_by');
            }
            if (Schema::hasColumn('import_histories', 'reverted_at')) {
                $table->dropColumn('reverted_at');
            }
        });
    }
};
