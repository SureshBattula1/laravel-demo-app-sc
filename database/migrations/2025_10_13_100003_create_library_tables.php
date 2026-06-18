<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Library management tables
     */
    public function up(): void
    {
        // BOOKS TABLE
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('title');
            $table->string('author');
            $table->string('isbn')->unique();
            $table->string('category');
            $table->string('publisher')->nullable();
            $table->integer('published_year')->nullable();
            $table->string('language');
            $table->string('edition')->nullable();
            $table->integer('pages')->nullable();
            $table->integer('total_copies');
            $table->integer('available_copies');
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_active']);
            $table->index('category');
            $table->index('isbn');
        });

        // BOOK ISSUES TABLE
        Schema::create('book_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('student_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->foreignId('teacher_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->enum('borrower_type', ['Student', 'Teacher']);
            $table->date('issue_date');
            $table->date('due_date');
            $table->date('return_date')->nullable();
            $table->enum('status', ['Issued', 'Returned', 'Overdue', 'Lost'])->default('Issued');
            $table->decimal('fine_amount', 8, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();
            
            $table->index(['book_id', 'status']);
            $table->index(['student_id', 'status']);
            $table->index(['teacher_id', 'status']);
            $table->index(['due_date', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('book_issues');
        Schema::dropIfExists('books');
    }
};

