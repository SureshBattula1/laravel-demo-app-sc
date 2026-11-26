<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $roleId = DB::table('roles')->where('slug', 'branch-admin')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['exams.delete', 'settings.edit'])
            ->pluck('id');

        foreach ($permissionIds as $permissionId) {
            $exists = DB::table('role_permissions')
                ->where('role_id', $roleId)
                ->where('permission_id', $permissionId)
                ->exists();
            if ($exists) {
                continue;
            }
            DB::table('role_permissions')->insert([
                'role_id' => $roleId,
                'permission_id' => $permissionId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('roles')->where('slug', 'branch-admin')->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')
            ->whereIn('slug', ['exams.delete', 'settings.edit'])
            ->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
