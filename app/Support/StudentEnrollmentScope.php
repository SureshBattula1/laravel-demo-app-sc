<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Who belongs in a class for a selected academic year.
 *
 * An Active enrollment for that year is the class. Students with no enrollment
 * row for the year fall back to students.grade / academic_year_id. An Inactive
 * enrollment means they were promoted out of that year and must not stay in it.
 */
class StudentEnrollmentScope
{
    public const STATUS_ACTIVE = 'Active';

    public static function hasEnrollments(): bool
    {
        return Schema::hasTable('student_enrollments');
    }

    /**
     * COALESCE(enrollment.field, student.field). Pass a null enrollment alias for the student column only.
     */
    public static function column(string $studentAlias, ?string $enrollmentAlias, string $field): string
    {
        self::assertAlias($studentAlias);
        self::assertAlias($field);
        if ($enrollmentAlias === null || $enrollmentAlias === '') {
            return "{$studentAlias}.{$field}";
        }

        self::assertAlias($enrollmentAlias);

        return "COALESCE({$enrollmentAlias}.{$field}, {$studentAlias}.{$field})";
    }

    public static function joinActive(Builder $query, string $studentAlias, int $academicYearId, string $enrollmentAlias = 'se'): void
    {
        self::assertAlias($studentAlias);
        self::assertAlias($enrollmentAlias);

        $query->leftJoin("student_enrollments as {$enrollmentAlias}", function ($join) use ($studentAlias, $enrollmentAlias, $academicYearId) {
            $join->on("{$enrollmentAlias}.student_id", '=', "{$studentAlias}.id")
                ->where("{$enrollmentAlias}.academic_year_id", '=', $academicYearId)
                ->where("{$enrollmentAlias}.status", '=', self::STATUS_ACTIVE);
        });
    }

    /**
     * Active enrollment for this year, or a legacy student row with no enrollment at all.
     * The active enrollment must already be left-joined as $enrollmentAlias.
     */
    public static function whereYearMatch(Builder $query, string $studentAlias, int $academicYearId, string $enrollmentAlias = 'se'): void
    {
        self::assertAlias($studentAlias);
        self::assertAlias($enrollmentAlias);
        $anyAlias = $enrollmentAlias.'_any';
        self::assertAlias($anyAlias);

        $query->where(function ($q) use ($studentAlias, $enrollmentAlias, $academicYearId, $anyAlias) {
            $q->whereNotNull("{$enrollmentAlias}.id")
                ->orWhere(function ($legacy) use ($studentAlias, $academicYearId, $anyAlias) {
                    $legacy->where("{$studentAlias}.academic_year_id", $academicYearId)
                        ->whereNotExists(function ($sub) use ($studentAlias, $academicYearId, $anyAlias) {
                            $sub->select(DB::raw(1))
                                ->from("student_enrollments as {$anyAlias}")
                                ->whereColumn("{$anyAlias}.student_id", "{$studentAlias}.id")
                                ->where("{$anyAlias}.academic_year_id", $academicYearId);
                        });
                });
        });
    }

    /**
     * Restrict the query to students in this grade/section for the academic year.
     */
    public static function whereInClass(
        Builder $query,
        string $studentAlias,
        ?int $academicYearId,
        ?string $grade,
        ?string $section,
        string $enrollmentAlias = 'se'
    ): void {
        if ($academicYearId && self::hasEnrollments()) {
            self::joinActive($query, $studentAlias, $academicYearId, $enrollmentAlias);
            self::whereYearMatch($query, $studentAlias, $academicYearId, $enrollmentAlias);
            self::whereClassColumns($query, $studentAlias, $enrollmentAlias, $grade, $section);

            return;
        }

        if ($academicYearId) {
            self::assertAlias($studentAlias);
            $query->where("{$studentAlias}.academic_year_id", $academicYearId);
        }
        self::whereClassColumns($query, $studentAlias, null, $grade, $section);
    }

    /**
     * Class roster for the year, plus anyone who already has attendance marked
     * in that class and year. Promotion must not hide attendance already taken.
     */
    public static function whereInClassOrMarkedAttendance(
        Builder $query,
        string $studentAlias,
        ?int $academicYearId,
        string $grade,
        string $section,
        string $enrollmentAlias = 'se'
    ): void {
        $useEnrollment = (bool) ($academicYearId && self::hasEnrollments());
        if ($useEnrollment) {
            self::joinActive($query, $studentAlias, $academicYearId, $enrollmentAlias);
        }

        $query->where(function ($outer) use ($studentAlias, $enrollmentAlias, $academicYearId, $grade, $section, $useEnrollment) {
            $outer->where(function ($enrolled) use ($studentAlias, $enrollmentAlias, $academicYearId, $grade, $section, $useEnrollment) {
                if ($useEnrollment) {
                    self::whereYearMatch($enrolled, $studentAlias, $academicYearId, $enrollmentAlias);
                    self::whereClassColumns($enrolled, $studentAlias, $enrollmentAlias, $grade, $section);

                    return;
                }

                if ($academicYearId) {
                    self::assertAlias($studentAlias);
                    $enrolled->where("{$studentAlias}.academic_year_id", $academicYearId);
                }
                self::whereClassColumns($enrolled, $studentAlias, null, $grade, $section);
            })->orWhereExists(function ($att) use ($studentAlias, $academicYearId, $grade, $section) {
                self::assertAlias($studentAlias);
                $att->select(DB::raw(1))
                    ->from('student_attendance as sa_hist')
                    ->whereColumn('sa_hist.student_id', "{$studentAlias}.user_id")
                    ->where('sa_hist.grade_level', $grade)
                    ->where('sa_hist.section', $section);
                if ($academicYearId) {
                    $att->where('sa_hist.academic_year_id', $academicYearId);
                }
            });
        });
    }

    private static function whereClassColumns(Builder $query, string $studentAlias, ?string $enrollmentAlias, ?string $grade, ?string $section): void
    {
        if ($grade !== null && $grade !== '') {
            $query->whereRaw(self::column($studentAlias, $enrollmentAlias, 'grade').' = ?', [$grade]);
        }
        if ($section !== null && $section !== '') {
            $query->whereRaw(self::column($studentAlias, $enrollmentAlias, 'section').' = ?', [$section]);
        }
    }

    private static function assertAlias(string $alias): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
            throw new InvalidArgumentException('Invalid SQL alias.');
        }
    }
}
