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
        Schema::table('exams', function (Blueprint $table) {
            if (!Schema::hasColumn('exams', 'exam_term_id')) {
                $table->foreignId('exam_term_id')->nullable()->after('id')->constrained('exam_terms')->onDelete('set null');
                $table->index('exam_term_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exams', function (Blueprint $table) {
            if (Schema::hasColumn('exams', 'exam_term_id')) {
                $table->dropForeign(['exam_term_id']);
                $table->dropIndex(['exam_term_id']);
                $table->dropColumn('exam_term_id');
            }
        });
    }
};
