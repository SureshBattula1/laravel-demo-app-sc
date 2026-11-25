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
        if (Schema::hasTable('timetables')) {
            Schema::table('timetables', function (Blueprint $table) {
                if (Schema::hasColumn('timetables', 'teacher_id')) {
                    $table->unsignedBigInteger('teacher_id')->nullable()->change();
                }
                if (Schema::hasColumn('timetables', 'grade_level')) {
                    $table->string('grade_level', 50)->nullable()->change();
                }
                if (! Schema::hasColumn('timetables', 'duration_minutes')) {
                    $table->unsignedSmallInteger('duration_minutes')->nullable()->after('end_time');
                }
                if (! Schema::hasColumn('timetables', 'schedule_mode')) {
                    $table->string('schedule_mode', 20)->default('weekly')->after('room_number');
                }
                if (! Schema::hasColumn('timetables', 'is_default_weekly')) {
                    $table->boolean('is_default_weekly')->default(true)->after('schedule_mode');
                }
                if (! Schema::hasColumn('timetables', 'effective_date')) {
                    $table->date('effective_date')->nullable()->after('is_default_weekly');
                }
                if (! Schema::hasColumn('timetables', 'active_days')) {
                    $table->json('active_days')->nullable()->after('effective_date');
                }
            });

            // Backfill grade_level from grade and duration_minutes for existing rows
            if (Schema::hasColumn('timetables', 'grade_level') && Schema::hasColumn('timetables', 'grade')) {
                DB::table('timetables')
                    ->whereNull('grade_level')
                    ->whereNotNull('grade')
                    ->update(['grade_level' => DB::raw('grade')]);
            }

            $driver = Schema::getConnection()->getDriverName();
            if (in_array($driver, ['mysql', 'mariadb'], true)) {
                DB::statement('
                    UPDATE timetables
                    SET duration_minutes = TIMESTAMPDIFF(MINUTE, start_time, end_time)
                    WHERE duration_minutes IS NULL
                      AND start_time IS NOT NULL
                      AND end_time IS NOT NULL
                      AND end_time > start_time
                ');
            }

            Schema::table('timetables', function (Blueprint $table) {
                if (! $this->indexExists('timetables', 'timetables_scope_mode_slot_idx')) {
                    $table->index(
                        ['branch_id', 'grade', 'section', 'academic_year_id', 'schedule_mode', 'effective_date', 'day', 'period_number'],
                        'timetables_scope_mode_slot_idx'
                    );
                }
            });
        }

        $this->ensureTimetablePermissions();
    }

    public function down(): void
    {
        if (! Schema::hasTable('timetables')) {
            return;
        }

        Schema::table('timetables', function (Blueprint $table) {
            if ($this->indexExists('timetables', 'timetables_scope_mode_slot_idx')) {
                $table->dropIndex('timetables_scope_mode_slot_idx');
            }
            $dropCols = array_filter(
                ['duration_minutes', 'schedule_mode', 'is_default_weekly', 'effective_date', 'active_days'],
                fn (string $col) => Schema::hasColumn('timetables', $col)
            );
            if (! empty($dropCols)) {
                $table->dropColumn($dropCols);
            }
        });
    }

    private function ensureTimetablePermissions(): void
    {
        if (! Schema::hasTable('modules') || ! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $module = Module::firstOrCreate(
            ['slug' => 'timetable'],
            [
                'name' => 'Timetable',
                'slug' => 'timetable',
                'icon' => 'schedule',
                'route' => '/timetable',
                'order' => 14,
                'is_active' => true,
            ]
        );

        $actions = ['view', 'create', 'edit', 'delete', 'manage', 'export'];
        $permIdsBySlug = [];

        foreach ($actions as $action) {
            $slug = 'timetable.'.$action;
            $perm = Permission::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'action' => $action,
                ],
                [
                    'module_id' => $module->id,
                    'name' => ucfirst($action).' Timetable',
                    'slug' => $slug,
                    'action' => $action,
                    'is_system_permission' => true,
                ]
            );
            $permIdsBySlug[$slug] = $perm->id;
        }

        $roleGrants = [
            'super-admin' => array_keys($permIdsBySlug),
            'branch-admin' => array_keys($permIdsBySlug),
            'teacher' => ['timetable.view', 'timetable.create', 'timetable.edit'],
            'staff' => ['timetable.view', 'timetable.create', 'timetable.edit', 'timetable.manage'],
            'student' => ['timetable.view'],
            'parent' => ['timetable.view'],
        ];

        foreach ($roleGrants as $roleSlug => $slugs) {
            $role = Role::where('slug', $roleSlug)->first();
            if (! $role) {
                continue;
            }
            $ids = array_values(array_intersect_key($permIdsBySlug, array_flip($slugs)));
            if (! empty($ids)) {
                $role->permissions()->syncWithoutDetaching($ids);
            }
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            return false;
        }
        $db = $connection->getDatabaseName();

        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$db, $table, $index]
        );

        return count($rows) > 0;
    }
};
