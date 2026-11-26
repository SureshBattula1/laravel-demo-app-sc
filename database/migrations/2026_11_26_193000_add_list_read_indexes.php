<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('notifications') && ! Schema::hasIndex('notifications', 'notifications_user_id_read_at_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->index(['user_id', 'read_at'], 'notifications_user_id_read_at_index');
            });
        }

        if (Schema::hasTable('exam_schedules') && ! Schema::hasIndex('exam_schedules', 'exam_schedules_exam_id_exam_date_index')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->index(['exam_id', 'exam_date'], 'exam_schedules_exam_id_exam_date_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('notifications') && Schema::hasIndex('notifications', 'notifications_user_id_read_at_index')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropIndex('notifications_user_id_read_at_index');
            });
        }

        if (Schema::hasTable('exam_schedules') && Schema::hasIndex('exam_schedules', 'exam_schedules_exam_id_exam_date_index')) {
            Schema::table('exam_schedules', function (Blueprint $table) {
                $table->dropIndex('exam_schedules_exam_id_exam_date_index');
            });
        }
    }
};
