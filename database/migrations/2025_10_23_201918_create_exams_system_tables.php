<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Complete exams system with all tables
     */
    public function up(): void
    {
        // EXAM TERMS TABLE - Academic calendar terms
        Schema::create('exam_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name'); // Term 1, Term 2, Final
            $table->string('code')->unique(); // TERM1-2024, TERM2-2024
            $table->string('academic_year');
            $table->date('start_date');
            $table->date('end_date');
            $table->integer('weightage')->default(0);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['branch_id', 'academic_year', 'is_active']);
            $table->index('code');
        });

        // EXAMS TABLE - Main exams table with final structure
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('exam_term_id')->nullable()->constrained('exam_terms')->onDelete('set null');
            $table->foreignId('subject_id')->nullable()->constrained('subjects')->onDelete('set null');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('type')->nullable(); // Changed to string for flexibility
            $table->string('grade_level')->nullable();
            $table->string('section')->nullable();
            $table->date('date')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->integer('duration')->nullable();
            $table->integer('total_marks')->nullable();
            $table->integer('passing_marks')->nullable();
            $table->string('room')->nullable();
            $table->text('instructions')->nullable();
            $table->string('academic_year');
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'exam_term_id']);
            $table->index(['grade_level', 'section', 'academic_year']);
            $table->index('date');
        });

        // EXAM SCHEDULES TABLE - Links exams to subjects with detailed timing
        Schema::create('exam_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('grade_level');
            $table->string('section')->nullable();
            $table->date('exam_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration')->nullable();
            $table->decimal('total_marks', 8, 2);
            $table->decimal('passing_marks', 8, 2);
            $table->string('room_number')->nullable();
            $table->foreignId('invigilator_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('instructions')->nullable();
            $table->enum('status', ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'])->default('Scheduled');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['exam_id', 'subject_id', 'grade_level', 'section'], 'unique_exam_subject_schedule');
            $table->index(['exam_id', 'grade_level', 'section']);
            $table->index(['exam_date', 'start_time']);
            $table->index(['branch_id', 'exam_date']);
        });

        // EXAM MARKS TABLE - Store individual student marks
        Schema::create('exam_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->decimal('total_marks', 8, 2);
            $table->decimal('percentage', 5, 2);
            $table->string('grade', 5)->nullable();
            $table->boolean('is_absent')->default(false);
            $table->boolean('is_pass')->default(false);
            $table->integer('rank_in_class')->nullable();
            $table->integer('rank_in_section')->nullable();
            $table->text('remarks')->nullable();
            $table->enum('status', ['Draft', 'Submitted', 'Approved', 'Published'])->default('Draft');
            $table->foreignId('entered_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            
            $table->unique(['exam_schedule_id', 'student_id'], 'unique_exam_student_marks');
            $table->index(['exam_schedule_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['marks_obtained', 'exam_schedule_id']);
        });

        // EXAM RESULTS TABLE - Overall results per student per exam
        Schema::create('exam_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained('exams')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->decimal('marks_obtained', 8, 2);
            $table->string('grade');
            $table->decimal('percentage', 5, 2);
            $table->integer('rank')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_pass');
            $table->timestamps();
            
            $table->index(['exam_id', 'student_id']);
            $table->index('student_id');
        });

        // EXAM ATTENDANCE TABLE - Track who appeared for exams
        Schema::create('exam_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['Present', 'Absent', 'Medical Leave', 'Late'])->default('Present');
            $table->time('arrival_time')->nullable();
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->unique(['exam_schedule_id', 'student_id'], 'unique_exam_attendance');
            $table->index(['exam_schedule_id', 'status']);
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attendance');
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exams');
        Schema::dropIfExists('exam_terms');
    }
};

