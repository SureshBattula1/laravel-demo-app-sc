<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class BranchService
{
    /**
     * Get branches with optimized queries and caching
     */
    public function getBranches($filters = [], $paginate = true, $perPage = 15)
    {
        $cacheKey = 'branches:' . md5(json_encode($filters) . $paginate . $perPage);
        
        return Cache::remember($cacheKey, 300, function () use ($filters, $paginate, $perPage) {
            $query = Branch::query()
                ->select([
                    'branches.*',
                    DB::raw('(SELECT COUNT(*) FROM branches as children WHERE children.parent_branch_id = branches.id) as children_count'),
                    DB::raw('ROUND((current_enrollment / NULLIF(total_capacity, 0)) * 100, 2) as capacity_utilization')
                ]);

            // Apply filters efficiently
            $this->applyFilters($query, $filters);

            // Eager load relationships to prevent N+1
            $query->with(['parentBranch:id,name,code']);

            if ($filters['hierarchical'] ?? false) {
                return $query->whereNull('parent_branch_id')
                    ->with('childBranches')
                    ->orderBy('name')
                    ->get();
            }

            return $paginate 
                ? $query->orderBy('name', 'asc')->paginate($perPage)
                : $query->orderBy('name', 'asc')->get();
        });
    }

    /**
     * Apply filters to query
     */
    private function applyFilters($query, $filters)
    {
        if (!empty($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['branch_type'])) {
            $query->where('branch_type', $filters['branch_type']);
        }

        if (!empty($filters['region'])) {
            $query->where('region', $filters['region']);
        }

        if (!empty($filters['city'])) {
            $query->where('city', $filters['city']);
        }

        if (isset($filters['parent_id'])) {
            if ($filters['parent_id'] === 'null' || $filters['parent_id'] === '0') {
                $query->whereNull('parent_branch_id');
            } else {
                $query->where('parent_branch_id', $filters['parent_id']);
            }
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Create branch with validation and caching
     */
    public function createBranch($data)
    {
        DB::beginTransaction();
        try {
            // Handle logo upload if present
            if (isset($data['logo']) && $data['logo']) {
                $data['logo'] = $data['logo']->store('branch-logos', 'public');
            }

            $branch = Branch::create($data);

            // Clear cache
            Cache::tags(['branches'])->flush();

            DB::commit();
            
            Log::info('Branch created', ['branch_id' => $branch->id, 'name' => $branch->name]);
            
            return $branch;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Branch creation failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Update branch with cache invalidation
     */
    public function updateBranch($id, $data)
    {
        DB::beginTransaction();
        try {
            $branch = Branch::findOrFail($id);

            // Handle logo replacement
            if (isset($data['logo']) && $data['logo']) {
                if ($branch->logo) {
                    Storage::disk('public')->delete($branch->logo);
                }
                $data['logo'] = $data['logo']->store('branch-logos', 'public');
            }

            $branch->update($data);

            // Clear cache
            Cache::tags(['branches'])->flush();

            DB::commit();
            
            Log::info('Branch updated', ['branch_id' => $branch->id]);
            
            return $branch;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Branch update failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Soft delete branch (set status to Closed)
     */
    public function softDeleteBranch($id)
    {
        DB::beginTransaction();
        try {
            $branch = Branch::findOrFail($id);

            $branch->update([
                'status' => 'Closed',
                'is_active' => false,
                'closing_date' => now()
            ]);

            // Recursively deactivate child branches
            $this->deactivateChildren($branch->id);

            // Clear cache
            Cache::tags(['branches'])->flush();

            DB::commit();
            
            Log::info('Branch soft deleted', ['branch_id' => $branch->id]);
            
            return $branch;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Branch deletion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Recursively deactivate child branches
     */
    private function deactivateChildren($parentId)
    {
        $children = Branch::where('parent_branch_id', $parentId)->get();
        
        foreach ($children as $child) {
            $child->update([
                'status' => 'Closed',
                'is_active' => false,
                'closing_date' => now()
            ]);
            
            // Recursively deactivate grandchildren
            $this->deactivateChildren($child->id);
        }
    }

    /**
     * Get branch statistics with caching
     */
    public function getBranchStats($id)
    {
        $cacheKey = "branch:stats:{$id}";
        
        return Cache::remember($cacheKey, 300, function () use ($id) {
            $branch = Branch::findOrFail($id);

            return [
                'total_students' => DB::table('students')->where('branch_id', $id)->count(),
                'total_teachers' => DB::table('teachers')->where('branch_id', $id)->count(),
                'total_departments' => DB::table('departments')->where('branch_id', $id)->count(),
                'total_subjects' => DB::table('subjects')->where('branch_id', $id)->count(),
                'capacity_utilization' => round(($branch->current_enrollment / max($branch->total_capacity, 1)) * 100, 2),
                'children_count' => Branch::where('parent_branch_id', $id)->count(),
            ];
        });
    }

    /**
     * Clear all branch caches
     */
    public function clearCache()
    {
        Cache::tags(['branches'])->flush();
    }
}

