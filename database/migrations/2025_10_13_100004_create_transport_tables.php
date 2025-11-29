<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Transport management tables
     */
    public function up(): void
    {
        // TRANSPORT ROUTES TABLE
        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('route_number')->unique();
            $table->string('route_name');
            $table->text('description')->nullable();
            $table->json('stops');
            $table->decimal('distance', 8, 2)->nullable();
            $table->integer('estimated_time')->nullable();
            $table->decimal('fare', 8, 2);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'is_active']);
            $table->index('route_number');
        });

        // VEHICLES TABLE
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->foreignId('route_id')->nullable()->constrained('transport_routes')->onDelete('set null');
            $table->foreignId('driver_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('vehicle_number')->unique();
            $table->enum('vehicle_type', ['Bus', 'Van', 'Car']);
            $table->string('make')->nullable();
            $table->string('model')->nullable();
            $table->integer('capacity');
            $table->date('insurance_expiry')->nullable();
            $table->date('fitness_expiry')->nullable();
            $table->enum('status', ['Active', 'Maintenance', 'Inactive'])->default('Active');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['branch_id', 'status']);
            $table->index('route_id');
            $table->index('vehicle_number');
        });

        // STUDENT TRANSPORT TABLE
        Schema::create('student_transport', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('route_id')->constrained('transport_routes')->onDelete('cascade');
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->onDelete('set null');
            $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
            $table->string('stop_name');
            $table->time('pickup_time');
            $table->time('drop_time');
            $table->decimal('monthly_fee', 8, 2);
            $table->enum('status', ['Active', 'Inactive'])->default('Active');
            $table->timestamps();
            
            $table->index(['student_id', 'status']);
            $table->index('route_id');
            $table->index('vehicle_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_transport');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('transport_routes');
    }
};

