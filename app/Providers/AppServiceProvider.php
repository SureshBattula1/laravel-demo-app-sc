<?php

namespace App\Providers;

use App\Services\TenantContext;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // One tenant resolution per request (memoizes accessible branches/school).
        $this->app->scoped(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register Model Observers for automatic role assignment
        \App\Models\Student::observe(\App\Observers\StudentObserver::class);
        \App\Models\Teacher::observe(\App\Observers\TeacherObserver::class);

        $this->registerTenantScopeMacro();
    }

    /**
     * `scopedToTenant($branchColumn)` — applies the current user's accessible
     * branch filter to BOTH Eloquent and raw query-builder chains, using
     * TenantContext as the single source of truth. Replaces the hand-written
     * where('branch_id'…) sites with one consistent, leak-proof call.
     *
     *   DB::table('students')->scopedToTenant('students.branch_id')
     *   Student::query()->scopedToTenant()
     */
    protected function registerTenantScopeMacro(): void
    {
        $macro = function (string $branchColumn = 'branch_id') {
            /** @var TenantContext $tenant */
            $tenant = app(TenantContext::class);
            $branches = $tenant->accessibleBranchIds();

            if ($branches === 'all') {
                return $this;
            }

            if (empty($branches)) {
                return $this->whereRaw('1 = 0');
            }

            return $this->whereIn($branchColumn, $branches);
        };

        QueryBuilder::macro('scopedToTenant', $macro);
        EloquentBuilder::macro('scopedToTenant', $macro);
    }
}
