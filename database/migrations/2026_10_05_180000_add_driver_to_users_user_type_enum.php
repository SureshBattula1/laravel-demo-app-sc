<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `user_type` ENUM('Student', 'Teacher', 'Parent', 'Staff', 'Admin', 'SchoolUser', 'CompanyAdmin', 'SupportStaff', 'Driver') NULL");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `users` MODIFY COLUMN `user_type` ENUM('Student', 'Teacher', 'Parent', 'Staff', 'Admin', 'SchoolUser', 'CompanyAdmin', 'SupportStaff') NULL");
        }
    }
};
