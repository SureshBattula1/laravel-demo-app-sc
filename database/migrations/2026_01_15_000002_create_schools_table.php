<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Create schools table
     */
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedBigInteger('main_branch_id')->nullable()->comment('Primary branch for this school');
            $table->enum('status', ['Active', 'Inactive', 'Suspended', 'UnderConstruction'])->default('Active');
            $table->json('settings')->nullable();
            $table->timestamps();
            $table->softDeletes();
            
            // Foreign key for main branch (will be set after branches table has school_id)
            $table->foreign('main_branch_id')->references('id')->on('branches')->onDelete('set null');
            
            // Indexes
            $table->index('company_id');
            $table->index('code');
            $table->index('status');
            $table->index(['company_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};

