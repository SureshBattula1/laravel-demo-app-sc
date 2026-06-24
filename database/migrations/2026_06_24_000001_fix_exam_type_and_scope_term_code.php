<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Two related fixes for the Exam module:
 *
 * 1. The `exams` table column is `type`, but the Exam model fillable and ExamController
 *    use `exam_type` — so creating/updating an exam threw "Unknown column 'exam_type'".
 *    Rename `type` -> `exam_type` to match the code and the UI contract.
 *
 * 2. `exam_terms.code` was globally unique, which is wrong for a multi-tenant SaaS.
 *    Scope it to (branch_id, code) like subjects.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'type') && !Schema::hasColumn('exams', 'exam_type')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->renameColumn('type', 'exam_type');
            });
        }

        if (Schema::hasTable('exam_terms')) {
            $hasGlobalUnique = collect(DB::select('SHOW INDEX FROM exam_terms'))
                ->contains(fn ($i) => $i->Key_name === 'exam_terms_code_unique');
            if ($hasGlobalUnique) {
                Schema::table('exam_terms', function (Blueprint $table) {
                    $table->dropUnique('exam_terms_code_unique');
                });
            }

            $hasComposite = collect(DB::select('SHOW INDEX FROM exam_terms'))
                ->contains(fn ($i) => $i->Key_name === 'exam_terms_branch_id_code_unique');
            if (!$hasComposite) {
                Schema::table('exam_terms', function (Blueprint $table) {
                    $table->unique(['branch_id', 'code'], 'exam_terms_branch_id_code_unique');
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('exams') && Schema::hasColumn('exams', 'exam_type') && !Schema::hasColumn('exams', 'type')) {
            Schema::table('exams', function (Blueprint $table) {
                $table->renameColumn('exam_type', 'type');
            });
        }

        if (Schema::hasTable('exam_terms')) {
            $hasComposite = collect(DB::select('SHOW INDEX FROM exam_terms'))
                ->contains(fn ($i) => $i->Key_name === 'exam_terms_branch_id_code_unique');
            if ($hasComposite) {
                Schema::table('exam_terms', function (Blueprint $table) {
                    $table->dropUnique('exam_terms_branch_id_code_unique');
                });
            }
        }
    }
};
