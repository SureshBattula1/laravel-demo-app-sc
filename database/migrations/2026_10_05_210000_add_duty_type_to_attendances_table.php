<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('attendances') && ! Schema::hasColumn('attendances', 'duty_type')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->string('duty_type')->nullable()->after('user_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('attendances') && Schema::hasColumn('attendances', 'duty_type')) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('duty_type');
            });
        }
    }
};
