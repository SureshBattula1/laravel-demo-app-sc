<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (! Schema::hasColumn('notification_campaigns', 'academic_year')) {
                $table->string('academic_year', 32)->nullable()->after('fee_structure_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notification_campaigns', function (Blueprint $table) {
            if (Schema::hasColumn('notification_campaigns', 'academic_year')) {
                $table->dropColumn('academic_year');
            }
        });
    }
};
