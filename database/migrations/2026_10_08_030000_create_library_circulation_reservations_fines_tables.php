<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Extend book_issues with copy_id and renewal tracking
        Schema::table('book_issues', function (Blueprint $table) {
            if (! Schema::hasColumn('book_issues', 'copy_id')) {
                $table->foreignId('copy_id')->nullable()->after('book_id')
                    ->constrained('library_book_copies')->nullOnDelete();
            }
            if (! Schema::hasColumn('book_issues', 'renewed_count')) {
                $table->unsignedTinyInteger('renewed_count')->default(0)->after('status');
            }
            if (! Schema::hasColumn('book_issues', 'last_renewed_at')) {
                $table->timestamp('last_renewed_at')->nullable()->after('renewed_count');
            }
        });

        // 2. Reservations
        Schema::create('library_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('book_id')->constrained('books')->onDelete('cascade');
            $table->foreignId('copy_id')->nullable()->constrained('library_book_copies')->nullOnDelete();
            $table->foreignId('member_id')->constrained('users')->onDelete('cascade');
            $table->enum('borrower_type', ['Student', 'Teacher'])->default('Student');
            $table->timestamp('reserved_at');
            $table->timestamp('hold_until')->nullable();
            $table->enum('status', ['Pending', 'Ready for Pickup', 'Fulfilled', 'Cancelled', 'Expired'])->default('Pending');
            $table->timestamp('notified_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['book_id', 'status']);
            $table->index(['member_id', 'status']);
            $table->index(['branch_id', 'status']);
        });

        // 3. Fine Ledger
        Schema::create('library_fines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('book_issue_id')->nullable()->constrained('book_issues')->nullOnDelete();
            $table->foreignId('member_id')->constrained('users')->onDelete('cascade');
            $table->enum('borrower_type', ['Student', 'Teacher'])->default('Student');
            $table->enum('type', ['Late Return', 'Lost Book', 'Damaged Book', 'Other'])->default('Late Return');
            $table->decimal('amount', 10, 2);
            $table->decimal('paid_amount', 10, 2)->default(0);
            $table->decimal('waived_amount', 10, 2)->default(0);
            $table->enum('status', ['Pending', 'Paid', 'Partially Paid', 'Waived'])->default('Pending');
            $table->enum('payment_method', ['Cash', 'Online', 'Fee Account', 'Other'])->nullable();
            $table->string('transaction_reference')->nullable();
            $table->text('waived_reason')->nullable();
            $table->foreignId('collected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();

            $table->index(['member_id', 'status']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_fines');
        Schema::dropIfExists('library_reservations');

        Schema::table('book_issues', function (Blueprint $table) {
            if (Schema::hasColumn('book_issues', 'last_renewed_at')) {
                $table->dropColumn('last_renewed_at');
            }
            if (Schema::hasColumn('book_issues', 'renewed_count')) {
                $table->dropColumn('renewed_count');
            }
            if (Schema::hasColumn('book_issues', 'copy_id')) {
                $table->dropConstrainedForeignId('copy_id');
            }
        });
    }
};
