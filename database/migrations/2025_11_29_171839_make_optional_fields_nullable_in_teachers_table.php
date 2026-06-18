<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Make fields nullable to match frontend validation requirements
     */
    public function up(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->date('joining_date')->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('state', 100)->nullable()->change();
            $table->string('pincode', 10)->nullable()->change();
            $table->string('emergency_contact_name', 100)->nullable()->change();
            $table->string('emergency_contact_phone', 20)->nullable()->change();
            $table->decimal('basic_salary', 10, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teachers', function (Blueprint $table) {
            $table->date('joining_date')->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->string('state', 100)->nullable(false)->change();
            $table->string('pincode', 10)->nullable(false)->change();
            $table->string('emergency_contact_name', 100)->nullable(false)->change();
            $table->string('emergency_contact_phone', 20)->nullable(false)->change();
            $table->decimal('basic_salary', 10, 2)->nullable(false)->change();
        });
    }
};
