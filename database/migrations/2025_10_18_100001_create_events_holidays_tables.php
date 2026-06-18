<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Events and holidays tables
     */
    public function up(): void
    {
        // EVENTS TABLE
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('start_date');
            $table->date('end_date');
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->string('location')->nullable();
            $table->enum('type', ['Academic', 'Sports', 'Cultural', 'Meeting', 'Other'])->default('Academic');
            $table->boolean('is_public')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'start_date']);
            $table->index(['type', 'is_active']);
        });

        // HOLIDAYS TABLE - Complete with all final modifications
        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->onDelete('cascade');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('name'); // Same as title (for backward compatibility)
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('date');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->enum('type', ['National', 'State', 'School', 'Optional', 'Restricted', 'Religious', 'Regional'])->default('School');
            $table->string('color', 20)->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('academic_year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'date']);
            $table->index(['type', 'is_active']);
            $table->index(['start_date', 'end_date']);
            $table->index('academic_year');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('events');
    }
};

