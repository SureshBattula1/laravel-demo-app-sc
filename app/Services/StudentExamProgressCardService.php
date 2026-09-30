<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\ExamMark;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Services\Concerns\BuildsStudentReportBranding;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StudentExamProgressCardService
{
    use BuildsStudentReportBranding;

    /**
     * @return array{filename: string, pdf: \Barryvdh\DomPDF\PDF}
     */
    public function generatePdf(Request $request, int $studentUserId): array
    {
        $student = Student::with(['user', 'branch.school'])
            ->where('user_id', $studentUserId)
            ->firstOrFail();

        $rows = $this->collectResultRows($request, $student, $studentUserId);
        if ($rows->isEmpty()) {
            abort(404, 'No exam results found for this student.');
        }

        $scope = $this->resolveScope($request);
        $examId = $request->filled('exam_id') ? (int) $request->exam_id : null;
        $markId = $request->filled('mark_id') ? (int) $request->mark_id : null;

        if ($markId) {
            $rows = $rows->where('id', $markId)->values();
        } elseif ($examId) {
            $rows = $rows->where('exam_id', $examId)->values();
        }

        if ($rows->isEmpty()) {
            abort(404, 'No exam results match the requested filter.');
        }

        $sections = $this->groupIntoSections($rows);
        $viewData = $this->buildViewData($student, $sections);
        $filename = $this->buildFilename($student, $scope, $examId, $markId, $sections);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('pdf.student-exam-progress-card', $viewData);
        $pdf->setPaper('a4', 'portrait');

        return ['filename' => $filename, 'pdf' => $pdf];
    }

    protected function resolveScope(Request $request): string
    {
        if ($request->filled('mark_id')) {
            return 'row';
        }
        if ($request->filled('exam_id')) {
            return 'exam';
        }
        if ($request->boolean('all') || $request->input('scope') === 'all') {
            return 'all';
        }

        return 'all';
    }

    protected function collectResultRows(Request $request, Student $student, int $studentUserId): Collection
    {
        $marksQuery = ExamMark::where('exam_marks.student_id', $studentUserId)
            ->join('exam_schedules', 'exam_marks.exam_schedule_id', '=', 'exam_schedules.id')
            ->join('exams', 'exam_schedules.exam_id', '=', 'exams.id')
            ->where('exams.branch_id', $student->branch_id);

        $academicYearId = $request->attributes->get('academic_year_id') ?? $request->input('academic_year_id');
        if ($academicYearId && Schema::hasColumn('exams', 'academic_year_id')) {
            $marksQuery->where('exams.academic_year_id', (int) $academicYearId);
        } elseif ($academicYearId) {
            $academicYearName = AcademicYear::query()->where('id', $academicYearId)->value('name');
            if ($academicYearName) {
                $marksQuery->where('exams.academic_year', $academicYearName);
            }
        }

        if (! empty($student->school_id)) {
            $marksQuery->where('exams.school_id', $student->school_id);
        }

        $marks = $marksQuery->select('exam_marks.*')->get();
        if ($marks->isEmpty()) {
            return collect();
        }

        $schedules = ExamSchedule::with(['exam.examTerm', 'subject'])
            ->whereIn('id', $marks->pluck('exam_schedule_id')->unique()->all())
            ->get()
            ->keyBy('id');

        return $marks->map(function (ExamMark $mark) use ($schedules) {
            $schedule = $schedules->get($mark->exam_schedule_id);
            $obtained = (float) $mark->marks_obtained;
            $total = (float) $mark->total_marks;
            $pct = (float) $mark->percentage;

            $examDate = $schedule?->exam_date;
            $dateStr = '—';
            if ($examDate) {
                try {
                    $dateStr = \Carbon\Carbon::parse($examDate)->format('d-m-Y');
                } catch (\Throwable) {
                    $dateStr = (string) $examDate;
                }
            }

            $marksDisplay = $mark->is_absent ? 'Absent' : $this->fmt($obtained, 0);
            $gradeDisplay = $mark->is_absent ? '—' : ($mark->grade ?: '—');

            return [
                'id' => $mark->id,
                'exam_id' => $schedule?->exam_id,
                'exam_name' => $schedule?->exam?->name ?? 'Examination',
                'exam_term_name' => $schedule?->exam?->examTerm?->name,
                'subject' => $schedule?->subject?->name ?? 'Subject',
                'date' => $dateStr,
                'marks' => $marksDisplay,
                'description' => $mark->remarks ? (string) $mark->remarks : '—',
                'grade' => $gradeDisplay,
                'marks_obtained' => $mark->is_absent ? 0.0 : $obtained,
                'total_marks' => $total,
                'percentage' => $pct,
                'is_absent' => (bool) $mark->is_absent,
            ];
        })->values();
    }

    protected function groupIntoSections(Collection $rows): array
    {
        $grouped = $rows->groupBy(function (array $row) {
            return $row['exam_id'] ?: $row['exam_name'];
        });

        $sections = [];
        foreach ($grouped as $group) {
            $first = $group->first();
            $sortedRows = $group->sortBy('subject')->values()->all();
            $sections[] = [
                'exam_title' => $first['exam_name'] ?? 'Examination',
                'exam_term' => $first['exam_term_name'] ?? null,
                'exam_pill_label' => $this->buildExamPillLabel($first),
                'rows' => $sortedRows,
                'summary' => $this->computeSectionSummary($sortedRows),
            ];
        }

        usort($sections, function ($a, $b) {
            return strcmp($b['rows'][0]['date'] ?? '', $a['rows'][0]['date'] ?? '');
        });

        return $sections;
    }

    protected function buildExamPillLabel(array $first): string
    {
        $term = trim((string) ($first['exam_term_name'] ?? ''));
        $name = trim((string) ($first['exam_name'] ?? 'Examination'));
        if ($term !== '') {
            return strtoupper($term.' — '.$name);
        }

        return strtoupper($name);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total_obtained: float, total_max: float, percentage: float, percentage_label: string, grade: string, total_label: string}
     */
    protected function computeSectionSummary(array $rows): array
    {
        $totalMax = 0.0;
        $totalObtained = 0.0;
        $hasScored = false;

        foreach ($rows as $row) {
            $totalMax += (float) ($row['total_marks'] ?? 0);
            if (empty($row['is_absent'])) {
                $totalObtained += (float) ($row['marks_obtained'] ?? 0);
                $hasScored = true;
            }
        }

        $percentage = $totalMax > 0 ? ($totalObtained / $totalMax) * 100 : 0.0;
        $grade = $hasScored ? $this->letterGradeFromPercentage($percentage) : '—';

        return [
            'total_obtained' => $totalObtained,
            'total_max' => $totalMax,
            'percentage' => $percentage,
            'percentage_label' => $this->fmt($percentage, 2).'%',
            'grade' => $grade,
            'total_label' => $this->fmt($totalObtained, 0).' / '.$this->fmt($totalMax, 0),
        ];
    }

    protected function letterGradeFromPercentage(float $pct): string
    {
        return match (true) {
            $pct >= 90 => 'A+',
            $pct >= 80 => 'A',
            $pct >= 70 => 'B+',
            $pct >= 60 => 'B',
            $pct >= 50 => 'C',
            $pct >= 40 => 'D',
            default => 'F',
        };
    }

    protected function buildViewData(Student $student, array $sections): array
    {
        return array_merge(
            $this->buildReportBrandingContext($student, 'PROGRESS REPORT'),
            [
                'sections' => $sections,
                'generatedAt' => now()->format('d M Y'),
            ]
        );
    }

    protected function buildFilename(Student $student, string $scope, ?int $examId, ?int $markId, array $sections): string
    {
        $slug = fn (?string $s) => preg_replace('/[^a-zA-Z0-9-]+/', '-', trim($s ?? 'student')) ?: 'student';
        $user = $student->user;
        $name = $slug(trim(($user?->first_name ?? '').'-'.($user?->last_name ?? '')));

        if ($markId && count($sections) === 1 && count($sections[0]['rows']) === 1) {
            $subject = $slug($sections[0]['rows'][0]['subject'] ?? 'subject');

            return "progress-card-{$name}-{$subject}.pdf";
        }

        if ($examId && count($sections) === 1) {
            $exam = $slug($sections[0]['exam_title'] ?? 'exam');

            return "progress-card-{$name}-{$exam}.pdf";
        }

        return "progress-card-{$name}-all-exams.pdf";
    }

    protected function fmt(float $value, int $decimals = 2): string
    {
        if ($decimals === 0) {
            return (string) (int) round($value);
        }

        return number_format($value, $decimals, '.', '');
    }
}
