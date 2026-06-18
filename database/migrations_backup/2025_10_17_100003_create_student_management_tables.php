<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Student Groups
        if (!Schema::hasTable('student_groups')) {
            Schema::create('student_groups', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->string('name');
                $table->string('code')->unique();
                $table->enum('type', ['Academic', 'Sports', 'Cultural', 'Club'])->default('Academic');
                $table->string('academic_year');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Student Group Members
        if (!Schema::hasTable('student_group_members')) {
            Schema::create('student_group_members', function (Blueprint $table) {
                $table->id();
                $table->foreignId('group_id')->constrained('student_groups')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->date('joined_date');
                $table->enum('role', ['Member', 'Leader'])->default('Member');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->unique(['group_id', 'student_id']);
            });
        }

        // Class Upgrades/Promotions
        if (!Schema::hasTable('class_upgrades')) {
            Schema::create('class_upgrades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->string('academic_year_from');
                $table->string('academic_year_to');
                $table->string('from_grade');
                $table->string('to_grade');
                $table->enum('promotion_status', ['Promoted', 'Detained', 'Left', 'Graduated'])->default('Promoted');
                $table->decimal('percentage', 5, 2)->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamps();
            });
        }

        // Student Achievements
        if (!Schema::hasTable('student_achievements')) {
            Schema::create('student_achievements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->string('achievement_title');
                $table->enum('achievement_type', ['Academic', 'Sports', 'Cultural', 'Other'])->default('Academic');
                $table->string('position')->nullable();
                $table->date('achievement_date');
                $table->enum('level', ['School', 'District', 'State', 'National', 'International'])->default('School');
                $table->text('description')->nullable();
                $table->timestamps();
            });
        }

        // Student Health Records
        if (!Schema::hasTable('student_health_records')) {
            Schema::create('student_health_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->date('checkup_date');
                $table->decimal('height_cm', 5, 2)->nullable();
                $table->decimal('weight_kg', 5, 2)->nullable();
                $table->decimal('bmi', 5, 2)->nullable();
                $table->string('blood_pressure')->nullable();
                $table->text('medical_conditions')->nullable();
                $table->text('doctor_remarks')->nullable();
                $table->timestamps();
            });
        }

        // Student Siblings
        if (!Schema::hasTable('student_siblings')) {
            Schema::create('student_siblings', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
                $table->foreignId('sibling_id')->constrained('students')->onDelete('cascade');
                $table->boolean('discount_applicable')->default(false);
                $table->decimal('discount_percentage', 5, 2)->nullable();
                $table->timestamps();
                
                $table->unique(['student_id', 'sibling_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('student_siblings');
        Schema::dropIfExists('student_health_records');
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('class_upgrades');
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
    }
};

