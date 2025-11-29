<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Student management tables
     */
    public function up(): void
    {
        // STUDENT GROUPS TABLE
        Schema::create('student_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->enum('type', ['Academic', 'Sports', 'Cultural', 'Club'])->default('Academic');
            $table->string('academic_year');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->index(['branch_id', 'type']);
            $table->index(['academic_year', 'is_active']);
        });

        // STUDENT GROUP MEMBERS TABLE
        Schema::create('student_group_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('student_groups')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->date('joined_date');
            $table->enum('role', ['Member', 'Leader'])->default('Member');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            $table->unique(['group_id', 'student_id']);
            $table->index(['group_id', 'is_active']);
        });

        // CLASS UPGRADES/PROMOTIONS TABLE
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
            
            $table->index(['student_id', 'academic_year_from']);
        });

        // STUDENT ACHIEVEMENTS TABLE
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
            
            $table->index(['student_id', 'achievement_date']);
            $table->index('achievement_type');
        });

        // STUDENT HEALTH RECORDS TABLE
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
            
            $table->index(['student_id', 'checkup_date']);
        });

        // STUDENT SIBLINGS TABLE
        Schema::create('student_siblings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->foreignId('sibling_id')->constrained('students')->onDelete('cascade');
            $table->boolean('discount_applicable')->default(false);
            $table->decimal('discount_percentage', 5, 2)->nullable();
            $table->timestamps();
            
            $table->unique(['student_id', 'sibling_id']);
        });

        // STUDENT LEAVES TABLE
        Schema::create('student_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->integer('total_days')->default(1);
            $table->enum('leave_type', ['Sick Leave', 'Casual Leave', 'Medical Leave', 'Family Emergency', 'Other'])->default('Casual Leave');
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('student_id');
            $table->index('branch_id');
            $table->index(['from_date', 'to_date']);
            $table->index('status');
            $table->index('leave_type');
            $table->index(['student_id', 'from_date', 'to_date']);
            
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });

        // TEACHER LEAVES TABLE
        Schema::create('teacher_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id');
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->integer('total_days')->default(1);
            $table->enum('leave_type', ['Sick Leave', 'Casual Leave', 'Medical Leave', 'Maternity Leave', 'Paternity Leave', 'Compensatory Leave', 'Unpaid Leave', 'Other'])->default('Casual Leave');
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Cancelled'])->default('Pending');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable();
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('substitute_teacher_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('teacher_id');
            $table->index('branch_id');
            $table->index(['from_date', 'to_date']);
            $table->index('status');
            $table->index('leave_type');
            $table->index(['teacher_id', 'from_date', 'to_date']);
            
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('substitute_teacher_id')->references('id')->on('users')->onDelete('set null');
        });

        // TEACHER ATTACHMENTS TABLE
        Schema::create('teacher_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('document_type', 50);
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type', 50);
            $table->integer('file_size');
            $table->string('original_name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('uploaded_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('teacher_id');
            $table->index('document_type');
            $table->index(['teacher_id', 'document_type']);
            $table->index(['teacher_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attachments');
        Schema::dropIfExists('teacher_leaves');
        Schema::dropIfExists('student_leaves');
        Schema::dropIfExists('student_siblings');
        Schema::dropIfExists('student_health_records');
        Schema::dropIfExists('student_achievements');
        Schema::dropIfExists('class_upgrades');
        Schema::dropIfExists('student_group_members');
        Schema::dropIfExists('student_groups');
    }
};

