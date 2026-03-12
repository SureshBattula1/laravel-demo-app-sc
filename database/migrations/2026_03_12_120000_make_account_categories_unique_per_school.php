<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Replace global unique on name/code with school-wise unique (school_id, name) and (school_id, code).
     */
    public function up(): void
    {
        if (!Schema::hasTable('account_categories')) {
            return;
        }

        // Backfill school_id from branch_id for existing rows (if school_id column exists)
        if (Schema::hasColumn('account_categories', 'school_id') && Schema::hasColumn('account_categories', 'branch_id')) {
            DB::statement('
                UPDATE account_categories ac
                INNER JOIN branches b ON b.id = ac.branch_id
                SET ac.school_id = b.school_id
                WHERE ac.branch_id IS NOT NULL AND (ac.school_id IS NULL OR ac.school_id = 0)
            ');
        }

        Schema::table('account_categories', function (Blueprint $table) {
            // Drop global unique indexes (MySQL names: account_categories_name_unique, account_categories_code_unique)
            $table->dropUnique(['name']);
            $table->dropUnique(['code']);
        });

        Schema::table('account_categories', function (Blueprint $table) {
            // School-wise unique: same name/code allowed in different schools
            $table->unique(['school_id', 'name'], 'account_categories_school_id_name_unique');
            $table->unique(['school_id', 'code'], 'account_categories_school_id_code_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('account_categories')) {
            return;
        }

        Schema::table('account_categories', function (Blueprint $table) {
            $table->dropUnique('account_categories_school_id_name_unique');
            $table->dropUnique('account_categories_school_id_code_unique');
        });

        Schema::table('account_categories', function (Blueprint $table) {
            $table->unique('name');
            $table->unique('code');
        });
    }
};
