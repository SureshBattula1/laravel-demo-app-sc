<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaign_recipients', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_campaign_recipients', 'context')) {
                $table->json('context')->nullable()->after('status_key');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaign_recipients', function (Blueprint $table) {
            if (Schema::hasColumn('notification_campaign_recipients', 'context')) {
                $table->dropColumn('context');
            }
        });
    }
};
