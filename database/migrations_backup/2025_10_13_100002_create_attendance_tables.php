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
        Schema::create('student_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->string('grade_level');
            $table->string('section');
            $table->date('date');
            $table->enum('status', ['Present', 'Absent', 'Late', 'Half-Day', 'Sick Leave', 'Leave'])->default('Present');
            $table->text('remarks')->nullable();
            $table->string('marked_by')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('academic_year');
            $table->timestamps();
            $table->unique(['student_id', 'date']);
        });

        Schema::create('teacher_attendance', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->date('date');
            $table->enum('status', ['Present', 'Absent', 'Late', 'Half-Day', 'Leave'])->default('Present');
            $table->text('remarks')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['teacher_id', 'date']);
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
