<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Subject `code` was globally unique, which is wrong for a multi-tenant SaaS:
 * two different schools/branches could never share a code (e.g. "MATH101").
 * Scope uniqueness to (branch_id, code) instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subjects')) {
            return;
        }

        // Drop the global unique index on `code` if present.
        $hasGlobalUnique = collect(DB::select("SHOW INDEX FROM subjects"))
            ->contains(fn ($i) => $i->Key_name === 'subjects_code_unique');

        if ($hasGlobalUnique) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropUnique('subjects_code_unique');
            });
        }

        // Add composite unique (branch_id, code) if not already present.
        $hasComposite = collect(DB::select("SHOW INDEX FROM subjects"))
            ->contains(fn ($i) => $i->Key_name === 'subjects_branch_id_code_unique');

        if (!$hasComposite) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->unique(['branch_id', 'code'], 'subjects_branch_id_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('subjects')) {
            return;
        }

        $hasComposite = collect(DB::select("SHOW INDEX FROM subjects"))
            ->contains(fn ($i) => $i->Key_name === 'subjects_branch_id_code_unique');

        if ($hasComposite) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->dropUnique('subjects_branch_id_code_unique');
            });
        }

        $hasGlobalUnique = collect(DB::select("SHOW INDEX FROM subjects"))
            ->contains(fn ($i) => $i->Key_name === 'subjects_code_unique');

        if (!$hasGlobalUnique) {
            Schema::table('subjects', function (Blueprint $table) {
                $table->unique('code', 'subjects_code_unique');
            });
        }
    }
};
