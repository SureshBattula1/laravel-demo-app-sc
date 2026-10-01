<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (!Schema::hasColumn('notification_campaigns', 'expected_recipient_count')) {
                $table->unsignedInteger('expected_recipient_count')->default(0)->after('recipient_count');
            }
            if (!Schema::hasColumn('notification_campaigns', 'materialize_target_index')) {
                $table->unsignedSmallInteger('materialize_target_index')->default(0)->after('expected_recipient_count');
            }
        });

        Schema::table('notification_campaign_recipients', function (Blueprint $table) {
            $table->index(['campaign_id', 'delivery_status', 'id'], 'ncr_campaign_status_id_idx');
            $table->index(['campaign_id', 'user_id'], 'ncr_campaign_user_idx');
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaign_recipients', function (Blueprint $table) {
            $table->dropIndex('ncr_campaign_status_id_idx');
            $table->dropIndex('ncr_campaign_user_idx');
        });

        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('notification_campaigns', 'materialize_target_index')) {
                $table->dropColumn('materialize_target_index');
            }
            if (Schema::hasColumn('notification_campaigns', 'expected_recipient_count')) {
                $table->dropColumn('expected_recipient_count');
            }
        });
    }
};
