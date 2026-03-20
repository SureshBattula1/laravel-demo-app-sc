<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            if (!Schema::hasColumn('fee_types', 'deleted_at')) {
                $table->softDeletes();
            }

            if (!Schema::hasColumn('fee_types', 'academic_year_id')) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->constrained('academic_years')
                    ->onDelete('set null')
                    ->after('branch_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('fee_types', function (Blueprint $table) {
            if (Schema::hasColumn('fee_types', 'academic_year_id')) {
                $table->dropForeign(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            }

            if (Schema::hasColumn('fee_types', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });
    }
};

