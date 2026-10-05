<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('transport_stops_master') && ! Schema::hasColumn('transport_stops_master', 'geofence_radius')) {
            Schema::table('transport_stops_master', function (Blueprint $table) {
                $table->integer('geofence_radius')->default(50)->after('longitude');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('transport_stops_master') && Schema::hasColumn('transport_stops_master', 'geofence_radius')) {
            Schema::table('transport_stops_master', function (Blueprint $table) {
                $table->dropColumn('geofence_radius');
            });
        }
    }
};
