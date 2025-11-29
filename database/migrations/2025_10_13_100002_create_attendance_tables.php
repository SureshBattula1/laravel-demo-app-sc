<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Attendance tables for students and teachers
     */
    public function up(): void
    {
        // STUDENT ATTENDANCE TABLE
        Schema::create('student_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('grade_level');
            $table->string('section');
            $table->date('date');
            $table->enum('status', ['Present', 'Absent', 'Late', 'Half-Day', 'Sick Leave', 'Leave'])->default('Present');
            $table->text('remarks')->nullable();
            $table->string('marked_by')->nullable();
            $table->string('academic_year');
            $table->timestamps();
            
            $table->unique(['student_id', 'date']);
            $table->index(['branch_id', 'date']);
            $table->index(['grade_level', 'section', 'date']);
            $table->index(['date', 'status']);
        });

        // TEACHER ATTENDANCE TABLE
        Schema::create('teacher_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('restrict');
            $table->date('date');
            $table->enum('status', ['Present', 'Absent', 'Late', 'HalfDay', 'OnLeave'])->default('Present');
            $table->time('check_in_time')->nullable();
            $table->time('check_out_time')->nullable();
            $table->string('leave_type', 50)->nullable();
            $table->text('remarks')->nullable();
            $table->string('marked_by')->nullable();
            $table->timestamps();
            
            $table->unique(['teacher_id', 'date']);
            $table->index('branch_id');
            $table->index('date');
            $table->index('status');
            $table->index(['branch_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attendance');
        Schema::dropIfExists('student_attendance');
    }
};

