<?php

namespace App\NotificationCampaigns\Concerns;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

trait EligibleTargetHelpers
{
    /**
     * @param  Collection<int, object{grade_level?:string,grade?:string,section:string,marked_count?:int,cnt?:int,student_count?:int}>  $rows
     * @return list<array{grade:string,section:string,class_name:string,student_count:int}>
     */
    protected function mapEligibleRows(Collection $rows, string $gradeKey = 'grade_level', string $countKey = 'marked_count'): array
    {
        $labels = DB::table('grades')->pluck('label', 'value');

        return $rows->map(function ($row) use ($labels, $gradeKey, $countKey) {
            $grade = (string) ($row->{$gradeKey} ?? $row->grade ?? '');
            $count = (int) ($row->{$countKey} ?? $row->cnt ?? $row->student_count ?? 0);

            return [
                'grade' => $grade,
                'section' => (string) $row->section,
                'class_name' => (string) ($labels[$grade] ?? ('Grade '.$grade)),
                'student_count' => $count,
            ];
        })->values()->all();
    }

    /**
     * @return list<array{grade:string,section:string,class_name:string,student_count:int}>
     */
    protected function enrolledSections(int $branchId): array
    {
        $rows = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as student_count')
            ->groupBy('grade', 'section')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        return $this->mapEligibleRows($rows, 'grade', 'student_count');
    }
}
