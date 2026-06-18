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
        // NOTIFICATIONS TABLE
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('title');
            $table->text('message');
            $table->enum('type', ['Info', 'Warning', 'Error', 'Success', 'Alert'])->default('Info');
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->enum('status', ['Pending', 'Sent', 'Read', 'Failed'])->default('Pending');
            $table->timestamp('read_at')->nullable();
            $table->string('action_url')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['user_id', 'status']);
            $table->index(['branch_id', 'type']);
            $table->index('read_at');
        });

        // ANNOUNCEMENTS TABLE
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['General', 'Academic', 'Event', 'Holiday', 'Emergency', 'Other'])->default('General');
            $table->json('target_audience')->nullable(); // ['Students', 'Teachers', 'Parents', 'All']
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->json('attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_published']);
            $table->index(['start_date', 'end_date']);
            $table->index('type');
        });

        // CIRCULARS TABLE
        Schema::create('circulars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
            $table->string('circular_number')->unique();
            $table->string('title');
            $table->text('content');
            $table->enum('type', ['Notice', 'Order', 'Instruction', 'Information', 'Other'])->default('Notice');
            $table->json('target_audience')->nullable();
            $table->date('issue_date');
            $table->date('effective_date');
            $table->date('expiry_date');
            $table->enum('priority', ['Low', 'Medium', 'High', 'Urgent'])->default('Medium');
            $table->boolean('requires_acknowledgment')->default(false);
            $table->boolean('is_published')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_published']);
            $table->index(['effective_date', 'expiry_date']);
            $table->index('circular_number');
        });

        // CIRCULAR ACKNOWLEDGMENTS TABLE
        Schema::create('circular_acknowledgments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('circular_id')->constrained('circulars')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamp('acknowledged_at');
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->unique(['circular_id', 'user_id']);
            $table->index('circular_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('circular_acknowledgments');
        Schema::dropIfExists('circulars');
        Schema::dropIfExists('announcements');
        Schema::dropIfExists('notifications');
    }
};
