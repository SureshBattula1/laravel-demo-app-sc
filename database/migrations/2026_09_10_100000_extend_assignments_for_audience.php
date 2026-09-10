<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('assignments')) {
            Schema::table('assignments', function (Blueprint $table) {
                if (!Schema::hasColumn('assignments', 'audience_mode')) {
                    $table->enum('audience_mode', ['all', 'custom'])->default('all');
                }
                if (!Schema::hasColumn('assignments', 'academic_year_id')) {
                    $table->foreignId('academic_year_id')->nullable()->constrained('academic_years')->nullOnDelete();
                }
            });

            Schema::table('assignments', function (Blueprint $table) {
                $table->index(['branch_id', 'is_published', 'due_date'], 'assignments_branch_published_due_idx');
            });
        }

        if (!Schema::hasTable('assignment_recipients')) {
            Schema::create('assignment_recipients', function (Blueprint $table) {
                $table->id();
                $table->foreignId('assignment_id')->constrained('assignments')->cascadeOnDelete();
                $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['assignment_id', 'student_id']);
                $table->index('student_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_recipients');

        if (Schema::hasTable('assignments')) {
            Schema::table('assignments', function (Blueprint $table) {
                $table->dropIndex('assignments_branch_published_due_idx');
                if (Schema::hasColumn('assignments', 'academic_year_id')) {
                    $table->dropConstrainedForeignId('academic_year_id');
                }
                if (Schema::hasColumn('assignments', 'audience_mode')) {
                    $table->dropColumn('audience_mode');
                }
            });
        }
    }
};
