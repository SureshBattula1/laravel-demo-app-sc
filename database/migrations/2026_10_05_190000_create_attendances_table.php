<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('attendances')) {
            Schema::create('attendances', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignId('branch_id')->constrained('branches')->onDelete('cascade');
                $table->foreignId('school_id')->nullable()->constrained('schools')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
                $table->string('user_type')->nullable();
                $table->date('attendance_date');
                $table->string('status')->default('present');
                $table->dateTime('check_in_time')->nullable();
                $table->dateTime('check_out_time')->nullable();
                $table->decimal('total_hours', 5, 2)->default(0);
                $table->integer('odometer_start')->nullable();
                $table->integer('odometer_end')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('remarks')->nullable();
                $table->unsignedBigInteger('marked_by')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['user_id', 'attendance_date']);
                $table->index(['branch_id', 'attendance_date']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
