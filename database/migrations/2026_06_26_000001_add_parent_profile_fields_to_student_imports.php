<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keep the student import staging table in sync with the student form/table:
 * the parent/guardian qualification, organization and designation fields were
 * added to students later, so the importer could not capture them. Add the
 * matching columns here so upload -> validate -> commit can carry them through.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('student_imports')) {
            return;
        }

        Schema::table('student_imports', function (Blueprint $table) {
            $cols = [
                'father_qualification',
                'father_organization',
                'father_designation',
                'mother_qualification',
                'mother_organization',
                'mother_designation',
                'guardian_qualification',
            ];
            foreach ($cols as $col) {
                if (! Schema::hasColumn('student_imports', $col)) {
                    $table->string($col)->nullable()->after('mother_annual_income');
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('student_imports')) {
            return;
        }

        Schema::table('student_imports', function (Blueprint $table) {
            foreach ([
                'father_qualification',
                'father_organization',
                'father_designation',
                'mother_qualification',
                'mother_organization',
                'mother_designation',
                'guardian_qualification',
            ] as $col) {
                if (Schema::hasColumn('student_imports', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
