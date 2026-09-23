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
     * Scope a users query to the actor's company and/or accessible branches.
     * Platform SuperAdmin (no company, unrestricted branches) is unfiltered.
     */
    protected function applyUserTenantFilter($query, Request $request)
    {
        $actor = $request->user();
        $branches = $this->getAccessibleBranchIds($request);

        if ($branches === 'all') {
            return $query;
        }

        $companyId = $actor?->company_id ? (int) $actor->company_id : null;

        return $query->where(function ($q) use ($branches, $companyId) {
            $hasBranchFilter = false;
            if (is_array($branches) && !empty($branches)) {
                $q->whereIn('branch_id', $branches);
                $hasBranchFilter = true;
            }
            if ($companyId) {
                if ($hasBranchFilter) {
                    $q->orWhere('company_id', $companyId);
                } else {
                    $q->where('company_id', $companyId);
                }
            } elseif (!$hasBranchFilter) {
                $q->whereRaw('1 = 0');
            }
        });
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

        $branches = $this->getAccessibleBranchIds($request);
        if ($branches === 'all') {
            return true;
        }

        if (!empty($actor->company_id) && (int) ($targetUser->company_id ?? 0) === (int) $actor->company_id) {
            return true;
        }

        if (!empty($targetUser->branch_id) && is_array($branches)) {
            return in_array((int) $targetUser->branch_id, array_map('intval', $branches), true);
        }

        return false;
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
