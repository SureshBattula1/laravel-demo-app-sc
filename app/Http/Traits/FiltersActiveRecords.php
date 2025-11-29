<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;

/**
 * Trait to filter only active (non-soft-deleted) teachers and students
 * Use this trait in all dependent modules (Attendance, Exams, Fees, etc.)
 */
trait FiltersActiveRecords
{
    /**
     * Scope to get only active students (not soft deleted)
     * 
     * @param Builder $query
     * @return Builder
     */
    protected function scopeActiveStudents(Builder $query): Builder
    {
        return $query->whereNull('students.deleted_at');
    }

    /**
     * Scope to get only active teachers (not soft deleted)
     * 
     * @param Builder $query
     * @return Builder
     */
    protected function scopeActiveTeachers(Builder $query): Builder
    {
        return $query->whereNull('teachers.deleted_at');
    }

    /**
     * Filter students query to only active records
     * 
     * @param Builder|mixed $query
     * @return Builder|mixed
     */
    protected function filterActiveStudents($query)
    {
        if (method_exists($query, 'whereNull')) {
            return $query->whereNull('deleted_at');
        }
        return $query;
    }

    /**
     * Filter teachers query to only active records
     * 
     * @param Builder|mixed $query
     * @return Builder|mixed
     */
    protected function filterActiveTeachers($query)
    {
        if (method_exists($query, 'whereNull')) {
            return $query->whereNull('deleted_at');
        }
        return $query;
    }

    /**
     * Get active students for a specific branch/grade/section
     * 
     * @param int|null $branchId
     * @param string|null $grade
     * @param string|null $section
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getActiveStudents($branchId = null, $grade = null, $section = null)
    {
        $query = \App\Models\Student::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($grade) {
            $query->where('grade', $grade);
        }

        if ($section) {
            $query->where('section', $section);
        }

        // Only active students (not soft deleted)
        return $query->with('user')->get();
    }

    /**
     * Get active teachers for a specific branch/department
     * 
     * @param int|null $branchId
     * @param int|null $departmentId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getActiveTeachers($branchId = null, $departmentId = null)
    {
        $query = \App\Models\Teacher::query();

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($departmentId) {
            $query->where('department_id', $departmentId);
        }

        // Only active teachers (not soft deleted)
        return $query->with('user')->get();
    }

    /**
     * Validate that student is active before operations
     * 
     * @param int $studentId
     * @return bool
     * @throws \Exception
     */
    protected function validateActiveStudent($studentId): bool
    {
        $student = \App\Models\Student::withTrashed()->find($studentId);
        
        if (!$student) {
            throw new \Exception('Student not found');
        }

        if ($student->trashed()) {
            throw new \Exception('Cannot perform operation on inactive student. Please activate the student first.');
        }

        return true;
    }

    /**
     * Validate that teacher is active before operations
     * 
     * @param int $teacherId
     * @return bool
     * @throws \Exception
     */
    protected function validateActiveTeacher($teacherId): bool
    {
        $teacher = \App\Models\Teacher::withTrashed()->find($teacherId);
        
        if (!$teacher) {
            throw new \Exception('Teacher not found');
        }

        if ($teacher->trashed()) {
            throw new \Exception('Cannot perform operation on inactive teacher. Please activate the teacher first.');
        }

        return true;
    }

    /**
     * Validate multiple students are active
     * 
     * @param array $studentIds
     * @return array ['valid' => [], 'inactive' => []]
     */
    protected function validateActiveStudents(array $studentIds): array
    {
        $allStudents = \App\Models\Student::withTrashed()->whereIn('id', $studentIds)->get();
        
        $valid = [];
        $inactive = [];

        foreach ($allStudents as $student) {
            if ($student->trashed()) {
                $inactive[] = $student->id;
            } else {
                $valid[] = $student->id;
            }
        }

        return [
            'valid' => $valid,
            'inactive' => $inactive
        ];
    }

    /**
     * Validate multiple teachers are active
     * 
     * @param array $teacherIds
     * @return array ['valid' => [], 'inactive' => []]
     */
    protected function validateActiveTeachers(array $teacherIds): array
    {
        $allTeachers = \App\Models\Teacher::withTrashed()->whereIn('id', $teacherIds)->get();
        
        $valid = [];
        $inactive = [];

        foreach ($allTeachers as $teacher) {
            if ($teacher->trashed()) {
                $inactive[] = $teacher->id;
            } else {
                $valid[] = $teacher->id;
            }
        }

        return [
            'valid' => $valid,
            'inactive' => $inactive
        ];
    }
}

