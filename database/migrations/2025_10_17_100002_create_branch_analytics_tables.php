<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Branch analytics and settings tables
     */
    public function up(): void
    {
        // BRANCH SETTINGS TABLE
        Schema::create('branch_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('setting_key');
            $table->text('setting_value')->nullable();
            $table->string('setting_type')->default('string');
            $table->timestamps();
            
            $table->unique(['branch_id', 'setting_key']);
        });

        // BRANCH TRANSFERS TABLE
        Schema::create('branch_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('from_branch_id')->constrained('branches')->onDelete('restrict');
            $table->foreignId('to_branch_id')->constrained('branches')->onDelete('restrict');
            $table->enum('transfer_type', ['Student', 'Teacher', 'Staff']);
            $table->date('transfer_date');
            $table->text('reason')->nullable();
            $table->enum('status', ['Pending', 'Approved', 'Rejected', 'Completed'])->default('Pending');
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index(['from_branch_id', 'to_branch_id']);
        });

        // BRANCH ANALYTICS TABLE
        Schema::create('branch_analytics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->date('analytics_date');
            $table->string('metric_type');
            $table->decimal('metric_value', 15, 2);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            
            $table->unique(['branch_id', 'analytics_date', 'metric_type']);
            $table->index(['branch_id', 'analytics_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('branch_analytics');
        Schema::dropIfExists('branch_transfers');
        Schema::dropIfExists('branch_settings');
    }
};

