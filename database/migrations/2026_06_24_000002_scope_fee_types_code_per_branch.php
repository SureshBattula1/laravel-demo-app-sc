<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * fee_types.code was globally unique — wrong for a multi-tenant SaaS (two schools could
 * never both have a "TUITION" code). Scope uniqueness to (branch_id, code).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('fee_types')) {
            return;
        }

        $hasGlobal = collect(DB::select('SHOW INDEX FROM fee_types'))
            ->contains(fn ($i) => $i->Key_name === 'fee_types_code_unique');
        if ($hasGlobal) {
            Schema::table('fee_types', function (Blueprint $table) {
                $table->dropUnique('fee_types_code_unique');
            });
        }

        $hasComposite = collect(DB::select('SHOW INDEX FROM fee_types'))
            ->contains(fn ($i) => $i->Key_name === 'fee_types_branch_id_code_unique');
        if (!$hasComposite) {
            Schema::table('fee_types', function (Blueprint $table) {
                $table->unique(['branch_id', 'code'], 'fee_types_branch_id_code_unique');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('fee_types')) {
            return;
        }
        $hasComposite = collect(DB::select('SHOW INDEX FROM fee_types'))
            ->contains(fn ($i) => $i->Key_name === 'fee_types_branch_id_code_unique');
        if ($hasComposite) {
            Schema::table('fee_types', function (Blueprint $table) {
                $table->dropUnique('fee_types_branch_id_code_unique');
            });
        }
    }
};
