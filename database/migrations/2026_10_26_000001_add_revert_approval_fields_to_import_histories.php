<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            if (! Schema::hasColumn('import_histories', 'revert_approval_status')) {
                $table->string('revert_approval_status', 32)->default('none')->after('reverted_at');
            }
            if (! Schema::hasColumn('import_histories', 'revert_requested_by')) {
                $table->foreignId('revert_requested_by')->nullable()->after('revert_approval_status')->constrained('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('import_histories', 'revert_requested_at')) {
                $table->timestamp('revert_requested_at')->nullable()->after('revert_requested_by');
            }
            if (! Schema::hasColumn('import_histories', 'revert_reason')) {
                $table->text('revert_reason')->nullable()->after('revert_requested_at');
            }
            if (! Schema::hasColumn('import_histories', 'revert_approved_by')) {
                $table->foreignId('revert_approved_by')->nullable()->after('revert_reason')->constrained('users')->onDelete('set null');
            }
            if (! Schema::hasColumn('import_histories', 'revert_approved_at')) {
                $table->timestamp('revert_approved_at')->nullable()->after('revert_approved_by');
            }
            if (! Schema::hasColumn('import_histories', 'revert_rejection_reason')) {
                $table->text('revert_rejection_reason')->nullable()->after('revert_approved_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('import_histories', function (Blueprint $table) {
            if (Schema::hasColumn('import_histories', 'revert_approved_by')) {
                $table->dropForeign(['revert_approved_by']);
                $table->dropColumn('revert_approved_by');
            }
            if (Schema::hasColumn('import_histories', 'revert_requested_by')) {
                $table->dropForeign(['revert_requested_by']);
                $table->dropColumn('revert_requested_by');
            }
            $columnsToDrop = [
                'revert_approval_status',
                'revert_requested_at',
                'revert_reason',
                'revert_approved_at',
                'revert_rejection_reason',
            ];
            foreach ($columnsToDrop as $col) {
                if (Schema::hasColumn('import_histories', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
