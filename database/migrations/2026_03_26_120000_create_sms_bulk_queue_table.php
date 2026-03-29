<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit / history row when a bulk SMS batch is queued (after user confirms in UI).
     */
    public function up(): void
    {
        Schema::create('sms_bulk_queue', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('audience', 32)->nullable();
            $table->text('body_template');
            $table->text('sample_rendered')->nullable();
            $table->string('sample_label', 255)->nullable();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('job_count')->default(0);
            $table->string('provider', 32)->nullable();
            $table->string('status', 32)->default('processing');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_bulk_queue');
    }
};
