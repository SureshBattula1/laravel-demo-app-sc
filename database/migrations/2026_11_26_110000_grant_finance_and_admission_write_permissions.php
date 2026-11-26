<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->grant('branch-admin', [
            'admissions.view',
            'admissions.create',
            'admissions.edit',
            'admissions.delete',
            'admissions.export',
        ]);

        $this->grant('accountant', [
            'fees.edit',
            'fees.delete',
            'invoices.edit',
            'invoices.delete',
            'accounts.delete',
            'transactions.edit',
            'transactions.delete',
        ]);
    }

    public function down(): void
    {
        $this->revoke('branch-admin', [
            'admissions.view',
            'admissions.create',
            'admissions.edit',
            'admissions.delete',
            'admissions.export',
        ]);

        $this->revoke('accountant', [
            'fees.edit',
            'fees.delete',
            'invoices.edit',
            'invoices.delete',
            'accounts.delete',
            'transactions.edit',
            'transactions.delete',
        ]);
    }

    private function grant(string $roleSlug, array $permissionSlugs): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');

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

    private function revoke(string $roleSlug, array $permissionSlugs): void
    {
        $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
        if (! $roleId) {
            return;
        }

        $permissionIds = DB::table('permissions')->whereIn('slug', $permissionSlugs)->pluck('id');

        DB::table('role_permissions')
            ->where('role_id', $roleId)
            ->whereIn('permission_id', $permissionIds)
            ->delete();
    }
};
