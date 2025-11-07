<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Classes/Sections Table
        if (!Schema::hasTable('classes')) {
            Schema::create('classes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('grade'); // e.g., "10", "11", "12"
                $table->string('section')->nullable(); // e.g., "A", "B", "C"
                $table->string('class_name'); // e.g., "Grade 10-A"
                $table->string('academic_year'); // e.g., "2024-2025"
                $table->foreignId('class_teacher_id')->nullable()->constrained('users')->onDelete('set null');
                $table->integer('capacity')->default(40);
                $table->integer('current_strength')->default(0);
                $table->string('room_number')->nullable();
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                // Unique constraint: one class per grade-section-branch-year
                $table->unique(['branch_id', 'grade', 'section', 'academic_year'], 'unique_class');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};

