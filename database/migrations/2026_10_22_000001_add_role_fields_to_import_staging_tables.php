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
        Schema::table('student_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('student_imports', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('student_imports', 'role_name')) {
                $table->string('role_name', 50)->nullable()->after('role_id');
            }
        });

        Schema::table('teacher_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('teacher_imports', 'role_id')) {
                $table->unsignedBigInteger('role_id')->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('teacher_imports', 'role_name')) {
                $table->string('role_name', 50)->nullable()->after('role_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_imports', function (Blueprint $table) {
            if (Schema::hasColumn('student_imports', 'role_name')) {
                $table->dropColumn('role_name');
            }
            if (Schema::hasColumn('student_imports', 'role_id')) {
                $table->dropColumn('role_id');
            }
        });

        Schema::table('teacher_imports', function (Blueprint $table) {
            if (Schema::hasColumn('teacher_imports', 'role_name')) {
                $table->dropColumn('role_name');
            }
            if (Schema::hasColumn('teacher_imports', 'role_id')) {
                $table->dropColumn('role_id');
            }
        });
    }
};
