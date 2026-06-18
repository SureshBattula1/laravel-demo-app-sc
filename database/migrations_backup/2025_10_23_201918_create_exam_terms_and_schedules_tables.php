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
        // Exam Terms - Academic Calendar
        if (!Schema::hasTable('exam_terms')) {
            Schema::create('exam_terms', function (Blueprint $table) {
                $table->id();
                $table->string('name'); // Term 1, Term 2, Final
                $table->string('code')->unique(); // TERM1-2024, TERM2-2024
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('academic_year');
                $table->date('start_date');
                $table->date('end_date');
                $table->integer('weightage')->default(0); // Percentage weightage in final result
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                // Indexes for performance
                $table->index(['branch_id', 'academic_year', 'is_active']);
                $table->index('code');
            });
        }

        // Exam Schedules - Links exams to subjects with timing
        if (!Schema::hasTable('exam_schedules')) {
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
                $table->integer('duration'); // in minutes
                $table->decimal('total_marks', 8, 2);
                $table->decimal('passing_marks', 8, 2);
                $table->string('room_number')->nullable();
                $table->foreignId('invigilator_id')->nullable()->constrained('users')->onDelete('set null');
                $table->text('instructions')->nullable();
                $table->enum('status', ['Scheduled', 'Ongoing', 'Completed', 'Cancelled'])->default('Scheduled');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                // Prevent double booking
                $table->unique(['exam_id', 'subject_id', 'grade_level', 'section'], 'unique_exam_subject_schedule');
                
                // Indexes
                $table->index(['exam_id', 'grade_level', 'section']);
                $table->index(['exam_date', 'start_time']);
                $table->index(['branch_id', 'exam_date']);
            });
        }

        // Exam Marks - Enhanced with approval workflow
        if (!Schema::hasTable('exam_marks')) {
            Schema::create('exam_marks', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
                $table->decimal('marks_obtained', 8, 2);
                $table->decimal('total_marks', 8, 2);
                $table->decimal('percentage', 5, 2);
                $table->string('grade', 5)->nullable(); // A+, A, B, etc.
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
                
                // Prevent duplicate entries
                $table->unique(['exam_schedule_id', 'student_id'], 'unique_exam_student_marks');
                
                // Indexes
                $table->index(['exam_schedule_id', 'status']);
                $table->index(['student_id', 'status']);
                $table->index(['marks_obtained', 'exam_schedule_id']); // For ranking
            });
        }

        // Exam Attendance - Track who appeared
        if (!Schema::hasTable('exam_attendance')) {
            Schema::create('exam_attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('exam_schedule_id')->constrained('exam_schedules')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
                $table->enum('status', ['Present', 'Absent', 'Medical Leave', 'Late'])->default('Present');
                $table->time('arrival_time')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
                
                // Prevent duplicates
                $table->unique(['exam_schedule_id', 'student_id'], 'unique_exam_attendance');
                
                // Indexes
                $table->index(['exam_schedule_id', 'status']);
                $table->index('student_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_attendance');
        Schema::dropIfExists('exam_marks');
        Schema::dropIfExists('exam_schedules');
        Schema::dropIfExists('exam_terms');
    }
};
