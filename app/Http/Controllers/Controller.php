<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Get accessible branch IDs for current user
     * Supports: SuperAdmin, Cross-Branch Permission, BranchAdmin, Regular Users
     * ✅ OPTIMIZED: Reduced database queries and optimized permission checks
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
        
        // SuperAdmin has access to all branches (fastest check - no DB query)
        if ($user->role === 'SuperAdmin') {
            return 'all';
        }
        
        // ✅ OPTIMIZED: Check role first before expensive permission checks
        // BranchAdmin can access their branch + descendants
        if ($user->role === 'BranchAdmin') {
            if (!$user->branch_id) {
                return [];
            }
            
            // ✅ OPTIMIZED: Use raw query to get descendant IDs without loading model
            // This avoids loading the Branch model and directly executes the CTE
            $descendants = \Illuminate\Support\Facades\DB::select("
                WITH RECURSIVE branch_tree AS (
                    SELECT id, parent_branch_id
                    FROM branches
                    WHERE parent_branch_id = ?
                    AND deleted_at IS NULL
                    
                    UNION ALL
                    
                    SELECT b.id, b.parent_branch_id
                    FROM branches b
                    INNER JOIN branch_tree bt ON b.parent_branch_id = bt.id
                    WHERE b.deleted_at IS NULL
                )
                SELECT id FROM branch_tree
            ", [$user->branch_id]);
            
            $ids = collect($descendants)->pluck('id')->toArray();
            array_unshift($ids, $user->branch_id); // Include self
            
            return $ids;
        }
        
        // ✅ OPTIMIZED: Check for cross-branch access permission with single optimized query
        // Check permissions directly without method call overhead
        $hasCrossBranch = \Illuminate\Support\Facades\DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $user->id)
            ->whereIn('permissions.slug', [
                'system.cross_branch_access',
                'system.manage_all_branches',
                'system.view_all_branches'
            ])
            ->exists();
        
        if ($hasCrossBranch) {
            return 'all';
        }
        
        // All other roles: only their assigned branch (fastest - no DB query)
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
