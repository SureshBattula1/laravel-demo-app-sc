<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Procurement Orders
        Schema::create('library_procurements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('po_number');
            $table->string('vendor_name');
            $table->string('vendor_contact')->nullable();
            $table->date('order_date');
            $table->date('delivery_date')->nullable();
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->enum('status', ['Draft', 'Ordered', 'Received', 'Cancelled'])->default('Draft');
            $table->string('invoice_number')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'status']);
            $table->unique(['branch_id', 'po_number']);
        });

        // 2. Procurement Line Items
        Schema::create('library_procurement_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_id')->constrained('library_procurements')->onDelete('cascade');
            $table->foreignId('book_id')->nullable()->constrained('books')->nullOnDelete();
            $table->string('title');
            $table->string('author')->nullable();
            $table->string('isbn')->nullable();
            $table->string('publisher')->nullable();
            $table->integer('quantity_ordered')->default(1);
            $table->integer('quantity_received')->default(0);
            $table->decimal('unit_price', 10, 2)->default(0);
            $table->decimal('total_price', 10, 2)->default(0);
            $table->enum('accession_status', ['Pending', 'Generated'])->default('Pending');
            $table->timestamps();

            $table->index('procurement_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_procurement_items');
        Schema::dropIfExists('library_procurements');
    }
};
