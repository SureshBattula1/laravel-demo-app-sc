<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        // 1. Add Driver to users.role enum
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'role')) {
            if ($isSqlite) {
                Schema::table('users', function (Blueprint $table) {
                    $table->string('role')->default('Student')->change();
                });
            } else {
                DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SuperAdmin', 'BranchAdmin', 'Teacher', 'Student', 'Parent', 'Staff', 'Accountant', 'Driver') NOT NULL DEFAULT 'Student'");
            }
        }

        // 2. Add user_id and email to transport_drivers
        if (Schema::hasTable('transport_drivers')) {
            Schema::table('transport_drivers', function (Blueprint $table) {
                if (! Schema::hasColumn('transport_drivers', 'user_id')) {
                    $table->foreignId('user_id')->nullable()->after('school_id')->constrained('users')->nullOnDelete();
                }
                if (! Schema::hasColumn('transport_drivers', 'email')) {
                    $table->string('email')->nullable()->after('phone');
                }
            });
        }

        // 3. Ensure Driver role exists in roles table
        if (Schema::hasTable('roles')) {
            $driverRoleId = DB::table('roles')->where('slug', 'driver')->value('id');
            if (! $driverRoleId) {
                $driverRoleId = DB::table('roles')->insertGetId([
                    'name' => 'Driver',
                    'slug' => 'driver',
                    'description' => 'School Bus Driver / Fleet Operator with bus, route & attendance access',
                    'level' => 6,
                    'is_system_role' => true,
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // 4. Attach transport permissions to Driver role
            if (Schema::hasTable('permissions') && Schema::hasTable('role_permissions')) {
                $permissionIds = DB::table('permissions')
                    ->whereIn('slug', [
                        'transport.view',
                        'transport.edit',
                        'student_attendance.view',
                        'student_attendance.mark',
                    ])
                    ->pluck('id')
                    ->toArray();

                foreach ($permissionIds as $permId) {
                    $exists = DB::table('role_permissions')
                        ->where('role_id', $driverRoleId)
                        ->where('permission_id', $permId)
                        ->exists();

                    if (! $exists) {
                        DB::table('role_permissions')->insert([
                            'role_id' => $driverRoleId,
                            'permission_id' => $permId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $isSqlite = Schema::getConnection()->getDriverName() === 'sqlite';

        if (Schema::hasTable('transport_drivers')) {
            Schema::table('transport_drivers', function (Blueprint $table) {
                if (Schema::hasColumn('transport_drivers', 'user_id')) {
                    $table->dropForeign(['user_id']);
                    $table->dropColumn('user_id');
                }
                if (Schema::hasColumn('transport_drivers', 'email')) {
                    $table->dropColumn('email');
                }
            });
        }

        if (! $isSqlite && Schema::hasTable('users')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('SuperAdmin', 'BranchAdmin', 'Teacher', 'Student', 'Parent', 'Staff', 'Accountant') NOT NULL DEFAULT 'Student'");
        }
    }
};
