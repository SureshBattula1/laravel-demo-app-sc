<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('promotion_batches')) {
            Schema::table('promotion_batches', function (Blueprint $table) {
                if (! Schema::hasColumn('promotion_batches', 'batch_id')) {
                    $table->string('batch_id', 64)->nullable()->unique()->after('id');
                }
                if (! Schema::hasColumn('promotion_batches', 'to_section')) {
                    $table->string('to_section', 50)->nullable()->after('from_section');
                }
                if (! Schema::hasColumn('promotion_batches', 'retained_count')) {
                    $table->integer('retained_count')->default(0)->after('promoted_count');
                }
                if (! Schema::hasColumn('promotion_batches', 'reverted_count')) {
                    $table->integer('reverted_count')->default(0)->after('retained_count');
                }
                if (! Schema::hasColumn('promotion_batches', 'executed_by')) {
                    $table->foreignId('executed_by')->nullable()->constrained('users')->onDelete('set null')->after('status');
                }
            });

            DB::statement("ALTER TABLE `promotion_batches` MODIFY COLUMN `status` VARCHAR(50) NOT NULL DEFAULT 'completed'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('promotion_batches')) {
            Schema::table('promotion_batches', function (Blueprint $table) {
                if (Schema::hasColumn('promotion_batches', 'batch_id')) {
                    $table->dropColumn('batch_id');
                }
                if (Schema::hasColumn('promotion_batches', 'to_section')) {
                    $table->dropColumn('to_section');
                }
                if (Schema::hasColumn('promotion_batches', 'retained_count')) {
                    $table->dropColumn('retained_count');
                }
                if (Schema::hasColumn('promotion_batches', 'reverted_count')) {
                    $table->dropColumn('reverted_count');
                }
            });
        }
    }
};
