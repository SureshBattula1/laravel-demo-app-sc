<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Company id for the authenticated user (Company Portal / school SuperAdmin context).
     */
    protected function getCurrentCompanyId(Request $request): ?int
    {
        return app(\App\Services\TenantContext::class)->currentCompanyId();
    }

    /**
     * Scope a users query to the actor's current school (or accessible branches).
     * Never expands to other schools in the same company.
     * Platform SuperAdmin (unrestricted branches) is unfiltered.
     */
    protected function applyUserTenantFilter($query, Request $request)
    {
        $actor = $request->user();
        $branches = $this->getAccessibleBranchIds($request);

        if ($branches === 'all') {
            return $query;
        }

        $scopeBranchIds = is_array($branches) ? array_map('intval', $branches) : [];
        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $schoolBranches = $this->getBranchIdsForSchool((int) $schoolId);
            $scopeBranchIds = !empty($scopeBranchIds)
                ? array_values(array_intersect($scopeBranchIds, $schoolBranches))
                : $schoolBranches;
        }

        return $query->where(function ($q) use ($scopeBranchIds, $actor) {
            if (!empty($scopeBranchIds)) {
                $q->whereIn('branch_id', $scopeBranchIds);
            } else {
                $q->whereRaw('1 = 0');
            }
            if ($actor) {
                $q->orWhere('id', $actor->id);
            }
        });
    }

    /**
     * Map users.role enum values to roles.name rows.
     */
    protected function userRoleEnumToName(?string $enum): ?string
    {
        if (!$enum) {
            return null;
        }

        return match ($enum) {
            'SuperAdmin' => 'Super Admin',
            'BranchAdmin' => 'Branch Admin',
            'Teacher' => 'Teacher',
            'Staff' => 'Staff',
            'Accountant' => 'Accountant',
            'Student' => 'Student',
            'Parent' => 'Parent',
            default => $enum,
        };
    }

    /**
     * Numeric roles.level for a user (1 = Super Admin … 5 = Student).
     */
    protected function getRoleLevelForUser($user): ?int
    {
        $name = $this->userRoleEnumToName($user->role ?? null);
        if (!$name) {
            return null;
        }

        $level = \Illuminate\Support\Facades\DB::table('roles')->where('name', $name)->value('level');

        return $level !== null ? (int) $level : null;
    }

    /**
     * users.role enum values the actor may list or assign (same or lower authority).
     */
    protected function getManageableRoleEnums(Request $request): array
    {
        $actorLevel = $this->getRoleLevelForUser($request->user());
        if ($actorLevel === null) {
            return [];
        }

        $enumToName = [
            'SuperAdmin' => 'Super Admin',
            'BranchAdmin' => 'Branch Admin',
            'Teacher' => 'Teacher',
            'Staff' => 'Staff',
            'Accountant' => 'Accountant',
            'Student' => 'Student',
            'Parent' => 'Parent',
        ];

        $levelsByName = \Illuminate\Support\Facades\DB::table('roles')->pluck('level', 'name');
        $allowed = [];

        foreach ($enumToName as $enum => $name) {
            $level = $levelsByName[$name] ?? null;
            if ($level !== null && (int) $level >= $actorLevel) {
                $allowed[] = $enum;
            }
        }

        return $allowed;
    }

    /**
     * Company-portal accounts never appear in school Settings → Users.
     */
    protected function schoolSettingsUserTypes(): array
    {
        return ['SchoolUser', 'Teacher', 'Student', 'Staff', 'Parent', 'Accountant'];
    }

    /**
     * Keep only the actor plus same-or-lower school users.
     * Platform SuperAdmin (unrestricted branches) is left unfiltered.
     */
    protected function applyManageableUserFilter($query, Request $request)
    {
        $actor = $request->user();
        if (!$actor) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->getAccessibleBranchIds($request) === 'all') {
            return $query;
        }

        $allowedRoles = $this->getManageableRoleEnums($request);
        $schoolTypes = $this->schoolSettingsUserTypes();

        return $query->where(function ($q) use ($actor, $allowedRoles, $schoolTypes) {
            $q->where(function ($inner) use ($allowedRoles, $schoolTypes) {
                if (!empty($allowedRoles)) {
                    $inner->whereIn('role', $allowedRoles);
                } else {
                    $inner->whereRaw('1 = 0');
                }
                $inner->where(function ($typeQ) use ($schoolTypes) {
                    $typeQ->whereNull('user_type')
                        ->orWhereIn('user_type', $schoolTypes);
                });
            })->orWhere('id', $actor->id);
        });
    }

    /**
     * Whether the actor may assign this roles-table row to another user.
     */
    protected function canAssignRole(Request $request, $role): bool
    {
        if (!$role) {
            return false;
        }

        if ($this->getAccessibleBranchIds($request) === 'all') {
            return true;
        }

        $actorLevel = $this->getRoleLevelForUser($request->user());
        if ($actorLevel === null || !isset($role->level)) {
            return false;
        }

        return (int) $role->level >= $actorLevel;
    }

    /**
     * Whether the actor may view/manage the target user record.
     */
    protected function canAccessManagedUser(Request $request, $targetUser): bool
    {
        $actor = $request->user();
        if (!$actor || !$targetUser) {
            return false;
        }

        if ((int) $actor->id === (int) $targetUser->id) {
            return true;
        }

        $branches = $this->getAccessibleBranchIds($request);
        if ($branches === 'all') {
            return true;
        }

        $schoolId = $this->getCurrentSchoolId($request);
        if ($schoolId) {
            $targetSchoolId = $this->getSchoolIdForBranch($targetUser->branch_id ?? null);
            if ($targetSchoolId !== (int) $schoolId) {
                return false;
            }
        } else {
            $sameBranch = !empty($targetUser->branch_id) && is_array($branches)
                && in_array((int) $targetUser->branch_id, array_map('intval', $branches), true);
            if (!$sameBranch) {
                return false;
            }
        }

        $targetType = $targetUser->user_type ?? null;
        if (in_array($targetType, ['CompanyAdmin', 'SupportStaff', 'Admin'], true)) {
            return false;
        }

        $actorLevel = $this->getRoleLevelForUser($actor);
        $targetLevel = $this->getRoleLevelForUser($targetUser);
        if ($actorLevel === null || $targetLevel === null) {
            return false;
        }

        return $targetLevel >= $actorLevel;
    }

    /**
     * Scope academic years to the actor's company. Platform admins (no company) see all.
     */
    protected function applyAcademicYearTenantFilter($query, Request $request)
    {
        $companyId = $this->getCurrentCompanyId($request);
        if ($companyId) {
            $query->where('company_id', $companyId);
        }

        return $query;
    }

    /**
     * Get current school ID from request
     * 
     * @param Request $request
     * @return int|null
     */
    protected function getCurrentSchoolId(Request $request): ?int
    {
        // Delegates to TenantContext — the single source of truth for tenant scoping.
        return app(\App\Services\TenantContext::class)->currentSchoolId();
    }

    /**
     * Get branch IDs that belong to the given company (via schools).
     * Used so dashboard "all" shows only that company's branches.
     *
     * @param int $companyId
     * @return array
     */
    protected function getBranchIdsForCompany(int $companyId): array
    {
        return \Illuminate\Support\Facades\DB::table('branches')
            ->join('schools', 'branches.school_id', '=', 'schools.id')
            ->where('schools.company_id', $companyId)
            ->whereNull('schools.deleted_at')
            ->whereNull('branches.deleted_at')
            ->where('branches.is_active', true)
            ->pluck('branches.id')
            ->toArray();
    }

    /**
     * Branch IDs belonging to one school.
     *
     * @return array<int>
     */
    protected function getBranchIdsForSchool(int $schoolId): array
    {
        return \Illuminate\Support\Facades\DB::table('branches')
            ->where('school_id', $schoolId)
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }

    /**
     * School id for a branch, or null if the branch is missing.
     */
    protected function getSchoolIdForBranch($branchId): ?int
    {
        if (empty($branchId)) {
            return null;
        }

        $schoolId = \Illuminate\Support\Facades\DB::table('branches')
            ->where('id', $branchId)
            ->whereNull('deleted_at')
            ->value('school_id');

        return $schoolId ? (int) $schoolId : null;
    }

    /**
     * Get accessible branch IDs for current user
     * Supports: SuperAdmin, Cross-Branch Permission, BranchAdmin, Regular Users
     * Now includes school-level filtering
     * ✅ OPTIMIZED: Reduced database queries and optimized permission checks
     * 
     * @param Request $request
     * @return array|string Returns 'all' for unrestricted access, or array of branch IDs
     */
    protected function getAccessibleBranchIds(Request $request): array|string
    {
        // Delegates to TenantContext — the single source of truth for tenant scoping.
        // (Equivalent to the previous inline logic; in an authenticated controller
        // route $request->user() is always present.)
        return app(\App\Services\TenantContext::class)->accessibleBranchIds();
    }
    
    /**
     * Apply school filter to query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param string $schoolColumn Default column name is 'school_id'
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applySchoolFilter($query, Request $request, $schoolColumn = 'school_id')
    {
        $schoolId = $this->getCurrentSchoolId($request);
        
        if ($schoolId) {
            $query->where($schoolColumn, $schoolId);
        }
        
        return $query;
    }

    /**
     * Apply branch filter to query (now includes school context)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param string $branchColumn Default column name is 'branch_id'
     * @param string|null $schoolColumn Optional school column for applySchoolFilter when branches='all'
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyBranchFilter($query, Request $request, $branchColumn = 'branch_id', $schoolColumn = null)
    {
        $accessibleBranches = $this->getAccessibleBranchIds($request);
        
        // SuperAdmin or users with cross-branch permission: no filter needed
        if ($accessibleBranches === 'all') {
            // But still apply school filter if in school context
            return $this->applySchoolFilter($query, $request, $schoolColumn ?? 'school_id');
        }
        
        // Apply branch filter
        if (!empty($accessibleBranches)) {
            $query->whereIn($branchColumn, $accessibleBranches);
        } else {
            // No accessible branches - return empty result
            $query->whereRaw('1 = 0');
        }
        
        return $query;
    }
    
    /**
     * Check if user can access specific branch
     * 
     * @param Request $request
     * @param int $branchId
     * @return bool
     */
    protected function canAccessBranch(Request $request, int $branchId): bool
    {
        $accessibleBranches = $this->getAccessibleBranchIds($request);
        
        if ($accessibleBranches === 'all') {
            return true;
        }
        
        return in_array($branchId, $accessibleBranches);
    }
    
    /**
     * Check if user can manage (edit/delete) in specific branch
     * 
     * @param Request $request
     * @param int $branchId
     * @return bool
     */
    protected function canManageBranch(Request $request, int $branchId): bool
    {
        $user = $request->user();
        
        if (!$user) {
            return false;
        }
        
        // Check manage permission
        if ($user->canManageAllBranches()) {
            return true;
        }
        
        // Otherwise, check normal access
        return $this->canAccessBranch($request, $branchId);
    }
    
    /**
     * Get user's default branch for new records
     * 
     * @param Request $request
     * @return int|null
     */
    protected function getDefaultBranchId(Request $request): ?int
    {
        $user = $request->user();
        
        // Users with cross-branch access don't have a default branch
        if ($user && $user->hasCrossBranchAccess()) {
            return null;
        }
        
        // Non-admins should use their branch
        if ($user && !in_array($user->role, ['SuperAdmin', 'BranchAdmin'])) {
            return $user->branch_id;
        }
        
        return null;
    }

    /**
     * Get user's default school ID for new records
     * 
     * @param Request $request
     * @return int|null
     */
    protected function getDefaultSchoolId(Request $request): ?int
    {
        return $this->getCurrentSchoolId($request);
    }

    /**
     * Standard 403 for a branch a user may not access.
     */
    protected function forbiddenResponse(string $message = 'You do not have access to this branch')
    {
        return response()->json(['success' => false, 'message' => $message], 403);
    }

    /**
     * Standard 500 (exposes the message only in local).
     */
    protected function serverErrorResponse(string $message, \Throwable $e)
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => app()->environment('local') ? $e->getMessage() : 'Server error',
        ], 500);
    }
}
