<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Create teacher_attachments table for storing document paths
     */
    public function up(): void
    {
        if (!Schema::hasTable('teacher_attachments')) {
            Schema::create('teacher_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
                $table->string('document_type', 50); // profile_picture, resume, joining_letter, etc.
                $table->string('file_name');
                $table->string('file_path');
                $table->string('file_type', 50); // jpg, png, pdf, etc.
                $table->integer('file_size'); // in bytes
                $table->string('original_name');
                $table->text('description')->nullable();
                $table->boolean('is_active')->default(true);
                $table->string('uploaded_by')->nullable();
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('teacher_id');
                $table->index('document_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_attachments');
    }
};

