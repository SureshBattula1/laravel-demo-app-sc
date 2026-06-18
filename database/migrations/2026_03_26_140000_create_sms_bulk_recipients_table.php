<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_bulk_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sms_bulk_queue_id')->constrained('sms_bulk_queue')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('recipient_type', 16);
            $table->unsignedBigInteger('recipient_id');
            $table->string('recipient_name', 255)->nullable();
            $table->string('status', 32)->default('pending');
            $table->text('error_message')->nullable();
            $table->json('provider_meta')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['sms_bulk_queue_id', 'recipient_type', 'recipient_id'], 'sms_bulk_rec_queue_type_id');
            $table->index(['branch_id', 'status']);
            $table->index(['sms_bulk_queue_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_bulk_recipients');
    }
};
