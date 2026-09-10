<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class AssignmentsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'assignments'],
            [
                'name' => 'Assignments',
                'icon' => 'task',
                'route' => '/assignments',
                'order' => 10,
            ]
        );

        $actions = ['view', 'create', 'edit', 'delete'];
        $permissionIds = [];
        foreach ($actions as $action) {
            $permission = Permission::firstOrCreate(
                [
                    'module_id' => $module->id,
                    'action' => $action,
                ],
                [
                    'name' => ucfirst($action) . ' Assignments',
                    'slug' => 'assignments.' . $action,
                    'action' => $action,
                    'is_system_permission' => true,
                ]
            );
            $permissionIds[$action] = $permission->id;
        }

        $all = array_values($permissionIds);
        $view = [$permissionIds['view']];

        $this->grant('super-admin', Permission::pluck('id')->all());
        $this->grant('branch-admin', $all);
        $this->grant('teacher', $all);
        $this->grant('staff', $view);
        $this->grant('student', $view);
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function grant(string $roleSlug, array $permissionIds): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (!$role || $permissionIds === []) {
            return;
        }
        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
