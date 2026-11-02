<?php

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();

                // Multi-tenant & Academic Year context
                $table->unsignedBigInteger('company_id')->nullable()->index();
                $table->unsignedBigInteger('school_id')->nullable()->index();
                $table->unsignedBigInteger('branch_id')->nullable()->index();
                $table->unsignedBigInteger('academic_year_id')->nullable()->index();

                // Actor & Impersonation attribution
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->string('user_name', 191)->nullable();
                $table->string('user_email', 191)->nullable();
                $table->string('user_role', 64)->nullable()->index();
                $table->unsignedBigInteger('impersonator_id')->nullable()->index();

                // Classification
                $table->string('module', 64)->index();
                $table->string('action', 64)->index();
                $table->string('severity', 24)->default('info')->index(); // info, warning, critical
                $table->string('description', 500);

                // Target entity
                $table->string('auditable_type', 191)->nullable()->index();
                $table->string('auditable_id', 64)->nullable()->index();
                $table->string('record_label', 255)->nullable();

                // Field-level diff & contextual metadata
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->json('changed_fields')->nullable();
                $table->json('metadata')->nullable();

                // Request & Client telemetry
                $table->string('request_method', 16)->nullable();
                $table->string('request_url', 500)->nullable();
                $table->string('route_name', 191)->nullable();
                $table->unsignedSmallInteger('status_code')->nullable()->index();
                $table->decimal('duration_ms', 10, 2)->nullable();
                $table->string('ip_address', 64)->nullable()->index();
                $table->text('user_agent')->nullable();
                $table->string('device_info', 191)->nullable();

                // Tamper-evident SHA-256 signature
                $table->string('signature_hash', 64)->nullable();

                $table->timestamp('created_at')->useCurrent()->index();

                $table->index(['school_id', 'branch_id', 'created_at'], 'idx_audit_school_branch_date');
                $table->index(['module', 'action', 'created_at'], 'idx_audit_module_action_date');
                $table->index(['auditable_type', 'auditable_id'], 'idx_audit_entity_lookup');
            });
        }

        // Seed Audit Logs Module & Permissions for SuperAdmin and BranchAdmin
        if (Schema::hasTable('modules') && Schema::hasTable('permissions')) {
            $auditModule = Module::firstOrCreate(
                ['slug' => 'audit_logs'],
                [
                    'name' => 'Audit Logs',
                    'slug' => 'audit_logs',
                    'icon' => 'policy',
                    'route' => '/settings/audit-logs',
                    'order' => 95,
                    'is_active' => true,
                ]
            );

            $permissions = [
                [
                    'action' => 'view',
                    'name' => 'View Audit Logs',
                    'slug' => 'audit_logs.view',
                    'description' => 'View system-wide audit trail, field diffs, and security events',
                ],
                [
                    'action' => 'export',
                    'name' => 'Export Audit Logs',
                    'slug' => 'audit_logs.export',
                    'description' => 'Export audit logs to CSV for compliance and security review',
                ],
            ];

            $permissionIds = [];
            foreach ($permissions as $perm) {
                $record = Permission::firstOrCreate(
                    ['slug' => $perm['slug']],
                    [
                        'module_id' => $auditModule->id,
                        'name' => $perm['name'],
                        'slug' => $perm['slug'],
                        'action' => $perm['action'],
                        'description' => $perm['description'],
                        'is_system_permission' => true,
                    ]
                );
                $permissionIds[] = $record->id;
            }

            if (Schema::hasTable('roles') && Schema::hasTable('role_permissions')) {
                $adminRoles = Role::where('slug', 'super-admin')
                    ->orWhere('slug', 'like', 'branch-admin%')
                    ->orWhere('name', 'like', '%Super Admin%')
                    ->orWhere('name', 'like', '%Branch Admin%')
                    ->get();

                foreach ($adminRoles as $role) {
                    $role->permissions()->syncWithoutDetaching($permissionIds);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modules')) {
            $module = Module::where('slug', 'audit_logs')->first();
            if ($module) {
                $permIds = Permission::where('module_id', $module->id)->pluck('id')->all();
                if (! empty($permIds)) {
                    DB::table('role_permissions')->whereIn('permission_id', $permIds)->delete();
                    DB::table('user_permissions')->whereIn('permission_id', $permIds)->delete();
                    Permission::whereIn('id', $permIds)->delete();
                }
                $module->delete();
            }
        }

        Schema::dropIfExists('audit_logs');
    }
};
