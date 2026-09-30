<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('exam_schedules')) {
            return;
        }

        if (! Schema::hasColumn('exam_schedules', 'batch_uuid')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->uuid('batch_uuid')->nullable()->after('exam_id');
                $table->index('batch_uuid');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('exam_schedules') || ! Schema::hasColumn('exam_schedules', 'batch_uuid')) {
            return;
        }

        Schema::table('exam_schedules', function (Blueprint $table) {
            $table->dropIndex(['batch_uuid']);
            $table->dropColumn('batch_uuid');
        });
    }
};
