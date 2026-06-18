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
        Schema::create('fee_audit_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('action', ['Payment', 'Refund', 'Discount', 'CarryForward', 'Waiver', 'Adjustment'])->index();
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->uuid('fee_payment_id')->nullable();
            $table->foreign('fee_payment_id')->references('id')->on('fee_payments')->onDelete('set null');
            $table->uuid('fee_due_id')->nullable();
            $table->foreign('fee_due_id')->references('id')->on('fee_dues')->onDelete('set null');
            $table->decimal('amount_before', 10, 2)->default(0);
            $table->decimal('amount_after', 10, 2)->default(0);
            $table->decimal('action_amount', 10, 2)->default(0);
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable(); // Additional tracking data
            $table->string('ip_address', 45)->nullable(); // Supports IPv6
            $table->text('user_agent')->nullable();
            $table->string('session_id')->nullable();
            $table->string('request_method', 10)->nullable();
            $table->string('request_endpoint')->nullable();
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('created_at')->useCurrent(); // Immutable - never updated
            
            // Indexes for performance and queries
            $table->index(['student_id', 'created_at']);
            $table->index(['action', 'created_at']);
            $table->index('created_by');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fee_audit_logs');
    }
};
