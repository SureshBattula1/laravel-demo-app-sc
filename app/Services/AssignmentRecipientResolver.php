<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AssignmentRecipientResolver
{
    /**
     * @param  list<int>|null  $customStudentIds  students.id when audience is custom
     * @return list<int>
     */
    public function resolveStudentIds(
        int $branchId,
        string $grade,
        string $section,
        ?int $academicYearId,
        string $audienceMode,
        ?array $customStudentIds = null
    ): array {
        $eligible = $this->eligibleStudentIds($branchId, $grade, $section, $academicYearId);

        if ($audienceMode === 'custom') {
            $requested = collect($customStudentIds ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter(fn ($id) => $id > 0)
                ->unique()
                ->values()
                ->all();

            if ($requested === []) {
                throw ValidationException::withMessages([
                    'student_ids' => ['Select at least one student for a custom assignment.'],
                ]);
            }

            $invalid = array_values(array_diff($requested, $eligible));
            if ($invalid !== []) {
                throw ValidationException::withMessages([
                    'student_ids' => ['One or more students are not in the selected class and section.'],
                ]);
            }

            return $requested;
        }

        return $eligible;
    }

    /**
     * @return list<array{id:int,user_id:?int,name:string,admission_number:?string}>
     */
    public function eligibleStudents(int $branchId, string $grade, string $section, ?int $academicYearId): array
    {
        $rows = $this->eligibleQuery($branchId, $grade, $section, $academicYearId)
            ->select([
                'students.id',
                'students.user_id',
                'students.admission_number',
                'users.first_name',
                'users.last_name',
            ])
            ->orderBy('users.first_name')
            ->orderBy('users.last_name')
            ->get();

        return $rows->map(function ($row) {
            return [
                'id' => (int) $row->id,
                'user_id' => $row->user_id !== null ? (int) $row->user_id : null,
                'name' => trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')),
                'admission_number' => $row->admission_number,
            ];
        })->all();
    }

    /**
     * @return list<int>
     */
    public function eligibleStudentIds(int $branchId, string $grade, string $section, ?int $academicYearId): array
    {
        return $this->eligibleQuery($branchId, $grade, $section, $academicYearId)
            ->pluck('students.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * @return list<int>
     */
    public function studentUserIds(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        return DB::table('students')
            ->whereIn('id', $studentIds)
            ->whereNotNull('user_id')
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Teachers in the branch plus branch admins and school super admins.
     *
     * @return array{teachers: list<int>, admins: list<int>}
     */
    public function staffUserIds(int $branchId, ?int $schoolId, ?int $excludeUserId = null): array
    {
        $teachersQuery = DB::table('users')
            ->where('role', 'Teacher')
            ->where('branch_id', $branchId)
            ->where('is_active', 1)
            ->whereNull('deleted_at');

        $adminsQuery = DB::table('users')
            ->whereIn('role', ['SuperAdmin', 'BranchAdmin'])
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($branchId, $schoolId) {
                $q->where('branch_id', $branchId);
                if ($schoolId && Schema::hasColumn('users', 'school_id')) {
                    $q->orWhere(function ($inner) use ($schoolId) {
                        $inner->where('role', 'SuperAdmin')->where('school_id', $schoolId);
                    });
                }
            });

        $teachers = $teachersQuery->pluck('id')->map(fn ($id) => (int) $id)->all();
        $admins = $adminsQuery->pluck('id')->map(fn ($id) => (int) $id)->all();

        if ($excludeUserId) {
            $teachers = array_values(array_filter($teachers, fn ($id) => $id !== $excludeUserId));
            $admins = array_values(array_filter($admins, fn ($id) => $id !== $excludeUserId));
        }

        return [
            'teachers' => $teachers,
            'admins' => array_values(array_diff($admins, $teachers)),
        ];
    }

    private function eligibleQuery(int $branchId, string $grade, string $section, ?int $academicYearId)
    {
        $hasEnrollments = Schema::hasTable('student_enrollments');

        $query = DB::table('students')
            ->join('users', 'students.user_id', '=', 'users.id')
            ->where('students.branch_id', $branchId)
            ->whereNull('students.deleted_at')
            ->whereNull('users.deleted_at')
            ->where('users.is_active', 1)
            ->where('students.student_status', 'Active');

        if ($hasEnrollments && $academicYearId) {
            $query->leftJoin('student_enrollments as se', function ($join) use ($academicYearId) {
                $join->on('se.student_id', '=', 'students.id')
                    ->where('se.academic_year_id', '=', $academicYearId);
            })
                ->whereRaw('COALESCE(se.grade, students.grade) = ?', [$grade])
                ->whereRaw('COALESCE(se.section, students.section) = ?', [$section])
                ->whereRaw('COALESCE(se.academic_year_id, students.academic_year_id) = ?', [$academicYearId]);
        } else {
            $query->where('students.grade', $grade)
                ->where('students.section', $section);
            if ($academicYearId) {
                $query->where('students.academic_year_id', $academicYearId);
            }
        }

        return $query;
    }
}
