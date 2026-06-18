<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create impersonation sessions table
     */
    public function up(): void
    {
        Schema::create('impersonation_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_admin_id')->constrained('users')->onDelete('cascade')->comment('Company admin who is impersonating');
            $table->foreignId('impersonated_user_id')->constrained('users')->onDelete('cascade')->comment('User being impersonated');
            $table->string('token')->unique()->comment('Session token');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->text('reason')->nullable()->comment('Reason for impersonation');
            $table->json('actions_log')->nullable()->comment('Log of actions performed during impersonation');
            $table->enum('status', ['Active', 'Ended', 'Expired'])->default('Active');
            $table->timestamps();
            
            // Indexes
            $table->index('company_admin_id');
            $table->index('impersonated_user_id');
            $table->index('token');
            $table->index('status');
            $table->index(['company_admin_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('impersonation_sessions');
    }
};

