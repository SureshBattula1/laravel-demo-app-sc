<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Create grades and sections tables
     */
    public function up(): void
    {
        // GRADES TABLE - Complete with order and category
        Schema::create('grades', function (Blueprint $table) {
            $table->id();
            $table->string('value', 20)->unique();
            $table->integer('order')->default(0);
            $table->string('category', 50)->nullable();
            $table->string('label', 100);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index('value');
            $table->index('order');
            $table->index('category');
            $table->index('is_active');
        });

        // Seed default grades
        $grades = [
            ['value' => '1', 'order' => 5, 'category' => 'Primary', 'label' => 'Grade 1'],
            ['value' => '2', 'order' => 6, 'category' => 'Primary', 'label' => 'Grade 2'],
            ['value' => '3', 'order' => 7, 'category' => 'Primary', 'label' => 'Grade 3'],
            ['value' => '4', 'order' => 8, 'category' => 'Primary', 'label' => 'Grade 4'],
            ['value' => '5', 'order' => 9, 'category' => 'Primary', 'label' => 'Grade 5'],
            ['value' => '6', 'order' => 10, 'category' => 'Middle', 'label' => 'Grade 6'],
            ['value' => '7', 'order' => 11, 'category' => 'Middle', 'label' => 'Grade 7'],
            ['value' => '8', 'order' => 12, 'category' => 'Middle', 'label' => 'Grade 8'],
            ['value' => '9', 'order' => 13, 'category' => 'Secondary', 'label' => 'Grade 9'],
            ['value' => '10', 'order' => 14, 'category' => 'Secondary', 'label' => 'Grade 10'],
            ['value' => '11', 'order' => 15, 'category' => 'Senior-Secondary', 'label' => 'Grade 11'],
            ['value' => '12', 'order' => 16, 'category' => 'Senior-Secondary', 'label' => 'Grade 12'],
        ];

        foreach ($grades as $grade) {
            DB::table('grades')->insert([
                'value' => $grade['value'],
                'order' => $grade['order'],
                'category' => $grade['category'],
                'label' => $grade['label'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]);
        }

        // SECTIONS TABLE - Complete structure
        Schema::create('sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name'); // A, B, C, D, etc.
            $table->string('code')->unique(); // SEC-A, SEC-B, etc.
            $table->string('grade_level')->nullable();
            $table->integer('capacity')->default(40);
            $table->integer('current_strength')->default(0);
            $table->string('room_number')->nullable();
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['branch_id', 'name', 'grade_level'], 'unique_section');
            $table->index(['branch_id', 'grade_level']);
            $table->index('is_active');
        });

        // CLASSES TABLE - Grade-section combinations
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('grade'); // "10", "11", "12"
            $table->string('section')->nullable(); // "A", "B", "C"
            $table->string('class_name'); // "Grade 10-A"
            $table->string('academic_year'); // "2024-2025"
            $table->foreignId('class_teacher_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('capacity')->default(40);
            $table->integer('current_strength')->default(0);
            $table->string('room_number')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Unique constraint: one class per grade-section-branch-year
            $table->unique(['branch_id', 'grade', 'section', 'academic_year'], 'unique_class');
            $table->index(['branch_id', 'grade']);
            $table->index(['grade', 'section']);
            $table->index('is_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
        Schema::dropIfExists('sections');
        Schema::dropIfExists('grades');
    }
};

