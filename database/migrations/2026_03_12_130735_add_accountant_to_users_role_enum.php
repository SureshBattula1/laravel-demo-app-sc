<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations - Add Accountant to users.role; update teachers.category_type to Teaching/Staff/Account.
     */
    public function up(): void
    {
        // 1. Add Staff and Account to teachers.category_type enum
        if (Schema::hasTable('teachers') && Schema::hasColumn('teachers', 'category_type')) {
            DB::statement("ALTER TABLE teachers MODIFY COLUMN category_type ENUM('Teaching', 'Non-Teaching', 'Staff', 'Account') DEFAULT 'Teaching'");
            DB::table('teachers')->where('category_type', 'Non-Teaching')->update(['category_type' => 'Staff']);
            DB::statement("ALTER TABLE teachers MODIFY COLUMN category_type ENUM('Teaching', 'Staff', 'Account') DEFAULT 'Teaching'");
        }
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SuperAdmin', 'BranchAdmin', 'Teacher', 'Student', 'Parent', 'Staff', 'Accountant') NOT NULL DEFAULT 'Student'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SuperAdmin', 'BranchAdmin', 'Teacher', 'Student', 'Parent', 'Staff') NOT NULL DEFAULT 'Student'");
    }
};
