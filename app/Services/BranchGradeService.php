<?php

namespace App\Services;

use App\Models\Branch;
use Illuminate\Support\Facades\DB;

class BranchGradeService
{
    /**
     * Ensure a branch has default grades (1..12). Idempotent.
     */
    public function ensureDefaults(int $branchId): void
    {
        $branch = Branch::query()->select(['id', 'school_id'])->find($branchId);
        if (!$branch) {
            return;
        }

        $hasAny = DB::table('grades')->where('branch_id', $branchId)->exists();
        if ($hasAny) {
            return;
        }

        $defaults = [
            ['value' => '1', 'order' => 5, 'category' => 'Primary', 'label' => 'Grade 1'],
            ['value' => '2', 'order' => 6, 'category' => 'Primary', 'label' => 'Grade 2'],
            ['value' => '3', 'order' => 7, 'category' => 'Primary', 'label' => 'Grade 3'],
            ['value' => '4', 'order' => 8, 'category' => 'Primary', 'label' => 'Grade 4'],
            ['value' => '5', 'order' => 9, 'category' => 'Primary', 'label' => 'Grade 5'],
            ['value' => '6', 'order' => 10, 'category' => 'Middle', 'label' => 'Grade 6'],
            ['value' => '7', 'order' => 11, 'category' => 'Middle', 'label' => 'Grade 7'],
            ['value' => '8', 'order' => 12, 'category' => 'Middle', 'label' => 'Grade 8'],
            ['value' => '9', 'order' => 13, 'category' => 'Secondary', 'label' => 'Grade 9'],
            ['value' => '10', 'order' => 14, 'category' => 'Secondary', 'label' => 'Grade 10'],
            ['value' => '11', 'order' => 15, 'category' => 'Senior-Secondary', 'label' => 'Grade 11'],
            ['value' => '12', 'order' => 16, 'category' => 'Senior-Secondary', 'label' => 'Grade 12'],
        ];

        foreach ($defaults as $g) {
            DB::table('grades')->insert([
                'school_id' => $branch->school_id,
                'branch_id' => $branchId,
                'value' => $g['value'],
                'order' => $g['order'],
                'category' => $g['category'],
                'label' => $g['label'],
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

