<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_id')->nullable()->after('branch_id');
            $table->index(['module', 'branch_id', 'event_date', 'exam_id'], 'nc_module_branch_date_exam_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            $table->dropIndex('nc_module_branch_date_exam_idx');
            $table->dropColumn('exam_id');
        });
    }
};
