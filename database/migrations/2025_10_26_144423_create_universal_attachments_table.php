<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Universal attachments table
     */
    public function up(): void
    {
        Schema::create('universal_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('module'); // 'branch', 'teacher', 'student', etc.
            $table->unsignedBigInteger('module_id'); // ID of the parent record
            $table->string('attachment_type'); // 'certification', 'resume', 'document', etc.
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->bigInteger('file_size')->nullable();
            $table->string('original_name')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['module', 'module_id']);
            $table->index(['module', 'attachment_type']);
            $table->index(['module', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('universal_attachments');
    }
};

