<?php

namespace App\Services;

use App\Models\User;

class SchoolAccountAccessService
{
    public const BLOCKED_MESSAGE = 'Your account is deactive connect the admin';

    public function isBlocked(User $user): bool
    {
        if (in_array($user->user_type, ['CompanyAdmin', 'SupportStaff'], true)) {
            return false;
        }

        if (! $user->is_active) {
            return true;
        }

        if (! $user->branch_id) {
            return $user->user_type === 'SchoolUser' || $user->user_type === null;
        }

        $user->loadMissing(['branch.school']);
        $branch = $user->branch;
        if (! $branch) {
            return true;
        }

        if (! $branch->is_active || in_array($branch->status, ['Inactive', 'Closed'], true)) {
            return true;
        }

        $school = $branch->school;
        if ($school && in_array($school->status, ['Inactive', 'Suspended'], true)) {
            return true;
        }

        return false;
    }

    public function deniedResponse()
    {
        return response()->json([
            'success' => false,
            'message' => self::BLOCKED_MESSAGE,
        ], 403);
    }
}
