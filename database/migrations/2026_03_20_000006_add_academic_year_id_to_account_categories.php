<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('account_categories')) {
            return;
        }

        Schema::table('account_categories', function (Blueprint $table) {
            if (!Schema::hasColumn('account_categories', 'academic_year_id')) {
                $table->foreignId('academic_year_id')
                    ->nullable()
                    ->after('school_id')
                    ->constrained('academic_years')
                    ->onDelete('set null');

                $table->index('academic_year_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('account_categories')) {
            return;
        }

        Schema::table('account_categories', function (Blueprint $table) {
            if (Schema::hasColumn('account_categories', 'academic_year_id')) {
                $table->dropForeign(['academic_year_id']);
                $table->dropIndex(['academic_year_id']);
                $table->dropColumn('academic_year_id');
            }
        });
    }
};

