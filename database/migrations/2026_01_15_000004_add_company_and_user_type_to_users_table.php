<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations - Add company_id and user_type to users table
     */
    public function up(): void
    {
        // Add company_id if it doesn't exist
        if (!Schema::hasColumn('users', 'company_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->after('branch_id')->constrained('companies')->onDelete('set null');
                $table->index('company_id');
            });
        }

        // Handle user_type column - modify existing enum to include new values
        if (Schema::hasColumn('users', 'user_type')) {
            if (Schema::getConnection()->getDriverName() === 'sqlite') {
                // SQLite: the enum is backed by a CHECK constraint. Convert it to a plain
                // string so the new user types (SchoolUser/CompanyAdmin/SupportStaff) are allowed.
                Schema::table('users', function (Blueprint $table) {
                    $table->string('user_type')->nullable()->change();
                });
            } else {
                // MySQL requires dropping and recreating the enum to add new values
                DB::statement("ALTER TABLE `users` MODIFY COLUMN `user_type` ENUM('Student', 'Teacher', 'Parent', 'Staff', 'Admin', 'SchoolUser', 'CompanyAdmin', 'SupportStaff') NULL");
            }
        } else {
            // Add new user_type column if it doesn't exist
            Schema::table('users', function (Blueprint $table) {
                $table->enum('user_type', ['SchoolUser', 'CompanyAdmin', 'SupportStaff'])->default('SchoolUser')->after('role');
            });
        }

        // Add indexes if they don't exist
        Schema::table('users', function (Blueprint $table) {
            // Add index on user_type if it doesn't exist
            if (Schema::hasColumn('users', 'user_type')) {
                try {
                    $table->index('user_type');
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
            
            // Add composite index if both columns exist
            if (Schema::hasColumn('users', 'company_id') && Schema::hasColumn('users', 'user_type')) {
                try {
                    $table->index(['company_id', 'user_type']);
                } catch (\Exception $e) {
                    // Index might already exist, ignore
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->dropIndex(['company_id']);
            $table->dropIndex(['user_type']);
            $table->dropIndex(['company_id', 'user_type']);
            $table->dropColumn(['company_id', 'user_type']);
        });
    }
};

