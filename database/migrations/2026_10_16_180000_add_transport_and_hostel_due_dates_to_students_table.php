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
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (! Schema::hasColumn('students', 'transport_due_date')) {
                    $table->date('transport_due_date')->nullable()->after('transport_fee');
                }
                if (! Schema::hasColumn('students', 'hostel_due_date')) {
                    $table->date('hostel_due_date')->nullable()->after('hostel_fee');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('students')) {
            Schema::table('students', function (Blueprint $table) {
                if (Schema::hasColumn('students', 'transport_due_date')) {
                    $table->dropColumn('transport_due_date');
                }
                if (Schema::hasColumn('students', 'hostel_due_date')) {
                    $table->dropColumn('hostel_due_date');
                }
            });
        }
    }
};
