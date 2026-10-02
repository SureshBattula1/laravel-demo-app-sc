<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_campaigns', 'staff_user_ids')) {
                $table->json('staff_user_ids')->nullable()->after('template_map');
            }
            if (! Schema::hasColumn('notification_campaigns', 'staff_template_id')) {
                $table->unsignedBigInteger('staff_template_id')->nullable()->after('staff_user_ids');
            }
            if (! Schema::hasColumn('notification_campaigns', 'staff_materialized_at')) {
                $table->timestamp('staff_materialized_at')->nullable()->after('staff_template_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            foreach (['staff_materialized_at', 'staff_template_id', 'staff_user_ids'] as $col) {
                if (Schema::hasColumn('notification_campaigns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
