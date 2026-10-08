<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. REUSABLE STOPS MASTER
        if (! Schema::hasTable('transport_stops_master')) {
            Schema::create('transport_stops_master', function (Blueprint $table) {
                $table->id();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->string('stop_name');
                $table->string('location')->nullable();
                $table->string('landmark')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->time('default_pickup_time')->nullable();
                $table->time('default_drop_time')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['branch_id', 'is_active']);
                $table->index('stop_name');
            });
        }

        // 2. DAILY OPERATIONAL TRIPS
        if (! Schema::hasTable('transport_trips')) {
            Schema::create('transport_trips', function (Blueprint $table) {
                $table->id();
                $table->string('trip_code', 50)->nullable();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->foreignId('route_id')->constrained('transport_routes')->onDelete('cascade');
                $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
                $table->foreignId('transport_driver_id')->nullable()->constrained('transport_drivers')->nullOnDelete();
                $table->date('trip_date');
                $table->enum('trip_type', ['Morning', 'Afternoon', 'Special'])->default('Morning');
                $table->enum('status', ['Scheduled', 'Started', 'In Progress', 'Completed', 'Cancelled'])->default('Scheduled');
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->foreignId('current_stop_id')->nullable()->constrained('route_stops')->nullOnDelete();
                $table->decimal('current_latitude', 10, 7)->nullable();
                $table->decimal('current_longitude', 10, 7)->nullable();
                $table->decimal('current_speed', 5, 2)->nullable();
                $table->integer('odometer_start')->nullable();
                $table->integer('odometer_end')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['branch_id', 'trip_date', 'status']);
                $table->index(['route_id', 'trip_date']);
                $table->index('trip_code');
            });
        }

        // 3. STUDENT BOARDING & DROP LOGS
        if (! Schema::hasTable('trip_boarding_logs')) {
            Schema::create('trip_boarding_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('trip_id')->constrained('transport_trips')->onDelete('cascade');
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('route_id')->nullable()->constrained('transport_routes')->nullOnDelete();
                $table->foreignId('pickup_stop_id')->nullable()->constrained('route_stops')->nullOnDelete();
                $table->foreignId('drop_stop_id')->nullable()->constrained('route_stops')->nullOnDelete();
                $table->enum('boarding_status', ['Not Boarded', 'Boarded', 'Absent'])->default('Not Boarded');
                $table->dateTime('boarded_at')->nullable();
                $table->enum('drop_status', ['Pending', 'Dropped'])->default('Pending');
                $table->dateTime('dropped_at')->nullable();
                $table->foreignId('marked_by')->nullable()->constrained('users')->nullOnDelete();
                $table->enum('verification_method', ['Manual', 'QR', 'RFID'])->default('Manual');
                $table->timestamps();

                $table->index(['trip_id', 'student_id']);
                $table->index(['trip_id', 'boarding_status']);
                $table->index(['trip_id', 'drop_status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_boarding_logs');
        Schema::dropIfExists('transport_trips');
        Schema::dropIfExists('transport_stops_master');
    }
};
