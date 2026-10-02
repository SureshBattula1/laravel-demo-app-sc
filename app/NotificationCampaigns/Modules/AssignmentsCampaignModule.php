<?php

namespace App\NotificationCampaigns\Modules;

use App\Models\Assignment;
use App\NotificationCampaigns\Concerns\EligibleTargetHelpers;
use App\NotificationCampaigns\NotificationCampaignModule;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AssignmentsCampaignModule implements NotificationCampaignModule
{
    use EligibleTargetHelpers;

    public function slug(): string
    {
        return 'assignments';
    }

    public function label(): string
    {
        return 'Assignments';
    }

    public function statuses(): array
    {
        return [
            ['key' => 'published', 'label' => 'Published'],
            ['key' => 'due', 'label' => 'Assignment'],
        ];
    }

    public function requiresEventDate(): bool
    {
        return true;
    }

    public function confirmSelection(): bool
    {
        return false;
    }

    public function eventDatePolicy(): string
    {
        return 'any';
    }

    /**
     * Status keys that have at least one assignment on this notification date.
     *
     * @return list<string>
     */
    public function statusKeysForDate(int $branchId, string $date): array
    {
        $keys = [];
        foreach ($this->assignmentsInScope($branchId, $date) as $assignment) {
            $status = self::statusKeyForAssignmentOnDate($assignment, $date);
            if ($status !== null) {
                $keys[$status] = true;
            }
        }

        return array_keys($keys);
    }

    public function eligibleTargets(int $branchId, string $date): array
    {
        $assignments = $this->assignmentsInScope($branchId, $date);

        if ($assignments->isEmpty()) {
            return [];
        }

        $counts = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->selectRaw('grade, section, COUNT(*) as student_count')
            ->groupBy('grade', 'section')
            ->get()
            ->keyBy(fn ($r) => $r->grade.'|'.$r->section);

        $targetKeys = collect();
        foreach ($assignments as $assignment) {
            $grade = (string) $assignment->grade;
            $section = trim((string) ($assignment->section ?? ''));
            if ($section === '') {
                foreach ($counts as $key => $row) {
                    if (self::gradesMatch((string) $row->grade, $grade)) {
                        $targetKeys->push($key);
                    }
                }
            } else {
                foreach ($counts as $key => $row) {
                    if (self::gradesMatch((string) $row->grade, $grade) && trim((string) $row->section) === $section) {
                        $targetKeys->push($key);
                    }
                }
            }
        }

        $rows = collect();
        foreach ($targetKeys->unique() as $key) {
            $row = $counts->get($key);
            if (! $row) {
                continue;
            }
            [$grade, $section] = explode('|', $key, 2);
            $rows->push((object) [
                'grade' => $grade,
                'section' => $section,
                'student_count' => (int) $row->student_count,
            ]);
        }

        return $this->mapEligibleRows($rows, 'grade', 'student_count');
    }

    public function classifyByUserId(int $branchId, string $date, Collection $students): array
    {
        $assignments = $this->assignmentsInScope($branchId, $date);

        $map = [];
        foreach ($students as $student) {
            $userId = (int) $student->user_id;
            if ($userId <= 0) {
                continue;
            }
            $status = self::resolveStatusForStudent($assignments, $date, (string) $student->grade, (string) ($student->section ?? ''));
            if ($status !== null) {
                $map[$userId] = $status;
            }
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        $assignment = self::pickAssignmentForStudent(
            $this->assignmentsInScope($branchId, $date),
            $date,
            (string) $student->grade,
            (string) ($student->section ?? ''),
            $statusKey
        );

        if ($assignment) {
            $baseContext['assignment_id'] = (int) $assignment->id;
            $baseContext['assignment_title'] = (string) $assignment->title;
            $due = $assignment->due_date ? Carbon::parse($assignment->due_date)->format('d M Y') : '';
            if ($due !== '') {
                $baseContext['date'] = $due;
            }
        }

        return $baseContext;
    }

    /**
     * Published assignments relevant to the chosen notification date (not every past due row).
     */
    private function assignmentsInScope(int $branchId, string $date): Collection
    {
        return Assignment::query()
            ->where('branch_id', $branchId)
            ->where('is_published', true)
            ->where(function ($q) use ($date) {
                $q->whereDate('due_date', $date)
                    ->orWhere(function ($q2) use ($date) {
                        $q2->whereDate('published_at', $date)
                            ->whereDate('due_date', '>', $date);
                    });
            })
            ->orderBy('due_date')
            ->get(['grade', 'section', 'title', 'due_date', 'published_at']);
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    public static function resolveStatusForStudent(
        Collection $assignments,
        string $date,
        string $studentGrade,
        string $studentSection
    ): ?string {
        $resolved = null;
        foreach ($assignments as $assignment) {
            if (! self::assignmentAppliesToStudent($assignment, $studentGrade, $studentSection)) {
                continue;
            }
            $status = self::statusKeyForAssignmentOnDate($assignment, $date);
            if ($status === 'due') {
                return 'due';
            }
            if ($status === 'published') {
                $resolved = 'published';
            }
        }

        return $resolved;
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     */
    public static function pickAssignmentForStudent(
        Collection $assignments,
        string $date,
        string $studentGrade,
        string $studentSection,
        string $statusKey
    ): ?Assignment {
        $candidates = $assignments->filter(function ($assignment) use ($date, $studentGrade, $studentSection, $statusKey) {
            if (! self::assignmentAppliesToStudent($assignment, $studentGrade, $studentSection)) {
                return false;
            }

            return self::statusKeyForAssignmentOnDate($assignment, $date) === $statusKey;
        });

        return $candidates->sortBy('due_date')->first();
    }

    public static function statusKeyForAssignmentOnDate(object $assignment, string $date): ?string
    {
        $due = self::normalizeDateValue($assignment->due_date ?? null);
        if ($due === $date) {
            return 'due';
        }
        $published = self::normalizeDateValue($assignment->published_at ?? null);
        if ($published === $date && $due !== null && $due > $date) {
            return 'published';
        }

        return null;
    }

    private static function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    public static function assignmentAppliesToStudent(
        Assignment $assignment,
        string $studentGrade,
        string $studentSection
    ): bool {
        if (! self::gradesMatch((string) $assignment->grade, $studentGrade)) {
            return false;
        }
        $sec = trim((string) ($assignment->section ?? ''));

        return $sec === '' || $sec === trim($studentSection);
    }

    public static function gradesMatch(string $assignmentGrade, string $studentGrade): bool
    {
        return self::normalizeGradeKey($assignmentGrade) === self::normalizeGradeKey($studentGrade);
    }

    public static function normalizeGradeKey(string $grade): string
    {
        $g = trim($grade);
        if (preg_match('/^grade\s*(.+)$/i', $g, $m)) {
            return trim($m[1]);
        }

        return $g;
    }
}
