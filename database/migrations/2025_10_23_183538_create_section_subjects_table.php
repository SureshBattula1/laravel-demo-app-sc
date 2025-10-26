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
        Schema::create('section_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('section_id')->constrained('sections')->onDelete('cascade');
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('academic_year');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Prevent duplicate assignments
            $table->unique(['section_id', 'subject_id', 'academic_year'], 'unique_section_subject_year');
            
            // Indexes for performance
            $table->index(['section_id', 'academic_year']);
            $table->index(['subject_id', 'academic_year']);
            $table->index(['teacher_id', 'academic_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('section_subjects');
    }
};
