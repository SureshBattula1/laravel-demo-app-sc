<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->string('fee_type', 64)->nullable()->after('exam_id');
            $table->index(
                ['module', 'branch_id', 'event_date', 'fee_type'],
                'nc_module_branch_date_fee_type_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->dropIndex('nc_module_branch_date_fee_type_idx');
            $table->dropColumn('fee_type');
        });
    }
};
