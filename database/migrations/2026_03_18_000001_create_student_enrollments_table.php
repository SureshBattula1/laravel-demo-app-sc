<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();

            // Multi-school context (kept nullable for compatibility with old data)
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();

            $table->foreignId('academic_year_id')->constrained('academic_years')->cascadeOnDelete();

            // Keep as string to match existing students.grade/section shape
            $table->string('grade', 50);
            $table->string('section', 50)->nullable();
            $table->string('roll_number', 50)->nullable();

            $table->enum('status', ['Active', 'Inactive'])->default('Active');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['student_id', 'academic_year_id'], 'uniq_student_year_enrollment');
            $table->index(['academic_year_id', 'branch_id', 'grade', 'section'], 'idx_enrollments_year_branch_grade_section');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};

