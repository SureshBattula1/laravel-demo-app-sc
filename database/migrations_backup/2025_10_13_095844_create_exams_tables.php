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
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['Mid-Term', 'Final', 'Unit Test', 'Quarterly', 'Annual']);
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->string('grade_level');
            $table->string('section')->nullable();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration');
            $table->integer('total_marks');
            $table->integer('passing_marks');
            $table->string('room')->nullable();
            $table->text('instructions')->nullable();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('academic_year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

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
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('exam_results');
        Schema::dropIfExists('exams');
    }
};
