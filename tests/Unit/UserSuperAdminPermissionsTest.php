<?php

namespace Tests\Unit;

use App\Models\User;
use PHPUnit\Framework\TestCase;

class UserSuperAdminPermissionsTest extends TestCase
{
    public function test_super_admin_bypasses_branch_scoped_permission_checks(): void
    {
        $user = new User([
            'role' => 'SuperAdmin',
        ]);
        $user->id = 1;

        $this->assertTrue($user->isSuperAdmin());
        $this->assertTrue($user->hasPermission('notifications.view', 2));
        $this->assertTrue($user->hasPermission('notifications.create', 99));
        $this->assertTrue($user->hasAnyPermission(['notifications.view'], 2));
        $this->assertTrue($user->hasAllPermissions(['notifications.view', 'notifications.create'], 2));
    }
}
