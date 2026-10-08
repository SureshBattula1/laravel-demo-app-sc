<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Shelves / Racks Master
        Schema::create('library_shelves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('floor')->nullable();
            $table->string('room')->nullable();
            $table->string('rack_number');
            $table->string('shelf_number')->nullable();
            $table->string('code')->nullable(); // Unique code, e.g. R1-S2
            $table->integer('capacity')->default(50);
            $table->foreignId('category_id')->nullable()->constrained('library_categories')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'is_active']);
            $table->index('code');
        });

        // 2. Individual Book Copies
        Schema::create('library_book_copies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->string('accession_number'); // Unique accession ID in school/branch
            $table->string('barcode');          // Barcode string
            $table->integer('copy_number')->default(1);
            $table->foreignId('shelf_id')->nullable()->constrained('library_shelves')->nullOnDelete();
            $table->enum('condition', ['New', 'Good', 'Fair', 'Damaged', 'Lost', 'Weeded'])->default('Good');
            $table->enum('status', ['Available', 'Issued', 'Reserved', 'Maintenance', 'Lost', 'Weeded'])->default('Available');
            $table->decimal('purchase_price', 10, 2)->nullable();
            $table->date('purchase_date')->nullable();
            $table->string('vendor_name')->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['book_id', 'status']);
            $table->index(['branch_id', 'status']);
            $table->unique(['branch_id', 'accession_number']);
            $table->unique(['branch_id', 'barcode']);
        });

        // 3. Stock Verification Sessions
        Schema::create('library_stock_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->string('session_title');
            $table->foreignId('verified_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->enum('status', ['In Progress', 'Completed', 'Cancelled'])->default('In Progress');
            $table->integer('total_copies_checked')->default(0);
            $table->integer('missing_count')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status']);
        });

        // 4. Stock Verification Scanned Items
        Schema::create('library_stock_verification_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verification_id')->constrained('library_stock_verifications')->onDelete('cascade');
            $table->foreignId('copy_id')->constrained('library_book_copies')->onDelete('cascade');
            $table->string('scanned_barcode')->nullable();
            $table->foreignId('shelf_id')->nullable()->constrained('library_shelves')->nullOnDelete();
            $table->enum('status', ['Found', 'Missing', 'Misplaced'])->default('Found');
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['verification_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_stock_verification_items');
        Schema::dropIfExists('library_stock_verifications');
        Schema::dropIfExists('library_book_copies');
        Schema::dropIfExists('library_shelves');
    }
};
