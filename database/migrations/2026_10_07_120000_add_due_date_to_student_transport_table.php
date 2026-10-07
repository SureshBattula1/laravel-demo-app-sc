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
        if (Schema::hasTable('student_transport')) {
            Schema::table('student_transport', function (Blueprint $table) {
                if (! Schema::hasColumn('student_transport', 'due_date')) {
                    $table->date('due_date')->nullable()->after('annual_fee');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('student_transport')) {
            Schema::table('student_transport', function (Blueprint $table) {
                if (Schema::hasColumn('student_transport', 'due_date')) {
                    $table->dropColumn('due_date');
                }
            });
        }
    }
};
