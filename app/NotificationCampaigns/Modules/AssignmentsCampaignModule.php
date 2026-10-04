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
            ['key' => 'published', 'label' => 'Assignment'],
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
                    if (
                        self::gradesMatch((string) $row->grade, $grade)
                        && self::sectionsMatch((string) $row->section, $section)
                    ) {
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
            $status = self::resolveStatusForStudent(
                $assignments,
                $date,
                (string) $student->grade,
                (string) ($student->section ?? ''),
            );
            if ($status !== null) {
                $map[$userId] = $status;
            }
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        $forStudent = self::assignmentsForStudent(
            $this->assignmentsInScope($branchId, $date),
            $date,
            (string) $student->grade,
            (string) ($student->section ?? ''),
            $statusKey,
        );

        if ($forStudent->isEmpty()) {
            return $baseContext;
        }

        $tags = self::buildAssignmentTagContext($forStudent);
        foreach ($tags as $key => $value) {
            if ($key === 'assignment_ids') {
                $baseContext['assignment_ids'] = is_array($value) ? $value : [];

                continue;
            }
            if ($key === 'assignment_id') {
                if ($value !== null) {
                    $baseContext['assignment_id'] = (int) $value;
                }

                continue;
            }
            $baseContext[$key] = (string) $value;
        }

        $firstDue = $forStudent->first()?->due_date;
        if ($firstDue) {
            $dueLabel = Carbon::parse($firstDue)->format('d M Y');
            if ($dueLabel !== '') {
                $baseContext['date'] = $dueLabel;
            }
        }

        return $baseContext;
    }

    /**
     * Assignments published on the chosen notification date (one notify per publish, not on due date).
     *
     * @return Collection<int, Assignment>
     */
    public function assignmentsPublishedOnDate(int $branchId, string $date): Collection
    {
        return $this->assignmentsInScope($branchId, $date);
    }

    /**
     * @return Collection<int, Assignment>
     */
    private function assignmentsInScope(int $branchId, string $date): Collection
    {
        [$start, $end] = self::publishedDayBounds($date);

        return Assignment::query()
            ->where('branch_id', $branchId)
            ->where('is_published', true)
            ->where('published_at', '>=', $start)
            ->where('published_at', '<', $end)
            ->with(['subject:id,name'])
            ->orderBy('due_date')
            ->get(['id', 'grade', 'section', 'title', 'due_date', 'published_at', 'subject_id']);
    }

    /**
     * Inclusive calendar-day window for published_at in the app timezone.
     *
     * @return array{0:\Carbon\CarbonInterface,1:\Carbon\CarbonInterface}
     */
    public static function publishedDayBounds(string $date): array
    {
        $tz = self::appTimezone();
        $start = Carbon::parse($date, $tz)->startOfDay();
        $end = $start->copy()->addDay();

        return [$start, $end];
    }

    public static function appTimezone(): string
    {
        if (! function_exists('config')) {
            return 'UTC';
        }

        try {
            return (string) (config('app.timezone') ?: 'UTC');
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    /**
     * @param  Collection<int, Assignment>  $assignments
     * @return Collection<int, Assignment>
     */
    public static function assignmentsForStudent(
        Collection $assignments,
        string $date,
        string $studentGrade,
        string $studentSection,
        string $statusKey = 'published',
    ): Collection {
        return $assignments
            ->filter(function ($assignment) use ($date, $studentGrade, $studentSection, $statusKey) {
                if (! self::assignmentAppliesToStudent($assignment, $studentGrade, $studentSection)) {
                    return false;
                }

                return self::statusKeyForAssignmentOnDate($assignment, $date) === $statusKey;
            })
            ->sortBy([
                fn (object $a) => strtolower(self::subjectNameForAssignment($a)),
                fn (object $a) => strtolower((string) ($a->title ?? '')),
                fn (object $a) => self::normalizeDateValue($a->due_date ?? null) ?? '',
            ])
            ->values();
    }

    /**
     * @param  Collection<int, Assignment>  $sortedAssignments  Already filtered and sorted for one student
     * @return array<string, int|string|null>
     */
    public static function buildAssignmentTagContext(
        Collection $sortedAssignments,
        ?int $maxItems = null,
        ?string $separator = null,
    ): array {
        if ($sortedAssignments->isEmpty()) {
            return [
                'assignment_count' => '0',
                'assignment_titles' => '',
                'assignment_list' => '',
                'subject_names' => '',
                'assignment_title' => '',
                'assignment_id' => null,
                'assignment_ids' => [],
            ];
        }

        $maxItems = max(1, $maxItems ?? (int) config('notification_campaigns.assignment_list_max_items', 10));
        $separator = $separator ?? (string) config('notification_campaigns.assignment_list_separator', '; ');

        $lines = [];
        $titles = [];
        $subjects = [];
        foreach ($sortedAssignments as $assignment) {
            $lines[] = self::formatAssignmentLine($assignment);
            $titles[] = (string) $assignment->title;
            $subject = self::subjectNameForAssignment($assignment);
            if ($subject !== '') {
                $subjects[$subject] = true;
            }
        }

        $total = count($lines);
        $visibleLines = array_slice($lines, 0, $maxItems);
        $visibleTitles = array_slice($titles, 0, $maxItems);
        $overflow = $total - count($visibleLines);

        $assignmentList = implode($separator, $visibleLines);
        $assignmentTitles = implode('; ', $visibleTitles);
        if ($overflow > 0) {
            $suffix = $separator.'+ '.$overflow.' more';
            $assignmentList .= $suffix;
            $assignmentTitles .= '; + '.$overflow.' more';
        }

        $primary = $sortedAssignments->first();
        $primaryTitle = $primary ? (string) ($primary->title ?? '') : '';
        $assignmentIds = $sortedAssignments
            ->map(fn (object $a) => isset($a->id) ? (int) $a->id : 0)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();

        return [
            'assignment_count' => (string) $total,
            'assignment_titles' => $assignmentTitles,
            'assignment_list' => $assignmentList,
            'subject_names' => implode(', ', array_keys($subjects)),
            'assignment_title' => $lines[0] ?? $primaryTitle,
            'assignment_id' => $primary && isset($primary->id) ? (int) $primary->id : null,
            'assignment_ids' => $assignmentIds,
        ];
    }

    public static function formatAssignmentLine(object $assignment): string
    {
        $subject = self::subjectNameForAssignment($assignment);
        $title = (string) ($assignment->title ?? '');
        $line = $subject !== '' ? $subject.' – '.$title : $title;
        $dueRaw = $assignment->due_date ?? null;
        $due = $dueRaw ? Carbon::parse($dueRaw)->format('d M Y') : '';
        if ($due !== '') {
            $line .= ' (Due '.$due.')';
        }

        return $line;
    }

    public static function subjectNameForAssignment(object $assignment): string
    {
        if ($assignment instanceof Assignment
            && $assignment->relationLoaded('subject')
            && $assignment->subject !== null) {
            return trim((string) $assignment->subject->name);
        }
        if (isset($assignment->subject) && is_object($assignment->subject)) {
            return trim((string) ($assignment->subject->name ?? ''));
        }

        return '';
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
        foreach ($assignments as $assignment) {
            if (! self::assignmentAppliesToStudent($assignment, $studentGrade, $studentSection)) {
                continue;
            }
            if (self::statusKeyForAssignmentOnDate($assignment, $date) === 'published') {
                return 'published';
            }
        }

        return null;
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
    ): ?object {
        return self::assignmentsForStudent($assignments, $date, $studentGrade, $studentSection, $statusKey)->first();
    }

    public static function statusKeyForAssignmentOnDate(object $assignment, string $date): ?string
    {
        $publishedDay = self::publishedCalendarDate($assignment->published_at ?? null);

        return $publishedDay === $date ? 'published' : null;
    }

    public static function publishedCalendarDate(mixed $publishedAt): ?string
    {
        if ($publishedAt === null || $publishedAt === '') {
            return null;
        }

        $tz = self::appTimezone();

        return Carbon::parse($publishedAt, $tz)->toDateString();
    }

    private static function normalizeDateValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->toDateString();
    }

    public static function assignmentAppliesToStudent(
        object $assignment,
        string $studentGrade,
        string $studentSection
    ): bool {
        if (! self::gradesMatch((string) $assignment->grade, $studentGrade)) {
            return false;
        }
        $sec = trim((string) ($assignment->section ?? ''));

        return $sec === '' || self::sectionsMatch($sec, $studentSection);
    }

    public static function sectionsMatch(string $a, string $b): bool
    {
        return strcasecmp(trim($a), trim($b)) === 0;
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

    /**
     * @param  list<int>  $currentIds
     * @param  list<int>  $notifiedIds
     */
    public static function hasNewAssignmentIds(array $currentIds, array $notifiedIds): bool
    {
        if ($currentIds === []) {
            return false;
        }

        return count(array_diff($currentIds, $notifiedIds)) > 0;
    }
}
