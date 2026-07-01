<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Resolves the current tenant scope (accessible branches + school) for the
 * authenticated request. This is the SINGLE SOURCE OF TRUTH for tenant
 * scoping — the base Controller helpers, the `scopedToTenant()` query macro,
 * and the BelongsToTenant global scope all delegate here so the rules can
 * never drift between query sites.
 *
 * Resolution mirrors the original Controller::getAccessibleBranchIds() logic:
 *   - SuperAdmin (no company/branch) => 'all'
 *   - SuperAdmin with company        => that company's branches
 *   - BranchAdmin                    => own branch + descendants (CTE)
 *   - cross-branch permission        => school/company branches (or 'all')
 *   - everyone else                  => own branch only
 *
 * Memoized per request. In console/seeder context (no authenticated user)
 * accessibleBranchIds() returns 'all' so background jobs and seeders are
 * never tenant-filtered.
 */
class TenantContext
{
    /** @var array<int>|string|null */
    protected array|string|null $resolvedBranches = null;

    protected ?int $resolvedSchoolId = null;

    protected bool $schoolResolved = false;

    /**
     * The user id the cached values were computed for. Memoization is keyed by
     * this so a resolution that happens before auth is set (e.g. in tests, or
     * any pre-auth code path) is never served back to the authenticated user.
     * Uses a sentinel (false) for "not yet computed".
     */
    protected int|null|false $cachedForUserId = false;

    public function __construct(
        protected Request $request
    ) {
    }

    /**
     * The authenticated user. Prefers the request's resolver (set by auth
     * middleware in HTTP) and falls back to the auth guard (set by actingAs()
     * in tests / direct model access outside the HTTP middleware stack).
     */
    protected function currentUser()
    {
        return $this->request->user() ?? auth()->user();
    }

    /**
     * Invalidate memoized values if the authenticated user changed since they
     * were computed (covers pre-auth resolution and user switches in a request).
     */
    protected function syncCache($user): void
    {
        $userId = $user?->id;
        if ($this->cachedForUserId !== $userId) {
            $this->cachedForUserId = $userId;
            $this->resolvedBranches = null;
            $this->resolvedSchoolId = null;
            $this->schoolResolved = false;
        }
    }

    /**
     * Branches the current user may access.
     *
     * @return array<int>|string 'all' for unrestricted, otherwise branch IDs (possibly empty).
     */
    public function accessibleBranchIds(): array|string
    {
        $user = $this->currentUser();
        $this->syncCache($user);

        if ($this->resolvedBranches !== null) {
            return $this->resolvedBranches;
        }

        // No authenticated user (console, seeders, queued jobs): do not filter.
        if (!$user) {
            return $this->resolvedBranches = 'all';
        }

        // SuperAdmin: limited to company/branch when present (Company Portal), else all.
        if ($user->role === 'SuperAdmin') {
            if (!empty($user->company_id)) {
                return $this->resolvedBranches = $this->getBranchIdsForCompany((int) $user->company_id);
            }
            if (!empty($user->branch_id)) {
                return $this->resolvedBranches = [(int) $user->branch_id];
            }
            return $this->resolvedBranches = 'all';
        }

        $schoolId = $this->currentSchoolId();

        // BranchAdmin: own branch + descendants within the same school (recursive CTE).
        if ($user->role === 'BranchAdmin') {
            if (!$user->branch_id) {
                return $this->resolvedBranches = [];
            }

            $params = [$user->branch_id];
            $sql = "
                WITH RECURSIVE branch_tree AS (
                    SELECT id, parent_branch_id, school_id
                    FROM branches
                    WHERE parent_branch_id = ?
                    AND deleted_at IS NULL";

            if ($schoolId) {
                $sql .= " AND school_id = ?";
                $params[] = $schoolId;
            }

            $sql .= "
                    UNION ALL
                    SELECT b.id, b.parent_branch_id, b.school_id
                    FROM branches b
                    INNER JOIN branch_tree bt ON b.parent_branch_id = bt.id
                    WHERE b.deleted_at IS NULL";

            if ($schoolId) {
                $sql .= " AND b.school_id = ?";
                $params[] = $schoolId;
            }

            $sql .= "
                )
                SELECT id FROM branch_tree
            ";

            $descendants = DB::select($sql, $params);
            $ids = collect($descendants)->pluck('id')->map(fn ($id) => (int) $id)->toArray();
            array_unshift($ids, (int) $user->branch_id);

            return $this->resolvedBranches = $ids;
        }

        // Cross-branch permission holders.
        $hasCrossBranch = DB::table('user_roles')
            ->join('role_permissions', 'user_roles.role_id', '=', 'role_permissions.role_id')
            ->join('permissions', 'role_permissions.permission_id', '=', 'permissions.id')
            ->where('user_roles.user_id', $user->id)
            ->whereIn('permissions.slug', [
                'system.cross_branch_access',
                'system.manage_all_branches',
                'system.view_all_branches',
            ])
            ->exists();

        if ($hasCrossBranch) {
            if ($schoolId) {
                $branchIds = DB::table('branches')
                    ->where('school_id', $schoolId)
                    ->where('is_active', true)
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id)
                    ->toArray();
                return $this->resolvedBranches = $branchIds;
            }
            if (!empty($user->company_id)) {
                return $this->resolvedBranches = $this->getBranchIdsForCompany((int) $user->company_id);
            }
            return $this->resolvedBranches = 'all';
        }

        // Everyone else: only their assigned branch.
        return $this->resolvedBranches = $user->branch_id ? [(int) $user->branch_id] : [];
    }

    /**
     * Current school id derived from the authenticated user.
     */
    public function currentSchoolId(): ?int
    {
        $user = $this->currentUser();
        $this->syncCache($user);

        if ($this->schoolResolved) {
            return $this->resolvedSchoolId;
        }
        $this->schoolResolved = true;

        if (!$user) {
            return $this->resolvedSchoolId = null;
        }

        if ($user->branch_id) {
            $schoolId = DB::table('branches')
                ->where('id', $user->branch_id)
                ->whereNull('deleted_at')
                ->value('school_id');
            return $this->resolvedSchoolId = $schoolId ? (int) $schoolId : null;
        }

        if ($user->role === 'SuperAdmin' && !empty($user->company_id)) {
            $schoolId = DB::table('schools')
                ->where('company_id', $user->company_id)
                ->whereNull('deleted_at')
                ->orderBy('id', 'asc')
                ->value('id');
            return $this->resolvedSchoolId = $schoolId ? (int) $schoolId : null;
        }

        return $this->resolvedSchoolId = null;
    }

    /**
     * True when the user has unrestricted (all-branch) access.
     */
    public function hasAllBranchAccess(): bool
    {
        return $this->accessibleBranchIds() === 'all';
    }

    /**
     * Branch IDs belonging to a company (via its schools).
     *
     * @return array<int>
     */
    protected function getBranchIdsForCompany(int $companyId): array
    {
        return DB::table('branches')
            ->join('schools', 'branches.school_id', '=', 'schools.id')
            ->where('schools.company_id', $companyId)
            ->whereNull('schools.deleted_at')
            ->whereNull('branches.deleted_at')
            ->where('branches.is_active', true)
            ->pluck('branches.id')
            ->map(fn ($id) => (int) $id)
            ->toArray();
    }
}
