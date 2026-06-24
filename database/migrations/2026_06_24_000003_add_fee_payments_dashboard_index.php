<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Composite index to back the fee dashboards (Today's Payments + payments list), which
 * filter by branch + payment_date range + payment_status.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fee_payments')) {
            return;
        }
        $exists = collect(DB::select('SHOW INDEX FROM fee_payments'))
            ->contains(fn ($i) => $i->Key_name === 'fee_payments_branch_date_status_index');
        if (!$exists) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $table->index(['branch_id', 'payment_date', 'payment_status'], 'fee_payments_branch_date_status_index');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('fee_payments')) {
            return;
        }
        $exists = collect(DB::select('SHOW INDEX FROM fee_payments'))
            ->contains(fn ($i) => $i->Key_name === 'fee_payments_branch_date_status_index');
        if ($exists) {
            Schema::table('fee_payments', function (Blueprint $table) {
                $table->dropIndex('fee_payments_branch_date_status_index');
            });
        }
    }
};
