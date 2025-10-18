<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        
        // Clear existing data
        DB::table('user_permissions')->truncate();
        DB::table('user_roles')->truncate();
        DB::table('role_permissions')->truncate();
        DB::table('permissions')->truncate();
        DB::table('modules')->truncate();
        DB::table('roles')->truncate();
        
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Create Roles
        $this->createRoles();
        
        // Create Modules and Permissions
        $this->createModulesAndPermissions();
        
        // Assign permissions to roles
        $this->assignRolePermissions();
    }

    /**
     * Create system roles
     */
    private function createRoles(): void
    {
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access with all permissions',
                'level' => 1,
                'is_system_role' => true
            ],
            [
                'name' => 'Branch Admin',
                'slug' => 'branch-admin',
                'description' => 'Branch-level administration access',
                'level' => 2,
                'is_system_role' => true
            ],
            [
                'name' => 'Teacher',
                'slug' => 'teacher',
                'description' => 'Teaching staff with student and academic access',
                'level' => 3,
                'is_system_role' => true
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Administrative staff access',
                'level' => 4,
                'is_system_role' => true
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Accounting and finance management',
                'level' => 4,
                'is_system_role' => true
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Student access to view own information',
                'level' => 5,
                'is_system_role' => true
            ],
            [
                'name' => 'Parent',
                'slug' => 'parent',
                'description' => 'Parent/Guardian access to children information',
                'level' => 6,
                'is_system_role' => true
            ]
        ];

        foreach ($roles as $roleData) {
            Role::create($roleData);
        }
    }

    /**
     * Create modules with their permissions
     */
    private function createModulesAndPermissions(): void
    {
        $modulesData = [
            [
                'name' => 'Dashboard',
                'slug' => 'dashboard',
                'icon' => 'dashboard',
                'route' => '/dashboard',
                'order' => 1,
                'permissions' => ['view']
            ],
            [
                'name' => 'Students',
                'slug' => 'students',
                'icon' => 'school',
                'route' => '/students',
                'order' => 2,
                'permissions' => ['view', 'create', 'edit', 'delete', 'export', 'promote', 'transfer']
            ],
            [
                'name' => 'Teachers',
                'slug' => 'teachers',
                'icon' => 'person',
                'route' => '/teachers',
                'order' => 3,
                'permissions' => ['view', 'create', 'edit', 'delete', 'export']
            ],
            [
                'name' => 'Attendance',
                'slug' => 'attendance',
                'icon' => 'check_circle',
                'route' => '/attendance',
                'order' => 4,
                'permissions' => ['view', 'mark', 'edit', 'report', 'export']
            ],
            [
                'name' => 'Branches',
                'slug' => 'branches',
                'icon' => 'business',
                'route' => '/branches',
                'order' => 5,
                'permissions' => ['view', 'create', 'edit', 'delete', 'stats']
            ],
            [
                'name' => 'Accounts',
                'slug' => 'accounts',
                'icon' => 'account_balance',
                'route' => '/accounts',
                'order' => 6,
                'permissions' => ['view', 'create', 'edit', 'delete', 'approve', 'export']
            ],
            [
                'name' => 'Transactions',
                'slug' => 'transactions',
                'icon' => 'receipt',
                'route' => '/accounts/transactions',
                'order' => 7,
                'permissions' => ['view', 'create', 'edit', 'delete', 'approve', 'reject']
            ],
            [
                'name' => 'Fees',
                'slug' => 'fees',
                'icon' => 'payment',
                'route' => '/fees',
                'order' => 8,
                'permissions' => ['view', 'create', 'edit', 'delete', 'collect', 'report']
            ],
            [
                'name' => 'Exams',
                'slug' => 'exams',
                'icon' => 'assignment',
                'route' => '/exams',
                'order' => 9,
                'permissions' => ['view', 'create', 'edit', 'delete', 'results']
            ],
            [
                'name' => 'Grades',
                'slug' => 'grades',
                'icon' => 'grade',
                'route' => '/grades',
                'order' => 10,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Sections',
                'slug' => 'sections',
                'icon' => 'class',
                'route' => '/sections',
                'order' => 11,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Subjects',
                'slug' => 'subjects',
                'icon' => 'book',
                'route' => '/subjects',
                'order' => 12,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Departments',
                'slug' => 'departments',
                'icon' => 'domain',
                'route' => '/departments',
                'order' => 13,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Holidays',
                'slug' => 'holidays',
                'icon' => 'event',
                'route' => '/holidays',
                'order' => 14,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Invoices',
                'slug' => 'invoices',
                'icon' => 'description',
                'route' => '/invoices',
                'order' => 15,
                'permissions' => ['view', 'create', 'edit', 'delete', 'send', 'payment']
            ],
            [
                'name' => 'Groups',
                'slug' => 'groups',
                'icon' => 'group',
                'route' => '/groups',
                'order' => 16,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Reports',
                'slug' => 'reports',
                'icon' => 'assessment',
                'route' => '/reports',
                'order' => 17,
                'permissions' => ['view', 'generate', 'export']
            ],
            [
                'name' => 'Settings',
                'slug' => 'settings',
                'icon' => 'settings',
                'route' => '/settings',
                'order' => 18,
                'permissions' => ['view', 'edit']
            ],
            [
                'name' => 'Users',
                'slug' => 'users',
                'icon' => 'people',
                'route' => '/users',
                'order' => 19,
                'permissions' => ['view', 'create', 'edit', 'delete', 'manage_roles']
            ]
        ];

        foreach ($modulesData as $moduleData) {
            $permissions = $moduleData['permissions'];
            unset($moduleData['permissions']);

            $module = Module::create($moduleData);

            foreach ($permissions as $action) {
                Permission::create([
                    'module_id' => $module->id,
                    'name' => ucfirst($action) . ' ' . $module->name,
                    'slug' => $module->slug . '.' . $action,
                    'action' => $action,
                    'is_system_permission' => true
                ]);
            }
        }
    }

    /**
     * Assign permissions to roles based on access levels
     */
    private function assignRolePermissions(): void
    {
        // Super Admin - ALL permissions
        $superAdmin = Role::where('slug', 'super-admin')->first();
        $superAdmin->syncPermissions(Permission::all()->pluck('id')->toArray());

        // Branch Admin - Comprehensive access (NO FINANCIAL MODULES)
        $branchAdmin = Role::where('slug', 'branch-admin')->first();
        $branchAdmin->syncPermissions(
            Permission::whereIn('slug', [
                // Dashboard
                'dashboard.view',
                // Students - full access
                'students.view', 'students.create', 'students.edit', 'students.delete', 
                'students.export', 'students.promote', 'students.transfer',
                // Teachers - full access
                'teachers.view', 'teachers.create', 'teachers.edit', 'teachers.delete', 'teachers.export',
                // Attendance - full access
                'attendance.view', 'attendance.mark', 'attendance.edit', 'attendance.report', 'attendance.export',
                // Branches - full access (ADDED)
                'branches.view', 'branches.create', 'branches.edit', 'branches.delete', 'branches.stats',
                // Fees - collection only (not full financial access)
                'fees.view', 'fees.collect', 'fees.report',
                // Exams - full access
                'exams.view', 'exams.create', 'exams.edit', 'exams.results',
                // Grades, Sections, Subjects
                'grades.view', 'grades.create', 'grades.edit', 'grades.delete',
                'sections.view', 'sections.create', 'sections.edit', 'sections.delete',
                'subjects.view', 'subjects.create', 'subjects.edit', 'subjects.delete',
                // Departments
                'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
                // Groups
                'groups.view', 'groups.create', 'groups.edit', 'groups.delete',
                // Holidays
                'holidays.view', 'holidays.create', 'holidays.edit', 'holidays.delete',
                // Reports
                'reports.view', 'reports.generate', 'reports.export',
                // Settings - view only
                'settings.view',
                // Users - view only (cannot manage roles)
                'users.view',
                // NOTE: BranchAdmin does NOT have access to:
                // - accounts.* (financial management - Accountant only)
                // - transactions.* (financial transactions - Accountant only)
                // - invoices.* (invoice management - Accountant only)
            ])->pluck('id')->toArray()
        );

        // Teacher - Academic access
        $teacher = Role::where('slug', 'teacher')->first();
        $teacher->syncPermissions(
            Permission::whereIn('slug', [
                'dashboard.view',
                'students.view',
                'attendance.view', 'attendance.mark',
                'exams.view', 'exams.results',
                'grades.view',
                'sections.view',
                'subjects.view',
                'holidays.view',
                'groups.view',
            ])->pluck('id')->toArray()
        );

        // Staff - Administrative access
        $staff = Role::where('slug', 'staff')->first();
        $staff->syncPermissions(
            Permission::whereIn('slug', [
                'dashboard.view',
                'students.view', 'students.create', 'students.edit',
                'teachers.view',
                'attendance.view', 'attendance.mark',
                'fees.view', 'fees.collect',
                'holidays.view',
                'groups.view',
            ])->pluck('id')->toArray()
        );

        // Accountant - Financial access
        $accountant = Role::where('slug', 'accountant')->first();
        $accountant->syncPermissions(
            Permission::whereIn('slug', [
                'dashboard.view',
                'students.view', // Need to see students for fee management
                'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.approve',
                'transactions.view', 'transactions.create', 'transactions.approve', 'transactions.reject',
                'fees.view', 'fees.create', 'fees.collect', 'fees.report',
                'invoices.view', 'invoices.create', 'invoices.send', 'invoices.payment',
                'reports.view', 'reports.generate', 'reports.export',
            ])->pluck('id')->toArray()
        );

        // Student - Limited view access
        $student = Role::where('slug', 'student')->first();
        $student->syncPermissions(
            Permission::whereIn('slug', [
                'dashboard.view',
                'attendance.view',
                'exams.view',
                'fees.view',
                'holidays.view',
            ])->pluck('id')->toArray()
        );

        // Parent - View children's information
        $parent = Role::where('slug', 'parent')->first();
        $parent->syncPermissions(
            Permission::whereIn('slug', [
                'dashboard.view',
                'students.view', // Own children only
                'attendance.view', // Children's attendance
                'exams.view', // Children's results
                'fees.view', // Children's fees
                'holidays.view',
            ])->pluck('id')->toArray()
        );
    }
}

