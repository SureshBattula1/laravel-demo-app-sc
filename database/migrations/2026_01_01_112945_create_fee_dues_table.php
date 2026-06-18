<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('fee_dues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->uuid('fee_structure_id')->nullable(); // Can be null if fee is custom/adhoc
            $table->foreign('fee_structure_id')->references('id')->on('fee_structures')->onDelete('set null');
            $table->string('academic_year');
            $table->string('original_grade'); // Grade when fee was due
            $table->string('current_grade'); // Current student grade
            $table->decimal('original_amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('balance_amount', 10, 2);
            $table->date('due_date');
            $table->integer('overdue_days')->default(0);
            $table->enum('status', ['Pending', 'PartiallyPaid', 'Overdue', 'Waived', 'CarriedForward', 'Paid'])->default('Pending');
            $table->date('carry_forward_date')->nullable();
            $table->text('carry_forward_reason')->nullable();
            $table->string('fee_type'); // For categorization (Tuition, Transport, Exam, etc.)
            $table->integer('installment_number')->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->timestamps();
            $table->softDeletes();
            
            // Indexes for performance
            $table->index(['student_id', 'academic_year', 'status']);
            $table->index(['due_date', 'status']);
            $table->index(['current_grade', 'status']);
            $table->index(['fee_type', 'status']); // Important for fee_type filtering and grouping
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_dues');
    }
};
