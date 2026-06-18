<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sms_bulk_queue', function (Blueprint $table) {
            $table->foreignId('academic_year_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('academic_years')
                ->nullOnDelete();
        });

        $defaultAyId = DB::table('academic_years')
            ->where('is_current', true)
            ->where('is_active', true)
            ->orderByDesc('id')
            ->value('id');

        if ($defaultAyId === null) {
            $defaultAyId = DB::table('academic_years')->orderByDesc('id')->value('id');
        }

        if ($defaultAyId !== null) {
            DB::table('sms_bulk_queue')->whereNull('academic_year_id')->update(['academic_year_id' => $defaultAyId]);
        }
    }

    public function down(): void
    {
        Schema::table('sms_bulk_queue', function (Blueprint $table) {
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
