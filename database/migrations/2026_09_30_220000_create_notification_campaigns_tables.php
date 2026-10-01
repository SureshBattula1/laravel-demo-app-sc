<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('module', 32);
            $table->foreignId('branch_id')->constrained('branches');
            $table->date('event_date')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->string('status', 16)->default('pending');
            $table->json('template_map');
            $table->unsignedInteger('target_count')->default(0);
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['module', 'branch_id', 'status'], 'nc_module_branch_status_idx');
        });

        Schema::create('notification_campaign_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $table->string('grade', 32)->nullable();
            $table->string('section', 32)->nullable();
            $table->unsignedInteger('student_count')->default(0);
            $table->string('status', 16)->default('pending');
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->timestamps();

            $table->index(['campaign_id', 'grade', 'section'], 'nct_campaign_class_idx');
        });

        Schema::create('notification_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $table->foreignId('target_id')->nullable()->constrained('notification_campaign_targets')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('student_id')->nullable();
            $table->string('student_name')->nullable();
            $table->string('grade', 32)->nullable();
            $table->string('section', 32)->nullable();
            $table->string('status_key', 32);
            $table->string('delivery_status', 16)->default('pending');
            $table->text('error')->nullable();
            $table->unsignedBigInteger('notification_id')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('liked_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'delivery_status'], 'ncr_campaign_status_idx');
            $table->index('notification_id', 'ncr_notification_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_recipients');
        Schema::dropIfExists('notification_campaign_targets');
        Schema::dropIfExists('notification_campaigns');
    }
};
