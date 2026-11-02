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
        if (! Schema::hasTable('promotion_batches')) {
            Schema::create('promotion_batches', function (Blueprint $table) {
                $table->id();
                $table->string('batch_id', 64)->unique();
                $table->foreignId('school_id')->nullable()->constrained('schools')->onDelete('cascade');
                $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
                $table->foreignId('from_academic_year_id')->nullable()->constrained('academic_years')->onDelete('set null');
                $table->foreignId('to_academic_year_id')->nullable()->constrained('academic_years')->onDelete('set null');
                $table->string('from_grade', 50);
                $table->string('to_grade', 50);
                $table->string('from_section', 50)->nullable();
                $table->string('to_section', 50)->nullable();
                $table->integer('total_students')->default(0);
                $table->integer('promoted_count')->default(0);
                $table->integer('retained_count')->default(0);
                $table->integer('reverted_count')->default(0);
                $table->decimal('total_fees_carried_forward', 12, 2)->default(0.00);
                $table->string('status', 30)->default('completed'); // completed, partially_reverted, reverted
                $table->foreignId('executed_by')->nullable()->constrained('users')->onDelete('set null');
                $table->foreignId('reverted_by')->nullable()->constrained('users')->onDelete('set null');
                $table->timestamp('reverted_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->index(['school_id', 'branch_id']);
                $table->index('status');
                $table->index(['from_academic_year_id', 'to_academic_year_id']);
            });
        }

        if (Schema::hasTable('class_upgrades') && ! Schema::hasColumn('class_upgrades', 'batch_id')) {
            Schema::table('class_upgrades', function (Blueprint $table) {
                $table->string('batch_id', 64)->nullable()->index()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('class_upgrades') && Schema::hasColumn('class_upgrades', 'batch_id')) {
            Schema::table('class_upgrades', function (Blueprint $table) {
                $table->dropColumn('batch_id');
            });
        }

        Schema::dropIfExists('promotion_batches');
    }
};
