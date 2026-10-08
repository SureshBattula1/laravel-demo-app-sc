<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('student_transport')) {
            Schema::table('student_transport', function (Blueprint $table) {
                if (! Schema::hasColumn('student_transport', 'annual_fee')) {
                    $table->decimal('annual_fee', 10, 2)->nullable()->after('monthly_fee');
                }
            });

            // Backfill annual_fee from existing monthly_fee if present
            try {
                DB::table('student_transport')
                    ->whereNull('annual_fee')
                    ->whereNotNull('monthly_fee')
                    ->update([
                        'annual_fee' => DB::raw('monthly_fee'),
                    ]);
            } catch (\Throwable $e) {
                // Ignore backfill error if column or table state varies
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_transport') && Schema::hasColumn('student_transport', 'annual_fee')) {
            Schema::table('student_transport', function (Blueprint $table) {
                $table->dropColumn('annual_fee');
            });
        }
    }
};
