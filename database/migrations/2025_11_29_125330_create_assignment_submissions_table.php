<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained('assignments')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');
            $table->text('submission_text')->nullable();
            $table->json('attachments')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->decimal('marks_obtained', 8, 2)->nullable();
            $table->text('feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->foreignId('graded_by')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('status', ['Pending', 'Submitted', 'Graded', 'Late', 'Missing'])->default('Pending');
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['assignment_id', 'student_id']);
            $table->index(['student_id', 'status']);
            $table->index('submitted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
    }
};
