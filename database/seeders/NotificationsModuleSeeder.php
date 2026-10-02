<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Ensures notifications module permissions exist and are granted on existing databases.
 * notifications.view/create/edit/delete gate the campaign hub (/notification-campaigns), not the personal inbox (bell / /notifications).
 */
class NotificationsModuleSeeder extends Seeder
{
    public function run(): void
    {
        $module = Module::firstOrCreate(
            ['slug' => 'notifications'],
            [
                'name' => 'Notifications',
                'icon' => 'notifications_active',
                'route' => '/notification-campaigns',
                'order' => 21,
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
                    'name' => ucfirst($action).' Notifications',
                    'slug' => 'notifications.'.$action,
                    'action' => $action,
                    'is_system_permission' => true,
                ]
            );
            $permissionIds[$action] = $permission->id;
        }

        $all = array_values($permissionIds);
        $viewCreate = [$permissionIds['view'], $permissionIds['create']];
        $viewOnly = [$permissionIds['view']];

        $this->grant('super-admin', Permission::pluck('id')->all());
        $this->grant('branch-admin', $all);
        $this->grant('teacher', $viewCreate);
        $this->grant('staff', $viewCreate);
        $this->grant('student', $viewOnly);
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function grant(string $roleSlug, array $permissionIds): void
    {
        $role = Role::where('slug', $roleSlug)->first();
        if (! $role || $permissionIds === []) {
            return;
        }
        $role->permissions()->syncWithoutDetaching($permissionIds);
    }
}
