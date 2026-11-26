<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('id_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('person_type', 20);
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->unsignedBigInteger('school_id')->nullable();
            $table->unsignedBigInteger('academic_year_id')->nullable();
            $table->string('card_number', 80);
            $table->string('status', 20)->default('draft');
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('replaced_by_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'academic_year_id', 'status']);
        });

        Schema::create('id_card_scans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_card_id')->nullable()->constrained('id_cards')->nullOnDelete();
            $table->foreignId('scanner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('gate_name', 80)->default('Desk');
            $table->string('result', 20);
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });

        $this->seedPermissions();
    }

    public function down(): void
    {
        Schema::dropIfExists('id_card_scans');
        Schema::dropIfExists('id_cards');
    }

    private function seedPermissions(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('permissions')) {
            return;
        }

        $now = now();
        $moduleId = DB::table('modules')->where('slug', 'id_cards')->value('id');
        if (! $moduleId) {
            $moduleId = DB::table('modules')->insertGetId([
                'name' => 'ID Cards',
                'slug' => 'id_cards',
                'icon' => 'badge',
                'route' => '/id-cards',
                'order' => 31,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $actions = [
            'view' => 'View ID Cards',
            'issue' => 'Issue ID Cards',
            'verify' => 'Verify ID Cards',
        ];
        $permissionIds = [];
        foreach ($actions as $action => $name) {
            $slug = 'id_cards.'.$action;
            $id = DB::table('permissions')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('permissions')->insertGetId([
                    'module_id' => $moduleId,
                    'name' => $name,
                    'slug' => $slug,
                    'action' => $action,
                    'is_system_permission' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $permissionIds[$slug] = $id;
        }

        $grants = [
            'branch-admin' => ['id_cards.view', 'id_cards.issue', 'id_cards.verify'],
            'staff' => ['id_cards.view', 'id_cards.issue', 'id_cards.verify'],
        ];
        foreach ($grants as $roleSlug => $slugs) {
            $roleId = DB::table('roles')->where('slug', $roleSlug)->value('id');
            if (! $roleId) {
                continue;
            }
            foreach ($slugs as $slug) {
                $exists = DB::table('role_permissions')
                    ->where('role_id', $roleId)
                    ->where('permission_id', $permissionIds[$slug])
                    ->exists();
                if (! $exists) {
                    DB::table('role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => $permissionIds[$slug],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }
};
