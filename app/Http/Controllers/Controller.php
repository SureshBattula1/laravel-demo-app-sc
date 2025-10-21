<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Get accessible branch IDs for current user
     * Supports: SuperAdmin, Cross-Branch Permission, BranchAdmin, Regular Users
     * 
     * @param Request $request
     * @return array|string Returns 'all' for unrestricted access, or array of branch IDs
     */
    protected function getAccessibleBranchIds(Request $request): array|string
    {
        $user = $request->user();
        
        if (!$user) {
            return [];
        }
        
        // SuperAdmin has access to all branches
        if ($user->role === 'SuperAdmin') {
            return 'all';
        }
        
        // Check for cross-branch access permission
        if ($user->hasCrossBranchAccess()) {
            return 'all';
        }
        
        // BranchAdmin can access their branch + descendants
        if ($user->role === 'BranchAdmin') {
            $userBranch = \App\Models\Branch::find($user->branch_id);
            if ($userBranch) {
                // ✅ OPTIMIZED: getDescendantIds now uses recursive CTE (1 query instead of N)
                // It includes self by default, so no need to merge
                return $userBranch->getDescendantIds(true);
            }
            return [$user->branch_id];
        }
        
        // All other roles: only their assigned branch
        return $user->branch_id ? [$user->branch_id] : [];
    }
    
    /**
     * Apply branch filter to query
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param Request $request
     * @param string $branchColumn Default column name is 'branch_id'
     * @return \Illuminate\Database\Eloquent\Builder
     */
    protected function applyBranchFilter($query, Request $request, $branchColumn = 'branch_id')
    {
        $accessibleBranches = $this->getAccessibleBranchIds($request);
        
        // SuperAdmin or users with cross-branch permission: no filter needed
        if ($accessibleBranches === 'all') {
            return $query;
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
}
