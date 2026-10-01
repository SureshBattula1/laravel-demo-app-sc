<?php

namespace App\NotificationCampaigns\Modules;

use App\Models\ExamMark;
use App\Models\ExamSchedule;
use App\NotificationCampaigns\Concerns\EligibleTargetHelpers;
use App\NotificationCampaigns\NotificationCampaignModule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ExamsCampaignModule implements NotificationCampaignModule
{
    use EligibleTargetHelpers;

    public function slug(): string
    {
        return 'exams';
    }

    public function label(): string
    {
        return 'Exams';
    }

    public function statuses(): array
    {
        return [
            ['key' => 'scheduled', 'label' => 'Upcoming exam'],
            ['key' => 'result', 'label' => 'Exam results'],
        ];
    }

    public function requiresEventDate(): bool
    {
        return false;
    }

    public function confirmSelection(): bool
    {
        return true;
    }

    public function eventDatePolicy(): string
    {
        return 'any';
    }

    public function eligibleTargets(int $branchId, ?string $date, ?int $examId = null): array
    {
        $summaries = $this->sectionExamSummaries($branchId, $this->normalizeScheduleDate($date), $examId);
        $rows = collect();

        foreach ($summaries as $summary) {
            if (($summary['schedule_count'] ?? 0) <= 0) {
                continue;
            }
            $rows->push((object) [
                'grade' => $summary['grade'],
                'section' => $summary['section'],
                'student_count' => (int) ($summary['student_count'] ?? 0),
            ]);
        }

        return $this->mapEligibleRows($rows, 'grade', 'student_count');
    }

    /**
     * Per grade/section exam schedule and marks tallies for the schedule UI.
     *
     * @return array<string, array{grade:string,section:string,student_count:int,schedule_count:int,marks_count:int}>
     */
    /**
     * Schedules for a branch on a date (matches exam.branch_id when schedule.branch_id is null).
     */
    public function schedulesForBranchAndDate(int $branchId, ?string $date, ?int $examId = null): Builder
    {
        $date = $this->normalizeScheduleDate($date);

        return ExamSchedule::query()
            ->when($date !== null, fn ($q) => $q->whereDate('exam_date', $date))
            ->when($examId !== null, fn ($q) => $q->where('exam_id', $examId))
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereHas('exam', fn ($exam) => $exam->where('branch_id', $branchId));
            });
    }

    /**
     * @return list<int>
     */
    public function examIdsWithSchedulesOnDate(int $branchId, ?string $date): array
    {
        return $this->schedulesForBranchAndDate($branchId, $date)
            ->distinct()
            ->pluck('exam_id')
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string> Y-m-d dates with at least one schedule (newest first).
     */
    public function distinctScheduleDatesForBranch(int $branchId, int $limit = 40): array
    {
        return ExamSchedule::query()
            ->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereHas('exam', fn ($exam) => $exam->where('branch_id', $branchId));
            })
            ->select('exam_date')
            ->distinct()
            ->orderByDesc('exam_date')
            ->limit($limit)
            ->pluck('exam_date')
            ->map(fn ($d) => $d instanceof \DateTimeInterface ? $d->format('Y-m-d') : (string) $d)
            ->values()
            ->all();
    }

    public function sectionExamSummaries(int $branchId, ?string $date, ?int $examId = null): array
    {
        $schedules = $this->schedulesForBranchAndDate($branchId, $date, $examId)
            ->get(['id', 'grade', 'section', 'exam_id']);

        $enrolled = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as student_count')
            ->groupBy('grade', 'section')
            ->get();

        $map = [];
        foreach ($enrolled as $row) {
            $key = $this->sectionKey((string) $row->grade, (string) $row->section);
            $map[$key] = [
                'grade' => (string) $row->grade,
                'section' => (string) $row->section,
                'student_count' => (int) $row->student_count,
                'schedule_count' => 0,
                'marks_count' => 0,
            ];
        }

        /** @var array<string, list<int>> $scheduleIdsBySectionKey */
        $scheduleIdsBySectionKey = [];

        foreach ($schedules as $schedule) {
            $grade = trim((string) $schedule->grade);
            if ($grade === '') {
                continue;
            }
            $section = trim((string) ($schedule->section ?? ''));
            $scheduleId = (int) $schedule->id;

            if ($section === '') {
                foreach ($this->sectionKeysForGrade($branchId, $grade, $map) as $key) {
                    $scheduleIdsBySectionKey[$key][$scheduleId] = $scheduleId;
                }
                continue;
            }

            $key = $this->sectionKey($grade, $section);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'grade' => $grade,
                    'section' => $section,
                    'student_count' => 0,
                    'schedule_count' => 0,
                    'marks_count' => 0,
                ];
            }
            $scheduleIdsBySectionKey[$key][$scheduleId] = $scheduleId;
        }

        foreach ($scheduleIdsBySectionKey as $key => $ids) {
            if (! isset($map[$key])) {
                continue;
            }
            $map[$key]['schedule_count'] = count($ids);
        }

        $allScheduleIds = collect($scheduleIdsBySectionKey)->flatten()->unique()->values()->all();
        if ($allScheduleIds !== []) {
            $markRows = ExamMark::query()
                ->join('students as s', 's.id', '=', 'exam_marks.student_id')
                ->where('s.branch_id', $branchId)
                ->whereIn('exam_marks.exam_schedule_id', $allScheduleIds)
                ->selectRaw('exam_marks.exam_schedule_id as schedule_id, s.grade as grade, s.section as section, s.id as student_id')
                ->get();

            $scheduleToGradeSection = [];
            foreach ($schedules as $schedule) {
                $grade = trim((string) $schedule->grade);
                $section = trim((string) ($schedule->section ?? ''));
                $scheduleToGradeSection[(int) $schedule->id] = ['grade' => $grade, 'section' => $section];
            }

            /** @var array<string, array<int, true>> $markedStudentsByKey */
            $markedStudentsByKey = [];

            foreach ($markRows as $row) {
                $scheduleMeta = $scheduleToGradeSection[(int) $row->schedule_id] ?? null;
                if (! $scheduleMeta) {
                    continue;
                }
                $grade = (string) $row->grade;
                $studentSection = (string) $row->section;
                if ($scheduleMeta['section'] === '') {
                    if ($scheduleMeta['grade'] !== $grade) {
                        continue;
                    }
                    $key = $this->sectionKey($grade, $studentSection);
                } else {
                    $key = $this->sectionKey($scheduleMeta['grade'], $scheduleMeta['section']);
                    if ($studentSection !== $scheduleMeta['section'] || $grade !== $scheduleMeta['grade']) {
                        continue;
                    }
                }
                if (! isset($markedStudentsByKey[$key])) {
                    $markedStudentsByKey[$key] = [];
                }
                $markedStudentsByKey[$key][(int) $row->student_id] = true;
            }

            foreach ($markedStudentsByKey as $key => $studentIds) {
                if (isset($map[$key])) {
                    $map[$key]['marks_count'] = count($studentIds);
                }
            }
        }

        return $map;
    }

    public function classifyByUserId(int $branchId, ?string $date, Collection $students, ?int $examId = null): array
    {
        $scheduleDate = $this->normalizeScheduleDate($date);
        $map = [];

        foreach ($students as $student) {
            $userId = (int) ($student->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }

            $scheduleIds = $this->schedulesForStudent($branchId, $scheduleDate, $student, $examId)->pluck('id')->all();
            if ($scheduleIds === []) {
                continue;
            }

            $hasMark = ExamMark::query()
                ->whereIn('exam_schedule_id', $scheduleIds)
                ->where('student_id', (int) $student->id)
                ->exists();

            $map[$userId] = $hasMark ? 'result' : 'scheduled';
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, ?string $date, int $branchId, ?int $examId = null): array
    {
        $scheduleDate = $this->normalizeScheduleDate($date);
        $schedule = $this->schedulesForStudent($branchId, $scheduleDate, $student, $examId)
            ->with('exam:id,name')
            ->orderBy('start_time')
            ->first();

        if ($schedule?->exam) {
            $baseContext['exam_name'] = (string) $schedule->exam->name;
        }
        if ($schedule?->exam_date) {
            $formatted = \Illuminate\Support\Carbon::parse($schedule->exam_date)->format('d M Y');
            $baseContext['date'] = $formatted;
            $baseContext['attendance_date'] = $formatted;
        }

        return $baseContext;
    }

    private function schedulesForStudent(int $branchId, ?string $date, $student, ?int $examId = null)
    {
        $grade = (string) ($student->grade ?? '');
        $section = (string) ($student->section ?? '');

        return $this->schedulesForBranchAndDate($branchId, $date, $examId)
            ->where('grade', $grade)
            ->where(function ($query) use ($section) {
                $query->whereNull('section')
                    ->orWhere('section', '')
                    ->orWhere('section', $section);
            });
    }

    /**
     * Section keys for a grade: enrolled students first, else configured sections for the branch.
     *
     * @param  array<string, array{grade:string,section:string,student_count:int,schedule_count:int,marks_count:int}>  $map
     * @return list<string>
     */
    private function sectionKeysForGrade(int $branchId, string $grade, array &$map): array
    {
        $keys = [];
        foreach ($map as $key => $entry) {
            if ($entry['grade'] === $grade) {
                $keys[] = $key;
            }
        }
        if ($keys !== []) {
            return $keys;
        }

        $sections = DB::table('sections')
            ->where('branch_id', $branchId)
            ->where('grade_level', $grade)
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name');

        foreach ($sections as $name) {
            $sectionName = trim((string) $name);
            if ($sectionName === '') {
                continue;
            }
            $key = $this->sectionKey($grade, $sectionName);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'grade' => $grade,
                    'section' => $sectionName,
                    'student_count' => 0,
                    'schedule_count' => 0,
                    'marks_count' => 0,
                ];
            }
            $keys[] = $key;
        }

        return $keys;
    }

    /**
     * Section keys (grade|section) that apply for an exam on a date and notify mode.
     *
     * @return list<string>
     */
    public function sectionKeysForExamMode(int $branchId, ?string $date, int $examId, string $notifyMode): array
    {
        $summaries = $this->sectionExamSummaries($branchId, $date, $examId);
        $keys = [];
        foreach ($summaries as $key => $summary) {
            if ($notifyMode === 'result') {
                if (($summary['marks_count'] ?? 0) > 0) {
                    $keys[] = $key;
                }
                continue;
            }
            if (($summary['schedule_count'] ?? 0) > 0) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function sectionKey(string $grade, string $section): string
    {
        return trim($grade).'|'.trim($section);
    }

    private function normalizeScheduleDate(?string $date): ?string
    {
        $date = trim((string) ($date ?? ''));
        if ($date === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        return $date;
    }
}
