<?php

namespace App\Models\Concerns;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder;

/**
 * Adds an automatic tenant (branch) scope to an Eloquent model so that
 * find()/get()/show/update/destroy can never silently leak across branches —
 * the single-record leak class that has bitten show/update/destroy paths.
 *
 * Defense-in-depth: list endpoints already scope explicitly via the
 * scopedToTenant() macro; this guarantees the same boundary on the
 * model-centric paths that macro calls don't cover.
 *
 * Resolution delegates to TenantContext (the single source of truth).
 * Returns 'all' for SuperAdmin and for non-request (console/seeder) context,
 * so seeders and background jobs are never filtered.
 *
 * Escape hatch: Model::withoutTenantScope() for the rare cross-tenant query
 * (e.g. company-portal aggregates) — use sparingly and deliberately.
 *
 * The model must expose its branch column via tenantBranchColumn()
 * (defaults to 'branch_id').
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            /** @var TenantContext $tenant */
            $tenant = app(TenantContext::class);
            $branches = $tenant->accessibleBranchIds();

            if ($branches === 'all') {
                return;
            }

            $model = $builder->getModel();
            $column = $model->getTable() . '.' . $model->tenantBranchColumn();

            if (empty($branches)) {
                $builder->whereRaw('1 = 0');
                return;
            }

            $builder->whereIn($column, $branches);
        });
    }

    /**
     * Query without the tenant scope. Use deliberately for cross-tenant reads.
     */
    public static function withoutTenantScope(): Builder
    {
        return static::withoutGlobalScope('tenant')->newQuery();
    }

    /**
     * Branch column used for tenant scoping. Override per-model if it differs.
     */
    public function tenantBranchColumn(): string
    {
        return 'branch_id';
    }
}
