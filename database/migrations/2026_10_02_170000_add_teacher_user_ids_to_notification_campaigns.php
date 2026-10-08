<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_campaigns', 'teacher_user_ids')) {
                $table->json('teacher_user_ids')->nullable()->after('staff_template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('notification_campaigns', 'teacher_user_ids')) {
                $table->dropColumn('teacher_user_ids');
            }
        });
    }
};
