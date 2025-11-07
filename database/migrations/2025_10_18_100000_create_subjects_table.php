<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create subjects and section_subjects tables
     */
    public function up(): void
    {
        // SUBJECTS TABLE
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('department_id')->constrained('departments')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('grade_level');
            $table->integer('credits')->default(0);
            $table->enum('type', ['Core', 'Elective', 'Language', 'Lab', 'Activity'])->default('Core');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'grade_level']);
            $table->index(['department_id', 'is_active']);
            $table->index('code');
        });

        // SECTION_SUBJECTS TABLE - Links subjects to specific sections
        Schema::create('section_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('academic_year');
            $table->integer('weekly_periods')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['section_id', 'subject_id', 'academic_year'], 'unique_section_subject');
            $table->index(['section_id', 'is_active']);
            $table->index(['branch_id', 'academic_year']);
            $table->index('teacher_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_subjects');
        Schema::dropIfExists('subjects');
    }
};

