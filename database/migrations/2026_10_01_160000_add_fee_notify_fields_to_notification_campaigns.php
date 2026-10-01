<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_campaigns', 'fee_type')) {
                $table->string('fee_type', 64)->nullable()->after('exam_id');
            }
            $table->string('fee_notify_mode', 16)->nullable()->after('fee_type');
            $table->string('fee_structure_id', 36)->nullable()->after('fee_notify_mode');
            $table->index(
                ['module', 'branch_id', 'fee_notify_mode', 'event_date', 'fee_type', 'fee_structure_id'],
                'nc_fees_scope_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->dropIndex('nc_fees_scope_idx');
            if (Schema::hasColumn('notification_campaigns', 'fee_structure_id')) {
                $table->dropColumn('fee_structure_id');
            }
            if (Schema::hasColumn('notification_campaigns', 'fee_notify_mode')) {
                $table->dropColumn('fee_notify_mode');
            }
        });
    }
};
