<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sections')) {
            Schema::create('sections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('name'); // A, B, C, D, etc.
                $table->string('code')->unique(); // SEC-A, SEC-B, etc.
                $table->string('grade_level')->nullable(); // Optional: specific to a grade
                $table->integer('capacity')->default(40);
                $table->integer('current_strength')->default(0);
                $table->string('room_number')->nullable();
                $table->foreignId('class_teacher_id')->nullable()->constrained('users')->onDelete('set null');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                
                // Unique constraint: one section per name-branch
                $table->unique(['branch_id', 'name', 'grade_level'], 'unique_section');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sections');
    }
};

