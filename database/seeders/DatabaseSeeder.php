<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Role;
use App\Models\Module;
use App\Models\Permission;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     * This seeder creates:
     * - 6 Roles (Super Admin, Branch Admin, Teacher, Staff, Accountant, Student)
     * - 24 Modules (for sidebar menus)
     * - Permissions for each module
     * - 2 Super Admin users with ALL permissions
     * - NO other users (teachers/students will be created by application)
     */
    public function run(): void
    {
        DB::beginTransaction();
        
        try {
            $this->command->info('🌱 Seeding database...');
            
            // Step 1: Create Roles
            $this->createRoles();
            
            // Step 2: Create Modules and Permissions
            $this->createModulesAndPermissions();
            
            // Step 3: Assign Permissions to Roles
            $this->assignRolePermissions();
            
            // Step 4: Create Super Admin Users
            $this->createSuperAdmins();
            
            DB::commit();
            
            $this->command->info('✅ Database seeding completed successfully!');
            $this->command->info('');
            $this->command->info('📋 Summary:');
            $this->command->info('   - 6 Roles created');
            $this->command->info('   - 24 Modules created');
            $this->command->info('   - ' . Permission::count() . ' Permissions created');
            $this->command->info('   - Permissions assigned to all roles');
            $this->command->info('   - 2 Super Admin users created');
            $this->command->info('');
            $this->command->info('🔑 Super Admin Credentials:');
            $this->command->info('   Email: superadmin@school.com | Password: Admin@123');
            $this->command->info('   Email: admin@school.com | Password: Admin@123');
            $this->command->info('');
            $this->command->info('💡 Note: Teacher and Student roles are automatically assigned');
            $this->command->info('   when teachers/students are created via the application.');
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error seeding database: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Create system roles
     */
    private function createRoles(): void
    {
        $this->command->info('📝 Creating roles...');
        
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'Full system access with all permissions',
                'level' => 1,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Branch Admin',
                'slug' => 'branch-admin',
                'description' => 'Branch-level administration access',
                'level' => 2,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Teacher',
                'slug' => 'teacher',
                'description' => 'Teaching staff with student and academic access',
                'level' => 3,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Staff',
                'slug' => 'staff',
                'description' => 'Administrative staff access',
                'level' => 4,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Accountant',
                'slug' => 'accountant',
                'description' => 'Accounting and finance management',
                'level' => 4,
                'is_system_role' => true,
                'is_active' => true
            ],
            [
                'name' => 'Student',
                'slug' => 'student',
                'description' => 'Student access to view own information',
                'level' => 5,
                'is_system_role' => true,
                'is_active' => true
            ],
        ];
        
        foreach ($roles as $roleData) {
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );
            if ($role->wasRecentlyCreated) {
                $this->command->info("   ✓ Created role: {$roleData['name']}");
            } else {
                $this->command->info("   ⊙ Role already exists: {$roleData['name']}");
            }
        }
    }
    
    /**
     * Create 2 Super Admin users
     */
    private function createSuperAdmins(): void
    {
        $this->command->info('👤 Creating Super Admin users...');
        
        $superAdminRole = Role::where('slug', 'super-admin')->first();
        
        if (!$superAdminRole) {
            $this->command->error('   ❌ Super Admin role not found!');
            return;
        }
        
        $superAdmins = [
            [
                'first_name' => 'Super',
                'last_name' => 'Admin',
                'email' => 'superadmin@school.com',
                'phone' => '1234567890',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'user_type' => 'Admin',
                'is_active' => true,
            ],
            [
                'first_name' => 'System',
                'last_name' => 'Administrator',
                'email' => 'admin@school.com',
                'phone' => '0987654321',
                'password' => Hash::make('Admin@123'),
                'role' => 'SuperAdmin',
                'user_type' => 'Admin',
                'is_active' => true,
            ],
        ];
        
        foreach ($superAdmins as $adminData) {
            // Create or get existing user
            $user = User::firstOrCreate(
                ['email' => $adminData['email']],
                $adminData
            );
            
            // Check if user already has the Super Admin role
            $hasRole = $user->roles()->where('roles.id', $superAdminRole->id)->exists();
            
            if (!$hasRole) {
                // Assign Super Admin role
                $user->roles()->attach($superAdminRole->id, [
                    'is_primary' => true,
                    'branch_id' => null, // Super Admin has access to all branches
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }
            
            if ($user->wasRecentlyCreated) {
                $this->command->info("   ✓ Created Super Admin: {$adminData['email']}");
            } else {
                $this->command->info("   ⊙ Super Admin already exists: {$adminData['email']}");
            }
        }
    }
    
    /**
     * Create modules with their permissions (for sidebar menus)
     */
    private function createModulesAndPermissions(): void
    {
        $this->command->info('📱 Creating modules and permissions...');
        
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
                'name' => 'Student Attendance',
                'slug' => 'student_attendance',
                'icon' => 'fact_check',
                'route' => '/attendance/student',
                'order' => 4,
                'permissions' => ['view', 'create', 'mark', 'edit', 'delete', 'report', 'export']
            ],
            [
                'name' => 'Teacher Attendance',
                'slug' => 'teacher_attendance',
                'icon' => 'assignment_turned_in',
                'route' => '/attendance/teacher',
                'order' => 5,
                'permissions' => ['view', 'create', 'mark', 'edit', 'delete', 'report', 'export']
            ],
            [
                'name' => 'Branches',
                'slug' => 'branches',
                'icon' => 'business',
                'route' => '/branches',
                'order' => 6,
                'permissions' => ['view', 'create', 'edit', 'delete', 'stats']
            ],
            [
                'name' => 'Accounts',
                'slug' => 'accounts',
                'icon' => 'account_balance',
                'route' => '/accounts',
                'order' => 7,
                'permissions' => ['view', 'create', 'edit', 'delete', 'approve', 'export']
            ],
            [
                'name' => 'Transactions',
                'slug' => 'transactions',
                'icon' => 'receipt',
                'route' => '/accounts/transactions',
                'order' => 8,
                'permissions' => ['view', 'create', 'edit', 'delete', 'approve', 'reject']
            ],
            [
                'name' => 'Fees',
                'slug' => 'fees',
                'icon' => 'payment',
                'route' => '/fees',
                'order' => 9,
                'permissions' => ['view', 'create', 'edit', 'delete', 'collect', 'report']
            ],
            [
                'name' => 'Exams',
                'slug' => 'exams',
                'icon' => 'assignment',
                'route' => '/exams',
                'order' => 10,
                'permissions' => ['view', 'create', 'edit', 'delete', 'results']
            ],
            [
                'name' => 'Grades',
                'slug' => 'grades',
                'icon' => 'grade',
                'route' => '/grades',
                'order' => 11,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Sections',
                'slug' => 'sections',
                'icon' => 'class',
                'route' => '/sections',
                'order' => 12,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Subjects',
                'slug' => 'subjects',
                'icon' => 'book',
                'route' => '/subjects',
                'order' => 13,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Departments',
                'slug' => 'departments',
                'icon' => 'domain',
                'route' => '/departments',
                'order' => 14,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Holidays',
                'slug' => 'holidays',
                'icon' => 'event',
                'route' => '/holidays',
                'order' => 15,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Leaves',
                'slug' => 'leaves',
                'icon' => 'event_busy',
                'route' => '/leaves',
                'order' => 16,
                'permissions' => ['view', 'create', 'edit', 'delete', 'approve', 'reject']
            ],
            [
                'name' => 'Import',
                'slug' => 'import',
                'icon' => 'upload_file',
                'route' => '/imports',
                'order' => 17,
                'permissions' => ['view', 'upload', 'validate', 'commit', 'cancel', 'template']
            ],
            [
                'name' => 'Invoices',
                'slug' => 'invoices',
                'icon' => 'description',
                'route' => '/invoices',
                'order' => 18,
                'permissions' => ['view', 'create', 'edit', 'delete', 'send', 'payment']
            ],
            [
                'name' => 'Groups',
                'slug' => 'groups',
                'icon' => 'group',
                'route' => '/groups',
                'order' => 19,
                'permissions' => ['view', 'create', 'edit', 'delete']
            ],
            [
                'name' => 'Reports',
                'slug' => 'reports',
                'icon' => 'assessment',
                'route' => '/reports',
                'order' => 20,
                'permissions' => ['view', 'generate', 'export']
            ],
            [
                'name' => 'Roles',
                'slug' => 'roles',
                'icon' => 'admin_panel_settings',
                'route' => '/settings/roles',
                'order' => 21,
                'permissions' => ['view', 'create', 'edit', 'delete', 'update']
            ],
            [
                'name' => 'Permissions',
                'slug' => 'permissions',
                'icon' => 'shield',
                'route' => '/settings/permissions',
                'order' => 22,
                'permissions' => ['view', 'create', 'edit', 'delete', 'update']
            ],
            [
                'name' => 'Users Management',
                'slug' => 'users',
                'icon' => 'people',
                'route' => '/settings/users',
                'order' => 23,
                'permissions' => ['view', 'create', 'edit', 'delete', 'update']
            ],
            [
                'name' => 'Settings',
                'slug' => 'settings',
                'icon' => 'settings',
                'route' => '/settings',
                'order' => 24,
                'permissions' => ['view', 'edit']
            ]
        ];

        foreach ($modulesData as $moduleData) {
            $permissions = $moduleData['permissions'];
            unset($moduleData['permissions']);

            $module = Module::firstOrCreate(
                ['slug' => $moduleData['slug']],
                $moduleData
            );

            $createdCount = 0;
            foreach ($permissions as $action) {
                $permission = Permission::firstOrCreate(
                    [
                        'module_id' => $module->id,
                        'action' => $action
                    ],
                    [
                        'module_id' => $module->id,
                        'name' => ucfirst($action) . ' ' . $module->name,
                        'slug' => $module->slug . '.' . $action,
                        'action' => $action,
                        'is_system_permission' => true
                    ]
                );
                if ($permission->wasRecentlyCreated) {
                    $createdCount++;
                }
            }
            
            if ($module->wasRecentlyCreated) {
                $this->command->info("   ✓ Created module: {$module->name} with " . count($permissions) . " permissions");
            } else {
                $this->command->info("   ⊙ Module exists: {$module->name} (created {$createdCount} new permissions)");
            }
        }
    }
    
    /**
     * Assign permissions to roles
     */
    private function assignRolePermissions(): void
    {
        $this->command->info('🔒 Assigning permissions to roles...');
        
        // Super Admin - ALL permissions
        $superAdmin = Role::where('slug', 'super-admin')->first();
        $allPermissions = Permission::all()->pluck('id')->toArray();
        $superAdmin->permissions()->sync($allPermissions);
        $this->command->info("   ✓ Super Admin: " . count($allPermissions) . " permissions");

        // Branch Admin - Comprehensive access (NO full financial modules)
        $branchAdmin = Role::where('slug', 'branch-admin')->first();
        $branchAdminPerms = Permission::whereIn('slug', [
            'dashboard.view',
            'students.view', 'students.create', 'students.edit', 'students.delete', 
            'students.export', 'students.promote', 'students.transfer',
            'teachers.view', 'teachers.create', 'teachers.edit', 'teachers.delete', 'teachers.export',
            'student_attendance.view', 'student_attendance.create', 'student_attendance.mark', 
            'student_attendance.edit', 'student_attendance.delete', 'student_attendance.report', 'student_attendance.export',
            'teacher_attendance.view', 'teacher_attendance.create', 'teacher_attendance.mark', 
            'teacher_attendance.edit', 'teacher_attendance.delete', 'teacher_attendance.report', 'teacher_attendance.export',
            'branches.view', 'branches.create', 'branches.edit', 'branches.delete', 'branches.stats',
            'fees.view', 'fees.collect', 'fees.report',
            'exams.view', 'exams.create', 'exams.edit', 'exams.results',
            'grades.view', 'grades.create', 'grades.edit', 'grades.delete',
            'sections.view', 'sections.create', 'sections.edit', 'sections.delete',
            'subjects.view', 'subjects.create', 'subjects.edit', 'subjects.delete',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'groups.view', 'groups.create', 'groups.edit', 'groups.delete',
            'holidays.view', 'holidays.create', 'holidays.edit', 'holidays.delete',
            'leaves.view', 'leaves.create', 'leaves.edit', 'leaves.delete', 'leaves.approve', 'leaves.reject',
            'import.view', 'import.upload', 'import.validate', 'import.commit', 'import.cancel', 'import.template',
            'reports.view', 'reports.generate', 'reports.export',
            'settings.view',
            'users.view',
        ])->pluck('id')->toArray();
        $branchAdmin->permissions()->sync($branchAdminPerms);
        $this->command->info("   ✓ Branch Admin: " . count($branchAdminPerms) . " permissions");

        // Teacher - Academic access
        $teacher = Role::where('slug', 'teacher')->first();
        $teacherPerms = Permission::whereIn('slug', [
            'dashboard.view',
            'students.view',
            'student_attendance.view', 'student_attendance.create', 'student_attendance.mark',
            'exams.view', 'exams.results',
            'grades.view',
            'sections.view',
            'subjects.view',
            'holidays.view',
            'groups.view',
            'leaves.view', 'leaves.create', // Teachers can view and create their own leaves
            'import.view', 'import.template', // Teachers can view imports and download templates
        ])->pluck('id')->toArray();
        $teacher->permissions()->sync($teacherPerms);
        $this->command->info("   ✓ Teacher: " . count($teacherPerms) . " permissions");

        // Staff - Administrative access
        $staff = Role::where('slug', 'staff')->first();
        $staffPerms = Permission::whereIn('slug', [
            'dashboard.view',
            'students.view', 'students.create', 'students.edit',
            'teachers.view',
            'student_attendance.view', 'student_attendance.create', 'student_attendance.mark',
            'teacher_attendance.view', 'teacher_attendance.create', 'teacher_attendance.mark',
            'fees.view', 'fees.collect',
            'holidays.view',
            'groups.view',
            'leaves.view', 'leaves.create', 'leaves.edit', 'leaves.approve', 'leaves.reject', // Staff can manage leaves
            'import.view', 'import.upload', 'import.validate', 'import.commit', 'import.cancel', 'import.template', // Staff can manage imports
        ])->pluck('id')->toArray();
        $staff->permissions()->sync($staffPerms);
        $this->command->info("   ✓ Staff: " . count($staffPerms) . " permissions");

        // Accountant - Financial access
        $accountant = Role::where('slug', 'accountant')->first();
        $accountantPerms = Permission::whereIn('slug', [
            'dashboard.view',
            'students.view',
            'accounts.view', 'accounts.create', 'accounts.edit', 'accounts.approve',
            'transactions.view', 'transactions.create', 'transactions.approve', 'transactions.reject',
            'fees.view', 'fees.create', 'fees.collect', 'fees.report',
            'invoices.view', 'invoices.create', 'invoices.send', 'invoices.payment',
            'reports.view', 'reports.generate', 'reports.export',
        ])->pluck('id')->toArray();
        $accountant->permissions()->sync($accountantPerms);
        $this->command->info("   ✓ Accountant: " . count($accountantPerms) . " permissions");

        // Student - Limited view access
        $student = Role::where('slug', 'student')->first();
        $studentPerms = Permission::whereIn('slug', [
            'dashboard.view',
            'student_attendance.view',
            'exams.view',
            'fees.view',
            'holidays.view',
            'leaves.view', 'leaves.create', // Students can view and create their own leaves
        ])->pluck('id')->toArray();
        $student->permissions()->sync($studentPerms);
        $this->command->info("   ✓ Student: " . count($studentPerms) . " permissions");
    }
}
