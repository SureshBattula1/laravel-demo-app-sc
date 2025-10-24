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
        // Student Leaves Table
        Schema::create('student_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('student_id'); // References users.id
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->integer('total_days')->default(1);
            $table->enum('leave_type', [
                'Sick Leave',
                'Casual Leave',
                'Medical Leave',
                'Family Emergency',
                'Other'
            ])->default('Casual Leave');
            $table->enum('status', [
                'Pending',
                'Approved',
                'Rejected',
                'Cancelled'
            ])->default('Pending');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable(); // For medical certificates, etc.
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('student_id');
            $table->index('branch_id');
            $table->index('from_date');
            $table->index('to_date');
            $table->index('status');
            $table->index('leave_type');
            $table->index(['student_id', 'from_date', 'to_date']);
            
            // Foreign keys
            $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
        });
        
        // Teacher Leaves Table
        Schema::create('teacher_leaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('teacher_id'); // References users.id
            $table->unsignedBigInteger('branch_id')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->integer('total_days')->default(1);
            $table->enum('leave_type', [
                'Sick Leave',
                'Casual Leave',
                'Medical Leave',
                'Maternity Leave',
                'Paternity Leave',
                'Compensatory Leave',
                'Unpaid Leave',
                'Other'
            ])->default('Casual Leave');
            $table->enum('status', [
                'Pending',
                'Approved',
                'Rejected',
                'Cancelled'
            ])->default('Pending');
            $table->text('reason')->nullable();
            $table->text('remarks')->nullable();
            $table->string('attachment')->nullable(); // For medical certificates, etc.
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedBigInteger('substitute_teacher_id')->nullable(); // References users.id
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes
            $table->index('teacher_id');
            $table->index('branch_id');
            $table->index('from_date');
            $table->index('to_date');
            $table->index('status');
            $table->index('leave_type');
            $table->index(['teacher_id', 'from_date', 'to_date']);
            
            // Foreign keys
            $table->foreign('teacher_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('substitute_teacher_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_leaves');
        Schema::dropIfExists('student_leaves');
    }
};

