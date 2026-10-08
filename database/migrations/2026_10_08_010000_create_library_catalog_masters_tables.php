<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Categories
        Schema::create('library_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->unique(['branch_id', 'name']);
        });

        // 2. Authors
        Schema::create('library_authors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->text('biography')->nullable();
            $table->string('nationality')->nullable();
            $table->integer('born_year')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->index('name');
        });

        // 3. Publishers
        Schema::create('library_publishers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('contact_person')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->text('address')->nullable();
            $table->string('website')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->index('name');
        });

        // 4. Subjects
        Schema::create('library_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->unique(['branch_id', 'name']);
        });

        // 5. Book - Library Subject Pivot
        Schema::create('book_library_subject', function (Blueprint $table) {
            $table->id();
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignId('library_subject_id')->constrained('library_subjects')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['book_id', 'library_subject_id']);
        });

        // 6. Extend books table with foreign keys & classification fields
        Schema::table('books', function (Blueprint $table) {
            if (! Schema::hasColumn('books', 'category_id')) {
                $table->foreignId('category_id')->nullable()->after('category')->constrained('library_categories')->nullOnDelete();
            }
            if (! Schema::hasColumn('books', 'author_id')) {
                $table->foreignId('author_id')->nullable()->after('author')->constrained('library_authors')->nullOnDelete();
            }
            if (! Schema::hasColumn('books', 'publisher_id')) {
                $table->foreignId('publisher_id')->nullable()->after('publisher')->constrained('library_publishers')->nullOnDelete();
            }
            if (! Schema::hasColumn('books', 'ddc_code')) {
                $table->string('ddc_code')->nullable()->after('edition'); // Dewey Decimal Classification
            }
            if (! Schema::hasColumn('books', 'call_number')) {
                $table->string('call_number')->nullable()->after('ddc_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('books', function (Blueprint $table) {
            if (Schema::hasColumn('books', 'category_id')) {
                $table->dropConstrainedForeignId('category_id');
            }
            if (Schema::hasColumn('books', 'author_id')) {
                $table->dropConstrainedForeignId('author_id');
            }
            if (Schema::hasColumn('books', 'publisher_id')) {
                $table->dropConstrainedForeignId('publisher_id');
            }
            if (Schema::hasColumn('books', 'ddc_code')) {
                $table->dropColumn('ddc_code');
            }
            if (Schema::hasColumn('books', 'call_number')) {
                $table->dropColumn('call_number');
            }
        });

        Schema::dropIfExists('book_library_subject');
        Schema::dropIfExists('library_subjects');
        Schema::dropIfExists('library_publishers');
        Schema::dropIfExists('library_authors');
        Schema::dropIfExists('library_categories');
    }
};
