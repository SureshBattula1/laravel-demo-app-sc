<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add Accountant to users.role; update teachers.category_type to Teaching/Staff/Account.
     */
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        // 1. Add Staff and Account to teachers.category_type enum
        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'category_type')) {
            if ($isSqlite) {
                // SQLite: convert the CHECK-constrained enum to a plain string, then normalise data.
                Schema::table('teachers', function (Blueprint $table) {
                    $table->string('category_type')->default('Teaching')->change();
                });
                DB::table('teachers')->where('category_type', 'Non-Teaching')->update(['category_type' => 'Staff']);
            } else {
                DB::statement("ALTER TABLE teachers MODIFY COLUMN category_type ENUM('Teaching', 'Non-Teaching', 'Staff', 'Account') DEFAULT 'Teaching'");
                DB::table('teachers')->where('category_type', 'Non-Teaching')->update(['category_type' => 'Staff']);
                DB::statement("ALTER TABLE teachers MODIFY COLUMN category_type ENUM('Teaching', 'Staff', 'Account') DEFAULT 'Teaching'");
            }
        }

        if ($isSqlite) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('role')->default('Student')->change();
            });
        } else {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SuperAdmin', 'BranchAdmin', 'Teacher', 'Student', 'Parent', 'Staff', 'Accountant') NOT NULL DEFAULT 'Student'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SuperAdmin', 'BranchAdmin', 'Teacher', 'Student', 'Parent', 'Staff') NOT NULL DEFAULT 'Student'");
    }
};
