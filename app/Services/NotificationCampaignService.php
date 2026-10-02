<?php

namespace App\Services;

use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\NotificationCampaignTarget;
use App\Models\SmsTemplate;
use App\Models\Student;
use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationCampaignService
{
    public function __construct(
        protected SmsTemplateTagRenderer $renderer,
        protected InboxNotificationService $inbox,
        protected NotificationCampaignModuleRegistry $registry,
    ) {}

    /**
     * @return array<string, list<array{key:string,label:string}>>
     */
    public function modules(): array
    {
        return $this->registry->statusesByModule();
    }

    /**
     * @return array<string, array{label:string,requires_event_date:bool,confirm_selection:bool,event_date_policy:string}>
     */
    public function modulesMeta(): array
    {
        return $this->registry->metaByModule();
    }

    /**
     * All grades/sections configured for the branch (not only grades with enrolled students).
     *
     * @return list<array{grade:string,label:string,student_count:int,sections:list<array{section:string,student_count:int}>}>
     */
    public function classOptionsForBranch(int $branchId): array
    {
        $gradeRows = DB::table('grades')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('order')
            ->orderBy('value')
            ->get(['value', 'label']);

        if ($gradeRows->isEmpty()) {
            return $this->classOptionsFromStudents($branchId);
        }

        $studentCounts = $this->studentCountsByGradeSection($branchId);

        $sectionsByGrade = [];
        $sectionRows = DB::table('sections')
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['grade_level', 'name']);

        foreach ($sectionRows as $row) {
            $grade = trim((string) ($row->grade_level ?? ''));
            if ($grade === '') {
                continue;
            }
            $name = trim((string) $row->name);
            if ($name === '') {
                continue;
            }
            $sectionsByGrade[$grade][$name] = true;
        }

        foreach ($studentCounts as $key => $count) {
            [$grade, $section] = explode('|', $key, 2);
            if ($grade !== '' && $section !== '') {
                $sectionsByGrade[$grade][$section] = true;
            }
        }

        $grades = [];
        foreach ($gradeRows as $gradeRow) {
            $grade = trim((string) $gradeRow->value);
            $sectionNames = array_keys($sectionsByGrade[$grade] ?? []);
            sort($sectionNames, SORT_NATURAL | SORT_FLAG_CASE);

            $gradeTotal = 0;
            $sections = [];
            foreach ($sectionNames as $sectionName) {
                $count = $studentCounts[$this->sectionMapKey($grade, $sectionName)] ?? 0;
                $gradeTotal += $count;
                $sections[] = [
                    'section' => $sectionName,
                    'student_count' => $count,
                ];
            }

            $grades[] = [
                'grade' => $grade,
                'label' => (string) ($gradeRow->label ?: ('Grade '.$grade)),
                'student_count' => $gradeTotal,
                'sections' => $sections,
            ];
        }

        return $grades;
    }

    /**
     * @return array<string, int> keys grade|section
     */
    private function studentCountsByGradeSection(int $branchId): array
    {
        $map = [];
        $rows = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as c')
            ->groupBy('grade', 'section')
            ->get();

        foreach ($rows as $row) {
            $key = $this->sectionMapKey((string) $row->grade, (string) $row->section);
            $map[$key] = (int) $row->c;
        }

        return $map;
    }

    /**
     * @return list<array{grade:string,label:string,student_count:int,sections:list<array{section:string,student_count:int}>}>
     */
    private function classOptionsFromStudents(int $branchId): array
    {
        $labels = DB::table('grades')->where('branch_id', $branchId)->pluck('label', 'value');
        $map = [];
        $rows = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('grade')
            ->where('grade', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as c')
            ->groupBy('grade', 'section')
            ->orderBy('grade')
            ->orderBy('section')
            ->get();

        foreach ($rows as $row) {
            $g = (string) $row->grade;
            $sec = $row->section === null || $row->section === '' ? null : (string) $row->section;
            $c = (int) $row->c;
            if (! isset($map[$g])) {
                $map[$g] = [
                    'grade' => $g,
                    'label' => (string) ($labels[$g] ?? ('Grade '.$g)),
                    'student_count' => 0,
                    'sections' => [],
                ];
            }
            $map[$g]['student_count'] += $c;
            if ($sec !== null) {
                $map[$g]['sections'][] = ['section' => $sec, 'student_count' => $c];
            }
        }

        return array_values($map);
    }

    public function eventDatePolicy(string $module): string
    {
        return $this->registry->get($module)?->eventDatePolicy() ?? 'any';
    }

    /**
     * @return string|null Error message when date is not allowed.
     */
    public function validateEventDatePolicy(string $module, ?string $eventDate): ?string
    {
        $policy = $this->eventDatePolicy($module);
        if ($policy === 'any' || ! $eventDate) {
            return null;
        }

        $date = Carbon::parse($eventDate)->startOfDay();
        $today = now()->startOfDay();

        if ($policy === 'today_only' && ! $date->equalTo($today)) {
            return 'Attendance notifications can only be scheduled for today.';
        }

        if ($policy === 'today_or_future' && $date->lessThan($today)) {
            return 'Event date cannot be in the past.';
        }

        return null;
    }

    /**
     * Coerce client date to an allowed value for eligibility/delivery queries.
     */
    public function resolveEventDateForModule(string $module, string $date): string
    {
        if ($this->eventDatePolicy($module) === 'today_only') {
            return now()->toDateString();
        }

        return $date;
    }

    public function statusKeys(string $module): array
    {
        return array_column($this->modules()[$module] ?? [], 'key');
    }

    /**
     * @return list<array{grade:string,section:string,class_name:string,student_count:int}>
     */
    public function eligibleTargets(
        string $module,
        int $branchId,
        ?string $date,
        ?int $examId = null,
        ?string $feeType = null,
        ?string $academicYear = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
    ): array {
        $plugin = $this->registry->get($module);
        if (! $plugin) {
            return [];
        }

        if ($module === 'exams' && $plugin instanceof \App\NotificationCampaigns\Modules\ExamsCampaignModule) {
            return $plugin->eligibleTargets($branchId, $date, $examId);
        }

        if ($module === 'fees' && $plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            if ($feeNotifyMode === 'structure' && $feeStructureId !== null && $feeStructureId !== '') {
                return $plugin->eligibleTargetsForStructure($branchId, $feeStructureId);
            }

            return $plugin->eligibleTargetsForReminder(
                $branchId,
                $date,
                $feeType,
                $academicYear ?? $this->academicYearNameForCampaigns(),
            );
        }

        return $plugin->eligibleTargets($branchId, $date ?? now()->toDateString());
    }

    /**
     * @return list<array{grade:string,section:string,class_name:string,student_count:int,notification_status:string,campaign_id?:int,sent_count?:int}>
     */
    public function eligibleTargetsWithDelivery(
        string $module,
        int $branchId,
        ?string $date,
        ?int $examId = null,
        ?string $notifyMode = null,
        ?string $feeType = null,
        ?string $academicYear = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
        ?array $deliveryBySection = null,
    ): array {
        $rows = $this->eligibleTargets(
            $module,
            $branchId,
            $date,
            $examId,
            $feeType,
            $academicYear,
            $feeNotifyMode,
            $feeStructureId,
        );

        return $this->mergeDeliveryIntoEligibleRows(
            $module,
            $branchId,
            $date,
            $rows,
            $examId,
            $notifyMode,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
            $deliveryBySection,
        );
    }

    public function warmFeesReminderCache(int $branchId, ?string $academicYear): void
    {
        $plugin = $this->registry->get('fees');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            $plugin->preloadReminderRows($branchId, $academicYear);
        }
    }

    /**
     * Lightweight picker data for fees Due notify (no section eligibility or delivery).
     *
     * @return array{fee_type_options: list<array>, fee_due_dates: list<string>}
     */
    public function feesDueNotifyMeta(int $branchId, ?string $feeType, ?string $academicYear = null): array
    {
        $year = $academicYear ?? $this->academicYearNameForCampaigns();
        $this->warmFeesReminderCache($branchId, $year);

        return [
            'fee_type_options' => $this->feeTypeOptionsForBranch($branchId, null, $year),
            'fee_due_dates' => $this->feeDueDatesForBranch($branchId, $feeType, $year),
        ];
    }

    /**
     * @return array<string, array{notification_status:string,campaign_id?:int,sent_count?:int}>
     */
    /**
     * @return array<string, array{enrolled_count:int,marked_count:int,present:int,absent:int,leave:int}>
     */
    /**
     * @return list<string>
     */
    public function assignmentStatusKeysForDate(int $branchId, string $date): array
    {
        $plugin = $this->registry->get('assignments');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\AssignmentsCampaignModule) {
            return $plugin->statusKeysForDate($branchId, $date);
        }

        return [];
    }

    public function attendanceSummaryBySection(string $module, int $branchId, string $date): array
    {
        if ($module !== 'attendance') {
            return [];
        }

        $plugin = $this->registry->get('attendance');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\AttendanceCampaignModule) {
            return $plugin->sectionAttendanceSummaries($branchId, $date);
        }

        return [];
    }

    /**
     * @return array<string, array{grade:string,section:string,student_count:int,schedule_count:int,marks_count:int}>
     */
    public function examSummaryBySection(string $module, int $branchId, ?string $date, ?int $examId = null): array
    {
        if ($module !== 'exams') {
            return [];
        }

        $plugin = $this->registry->get('exams');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\ExamsCampaignModule) {
            return $plugin->sectionExamSummaries($branchId, $date, $examId);
        }

        return [];
    }

    /**
     * @return list<array{
     *   exam_id:int,
     *   name:string,
     *   academic_year:?string,
     *   list_state:'active'|'closed',
     *   selectable:bool
     * }>
     */
    public function examNotifyOptions(int $branchId, ?string $date, string $notifyMode): array
    {
        $plugin = $this->registry->get('exams');
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\ExamsCampaignModule) {
            return [];
        }

        if (! in_array($notifyMode, ['scheduled', 'result'], true)) {
            $notifyMode = 'scheduled';
        }

        $examIds = $plugin->examIdsWithSchedulesOnDate($branchId, $date);

        if ($examIds === []) {
            return [];
        }

        $exams = \App\Models\Exam::query()
            ->whereIn('id', $examIds)
            ->where('branch_id', $branchId)
            ->orderByDesc('id')
            ->get(['id', 'name', 'academic_year']);

        $options = [];
        foreach ($exams as $exam) {
            $examId = (int) $exam->id;
            $hasSchedules = $plugin->schedulesForBranchAndDate($branchId, $date, $examId)->exists();
            $sectionKeys = $plugin->sectionKeysForExamMode($branchId, $date, $examId, $notifyMode);

            if ($notifyMode === 'result' && $sectionKeys === []) {
                continue;
            }
            if (! $hasSchedules) {
                continue;
            }

            $allSent = false;
            if ($sectionKeys !== []) {
                $delivery = $this->deliveryStatusBySection('exams', $branchId, $date, $examId, $notifyMode);
                $allSent = true;
                foreach ($sectionKeys as $key) {
                    [$grade, $section] = explode('|', $key, 2);
                    $status = $delivery[$this->sectionMapKey($grade, $section)]['notification_status'] ?? 'not_sent';
                    if ($status !== 'sent') {
                        $allSent = false;
                        break;
                    }
                }
            }

            $options[] = [
                'exam_id' => $examId,
                'name' => (string) $exam->name,
                'academic_year' => $exam->academic_year ? (string) $exam->academic_year : null,
                'list_state' => $allSent ? 'closed' : 'active',
                'selectable' => ! $allSent,
            ];
        }

        usort($options, function (array $a, array $b) {
            if ($a['list_state'] !== $b['list_state']) {
                return $a['list_state'] === 'active' ? -1 : 1;
            }

            return $b['exam_id'] <=> $a['exam_id'];
        });

        return $options;
    }

    /**
     * @return list<string>
     */
    public function examScheduleDatesForBranch(int $branchId): array
    {
        $plugin = $this->registry->get('exams');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\ExamsCampaignModule) {
            return $plugin->distinctScheduleDatesForBranch($branchId);
        }

        return [];
    }

    /**
     * @return array<string, array{grade:string,section:string,unpaid_count:int,overdue_count:int}>
     */
    public function feeSummaryBySection(
        string $module,
        int $branchId,
        ?string $dueDate,
        ?string $feeType,
        ?string $academicYear = null,
    ): array {
        if ($module !== 'fees') {
            return [];
        }

        $plugin = $this->registry->get('fees');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            return $plugin->sectionFeeSummaries(
                $branchId,
                $dueDate,
                $feeType,
                $academicYear ?? $this->academicYearNameForCampaigns(),
            );
        }

        return [];
    }

    /**
     * @return list<array{fee_type:string,label:string,unpaid_student_count:int,list_state:string,selectable:bool}>
     */
    public function feeTypeOptionsForBranch(int $branchId, ?string $dueDate, ?string $academicYear = null): array
    {
        $plugin = $this->registry->get('fees');
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            return [];
        }

        $year = $academicYear ?? $this->academicYearNameForCampaigns();
        $options = $plugin->feeTypeOptions($branchId, $year, $dueDate);

        if ($dueDate === null || $dueDate === '') {
            return $options;
        }

        $sectionKeysByFeeType = $plugin->sectionKeysWithUnpaidByFeeType($branchId, $dueDate, $year);
        $deliveryByFeeType = $this->deliveryStatusByFeeTypeAndSectionForFeesDue($branchId, $dueDate);

        foreach ($options as &$option) {
            $feeType = $option['fee_type'];
            $sectionKeys = $sectionKeysByFeeType[$feeType] ?? [];
            if ($sectionKeys === []) {
                $option['list_state'] = 'closed';
                $option['selectable'] = false;

                continue;
            }

            $delivery = $deliveryByFeeType[$feeType] ?? [];
            $allSent = true;
            foreach ($sectionKeys as $key) {
                [$grade, $section] = explode('|', $key, 2);
                $status = $delivery[$this->sectionMapKey($grade, $section)]['notification_status'] ?? 'not_sent';
                if ($status !== 'sent') {
                    $allSent = false;
                    break;
                }
            }
            $option['list_state'] = $allSent ? 'closed' : 'active';
            $option['selectable'] = ! $allSent;
        }
        unset($option);

        usort($options, function (array $a, array $b) {
            if ($a['list_state'] !== $b['list_state']) {
                return $a['list_state'] === 'active' ? -1 : 1;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $options;
    }

    /**
     * @return list<string>
     */
    public function feeDueDatesForBranch(int $branchId, ?string $feeType, ?string $academicYear = null): array
    {
        $plugin = $this->registry->get('fees');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            return $plugin->distinctDueDatesForBranch(
                $branchId,
                $academicYear ?? $this->academicYearNameForCampaigns(),
                $feeType,
                15,
            );
        }

        return [];
    }

    /**
     * @return list<array{fee_structure_id:string,label:string,grade:string,fee_type:string,due_date:?string,amount:string,list_state:string,selectable:bool}>
     */
    public function feeStructureOptionsForBranch(int $branchId, ?string $academicYear = null): array
    {
        $plugin = $this->registry->get('fees');
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            return [];
        }

        $year = $academicYear ?? $this->academicYearNameForCampaigns();
        $options = $plugin->structureNotifyOptions($branchId, $year);

        foreach ($options as &$option) {
            $structureId = $option['fee_structure_id'];
            $dueDate = $option['due_date'] ?? null;
            if ($dueDate === null || $dueDate === '') {
                continue;
            }
            $sectionKeys = $plugin->sectionKeysForStructure($branchId, $structureId);
            if ($sectionKeys === []) {
                $option['list_state'] = 'closed';
                $option['selectable'] = false;

                continue;
            }
            $delivery = $this->deliveryStatusBySection(
                'fees',
                $branchId,
                $dueDate,
                null,
                null,
                null,
                'structure',
                $structureId,
            );
            $allSent = true;
            foreach ($sectionKeys as $key) {
                [$grade, $section] = explode('|', $key, 2);
                $status = $delivery[$this->sectionMapKey($grade, $section)]['notification_status'] ?? 'not_sent';
                if ($status !== 'sent') {
                    $allSent = false;
                    break;
                }
            }
            $option['list_state'] = $allSent ? 'closed' : 'active';
            $option['selectable'] = ! $allSent;
        }
        unset($option);

        usort($options, function (array $a, array $b) {
            if ($a['list_state'] !== $b['list_state']) {
                return $a['list_state'] === 'active' ? -1 : 1;
            }

            return strcmp($a['label'], $b['label']);
        });

        return $options;
    }

    /**
     * @return array<string, array{grade:string,section:string,enrolled_count:int,structure_applies:bool}>
     */
    public function feeStructureSummaryBySection(int $branchId, string $feeStructureId): array
    {
        $plugin = $this->registry->get('fees');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule) {
            return $plugin->sectionStructureSummaries($branchId, $feeStructureId);
        }

        return [];
    }

    public function academicYearNameForCampaigns(): ?string
    {
        try {
            $model = app(AcademicYearContext::class)->model(false);

            return $model?->name ? (string) $model->name : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function deliveryStatusBySection(
        string $module,
        int $branchId,
        ?string $date,
        ?int $examId = null,
        ?string $notifyMode = null,
        ?string $feeType = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
    ): array {
        $query = DB::table('notification_campaign_targets as t')
            ->join('notification_campaigns as c', 'c.id', '=', 't.campaign_id')
            ->where('c.module', $module)
            ->where('c.branch_id', $branchId)
            ->orderByDesc('c.id');

        if ($date !== null && $date !== '') {
            $query->whereDate('c.event_date', $date);
        }

        if ($examId !== null) {
            $query->where('c.exam_id', $examId);
        }

        if ($feeType !== null && $feeType !== '') {
            $query->where('c.fee_type', $feeType);
        }

        if ($feeNotifyMode !== null && $feeNotifyMode !== '') {
            $query->where('c.fee_notify_mode', $feeNotifyMode);
        }

        if ($feeStructureId !== null && $feeStructureId !== '') {
            $query->where('c.fee_structure_id', $feeStructureId);
        }

        $rows = $query->get([
            't.grade',
            't.section',
            't.status as target_status',
            't.sent_count',
            'c.id as campaign_id',
            'c.status as campaign_status',
            'c.template_map',
        ]);

        $map = [];
        foreach ($rows as $row) {
            if ($notifyMode !== null && $notifyMode !== '') {
                $templateMap = json_decode((string) ($row->template_map ?? ''), true);
                if (! is_array($templateMap) || empty($templateMap[$notifyMode])) {
                    continue;
                }
            }
            if ($module === 'fees' && $feeNotifyMode !== null && $feeNotifyMode !== '') {
                $templateMap = json_decode((string) ($row->template_map ?? ''), true);
                if (! is_array($templateMap)) {
                    continue;
                }
                if ($feeNotifyMode === 'structure' && empty($templateMap['structure'])) {
                    continue;
                }
                if ($feeNotifyMode === 'due' && empty($templateMap['due']) && empty($templateMap['overdue'])) {
                    continue;
                }
            }

            $key = $this->sectionMapKey((string) $row->grade, (string) $row->section);
            if (isset($map[$key])) {
                continue;
            }
            $map[$key] = [
                'notification_status' => $this->resolveSectionNotificationStatus(
                    (string) $row->target_status,
                    (string) $row->campaign_status,
                ),
                'campaign_id' => (int) $row->campaign_id,
                'sent_count' => (int) $row->sent_count,
            ];
        }

        return $map;
    }

    /**
     * @return array<string, array<string, array{notification_status:string,campaign_id?:int,sent_count?:int}>>
     */
    public function deliveryStatusByFeeTypeAndSectionForFeesDue(int $branchId, string $dueDate): array
    {
        $query = DB::table('notification_campaign_targets as t')
            ->join('notification_campaigns as c', 'c.id', '=', 't.campaign_id')
            ->where('c.module', 'fees')
            ->where('c.branch_id', $branchId)
            ->where('c.fee_notify_mode', 'due')
            ->whereDate('c.event_date', $dueDate)
            ->orderByDesc('c.id');

        $rows = $query->get([
            'c.fee_type',
            't.grade',
            't.section',
            't.status as target_status',
            't.sent_count',
            'c.id as campaign_id',
            'c.status as campaign_status',
            'c.template_map',
        ]);

        $map = [];
        foreach ($rows as $row) {
            $feeType = trim((string) ($row->fee_type ?? ''));
            if ($feeType === '') {
                continue;
            }
            $templateMap = json_decode((string) ($row->template_map ?? ''), true);
            if (! is_array($templateMap) || (empty($templateMap['due']) && empty($templateMap['overdue']))) {
                continue;
            }

            $sectionKey = $this->sectionMapKey((string) $row->grade, (string) $row->section);
            if (isset($map[$feeType][$sectionKey])) {
                continue;
            }
            $map[$feeType][$sectionKey] = [
                'notification_status' => $this->resolveSectionNotificationStatus(
                    (string) $row->target_status,
                    (string) $row->campaign_status,
                ),
                'campaign_id' => (int) $row->campaign_id,
                'sent_count' => (int) $row->sent_count,
            ];
        }

        return $map;
    }

    /**
     * @param  list<array{grade:string,section:string,class_name:string,student_count:int}>  $rows
     * @return list<array{grade:string,section:string,class_name:string,student_count:int,notification_status:string,campaign_id?:int,sent_count?:int}>
     */
    public function mergeDeliveryIntoEligibleRows(
        string $module,
        int $branchId,
        ?string $date,
        array $rows,
        ?int $examId = null,
        ?string $notifyMode = null,
        ?string $feeType = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
        ?array $deliveryBySection = null,
    ): array {
        $delivery = $deliveryBySection ?? $this->deliveryStatusBySection(
            $module,
            $branchId,
            $date,
            $examId,
            $notifyMode,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
        );

        return array_map(function (array $row) use ($delivery) {
            $key = $this->sectionMapKey($row['grade'], $row['section']);
            $info = $delivery[$key] ?? ['notification_status' => 'not_sent'];

            return array_merge($row, [
                'notification_status' => $info['notification_status'],
                'campaign_id' => $info['campaign_id'] ?? null,
                'sent_count' => $info['sent_count'] ?? null,
            ]);
        }, $rows);
    }

    private function resolveSectionNotificationStatus(string $targetStatus, string $campaignStatus): string
    {
        $targetStatus = strtolower($targetStatus);
        $campaignStatus = strtolower($campaignStatus);

        if ($targetStatus === 'sent' || $campaignStatus === 'sent') {
            return 'sent';
        }
        if ($targetStatus === 'partial' || $campaignStatus === 'partial') {
            return 'partial';
        }
        if ($targetStatus === 'failed' || $campaignStatus === 'failed') {
            return 'failed';
        }
        if (in_array($campaignStatus, ['materializing', 'queued', 'sending'], true)) {
            return 'sending';
        }
        if ($targetStatus === 'sending') {
            return 'sending';
        }

        return 'not_sent';
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     * @return list<array<string, mixed>>
     */
    public function resolveRecipients(
        string $module,
        int $branchId,
        ?string $eventDate,
        array $targets,
        array $templateMap,
        ?int $examId = null,
        ?string $feeType = null,
        ?string $academicYear = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
    ): array {
        $plugin = $this->registry->get($module);
        if (! $plugin) {
            return [];
        }

        $students = $this->studentsForTargets($branchId, $targets);
        if ($students->isEmpty()) {
            return [];
        }

        $examsScoped = $module === 'exams'
            && $examId !== null
            && $plugin instanceof \App\NotificationCampaigns\Modules\ExamsCampaignModule;
        $feesPlugin = $plugin instanceof \App\NotificationCampaigns\Modules\FeesCampaignModule ? $plugin : null;
        $feesDueScoped = $module === 'fees'
            && $feesPlugin !== null
            && ($feeNotifyMode === null || $feeNotifyMode === 'due')
            && $eventDate !== null
            && trim($eventDate) !== '';
        $feesStructureScoped = $module === 'fees'
            && $feesPlugin !== null
            && $feeNotifyMode === 'structure'
            && $feeStructureId !== null
            && $feeStructureId !== '';
        $examScheduleDate = $examsScoped
            ? (($eventDate !== null && trim($eventDate) !== '') ? trim($eventDate) : null)
            : null;
        $dueDate = $feesDueScoped ? trim((string) $eventDate) : null;
        $date = $examsScoped ? ($examScheduleDate ?? now()->toDateString()) : ($eventDate ?: now()->toDateString());
        if ($examsScoped) {
            $statusByUser = $plugin->classifyByUserId($branchId, $examScheduleDate, $students, $examId);
        } elseif ($feesStructureScoped) {
            $statusByUser = $feesPlugin->classifyByUserIdForStructure($students);
        } elseif ($feesDueScoped) {
            $statusByUser = $feesPlugin->classifyByUserIdForReminder(
                $branchId,
                $dueDate,
                $students,
                $feeType,
                $academicYear ?? $this->academicYearNameForCampaigns(),
            );
        } else {
            $statusByUser = $plugin->classifyByUserId($branchId, $date, $students);
        }
        $mapped = array_keys(array_filter($templateMap));

        $rows = [];
        foreach ($students as $student) {
            $userId = (int) $student->user_id;
            if ($userId <= 0) {
                continue;
            }
            $rawStatus = $statusByUser[$userId] ?? null;
            if (! $rawStatus) {
                continue;
            }
            $statusKey = $this->resolveStatusKeyForTemplateMap($rawStatus, $mapped);
            if ($statusKey === null) {
                continue;
            }
            $user = $student->user;
            $name = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '';
            $baseContext = [
                'student_name' => $name,
                'grade' => (string) $student->grade,
                'section' => (string) ($student->section ?? ''),
                'class_name' => trim($student->grade.' '.($student->section ?? '')),
                'roll_number' => (string) ($student->roll_number ?? ''),
                'father_name' => (string) ($student->father_name ?? ''),
                'mother_name' => (string) ($student->mother_name ?? ''),
                'mobile' => (string) ($user->phone ?? ''),
                'date' => Carbon::parse($date)->format('d M Y'),
                'attendance_date' => Carbon::parse($date)->format('d M Y'),
                'status' => ucfirst($statusKey),
            ];
            if ($examsScoped) {
                $context = $plugin->enrichContext($baseContext, $student, $statusKey, $examScheduleDate, $branchId, $examId);
            } elseif ($feesStructureScoped) {
                $context = $feesPlugin->enrichContextForStructure(
                    $baseContext,
                    $student,
                    $branchId,
                    (string) $feeStructureId,
                );
            } elseif ($feesDueScoped) {
                $context = $feesPlugin->enrichContextForReminder(
                    $baseContext,
                    $student,
                    $dueDate,
                    $branchId,
                    $feeType,
                    $academicYear ?? $this->academicYearNameForCampaigns(),
                );
            } else {
                $context = $plugin->enrichContext($baseContext, $student, $statusKey, $date, $branchId);
            }

            $rows[] = [
                'user_id' => $userId,
                'student_id' => $student->id,
                'student_name' => $name,
                'grade' => (string) $student->grade,
                'section' => (string) ($student->section ?? ''),
                'status_key' => $statusKey,
                'context' => $context,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     * @return list<array{status_key:string,label:string,message:string,student_name:string}>
     */
    public function previewSamples(
        string $module,
        int $branchId,
        ?string $eventDate,
        array $targets,
        array $templateMap,
        ?int $examId = null,
        ?string $feeType = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
        ?string $academicYear = null,
    ): array {
        $year = $academicYear ?? $this->academicYearNameForCampaigns();
        if ($module === 'fees') {
            $this->warmFeesReminderCache($branchId, $year);
        }

        $recipients = $this->resolveRecipients(
            $module,
            $branchId,
            $eventDate,
            $targets,
            $templateMap,
            $examId,
            $feeType,
            $year,
            $feeNotifyMode,
            $feeStructureId,
        );
        $templates = SmsTemplate::query()
            ->where('branch_id', $branchId)
            ->whereIn('id', array_values($templateMap))
            ->get()
            ->keyBy('id');

        $samples = [];
        foreach ($this->modules()[$module] ?? [] as $status) {
            $templateId = $templateMap[$status['key']] ?? null;
            if (! $templateId || ! $templates->has($templateId)) {
                continue;
            }
            $example = collect($recipients)->firstWhere('status_key', $status['key']);
            $context = $example['context'] ?? [
                'student_name' => 'Sample Student',
                'grade' => $targets[0]['grade'] ?? '',
                'section' => $targets[0]['section'] ?? '',
                'class_name' => trim(($targets[0]['grade'] ?? '').' '.($targets[0]['section'] ?? '')),
                'date' => $eventDate ? Carbon::parse($eventDate)->format('d M Y') : now()->format('d M Y'),
                'attendance_date' => $eventDate ? Carbon::parse($eventDate)->format('d M Y') : now()->format('d M Y'),
                'status' => $status['label'],
            ];
            $samples[] = [
                'status_key' => $status['key'],
                'label' => $status['label'],
                'student_name' => $example['student_name'] ?? 'Sample Student',
                'message' => $this->renderer->render((string) $templates[$templateId]->body, $context),
                'recipient_count' => collect($recipients)->where('status_key', $status['key'])->count(),
            ];
        }

        return $samples;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     */
    public function createCampaign(
        string $module,
        int $branchId,
        ?string $eventDate,
        array $targets,
        array $templateMap,
        int $createdBy,
        int $expectedRecipientCount = 0,
        ?int $examId = null,
        ?string $feeType = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
        ?string $academicYear = null,
    ): NotificationCampaign {
        return DB::transaction(function () use (
            $module,
            $branchId,
            $eventDate,
            $targets,
            $templateMap,
            $createdBy,
            $expectedRecipientCount,
            $examId,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
            $academicYear,
        ) {
            $campaign = NotificationCampaign::create([
                'module' => $module,
                'branch_id' => $branchId,
                'exam_id' => $module === 'exams' ? $examId : null,
                'fee_type' => $module === 'fees' ? $feeType : null,
                'fee_notify_mode' => $module === 'fees' ? $feeNotifyMode : null,
                'fee_structure_id' => $module === 'fees' && $feeNotifyMode === 'structure' ? $feeStructureId : null,
                'academic_year' => $academicYear,
                'event_date' => $eventDate,
                'scheduled_at' => now(),
                'status' => 'materializing',
                'template_map' => $templateMap,
                'target_count' => count($targets),
                'recipient_count' => 0,
                'expected_recipient_count' => max(0, $expectedRecipientCount),
                'materialize_target_index' => 0,
                'created_by' => $createdBy,
            ]);

            foreach ($targets as $target) {
                NotificationCampaignTarget::create([
                    'campaign_id' => $campaign->id,
                    'grade' => $target['grade'],
                    'section' => $target['section'],
                    'student_count' => 0,
                    'status' => 'pending',
                ]);
            }

            return $campaign;
        });
    }

    public function materializeAll(NotificationCampaign $campaign, int $maxChunks = 500): void
    {
        $iterations = 0;
        while ($campaign->status === 'materializing' && $iterations < $maxChunks) {
            $this->materializeNextChunk($campaign);
            $campaign->refresh();
            $iterations++;
        }
    }

    /**
     * Rebuild recipient rows and per-target student counts (e.g. campaigns stuck at 0 after async materialize).
     */
    public function rematerializeCampaign(NotificationCampaign $campaign): void
    {
        $year = $campaign->academic_year
            ? (string) $campaign->academic_year
            : $this->academicYearNameForCampaigns();

        DB::transaction(function () use ($campaign, $year) {
            $campaign->recipients()->delete();
            $campaign->targets()->update(['student_count' => 0, 'status' => 'pending']);
            $campaign->update([
                'status' => 'materializing',
                'materialize_target_index' => 0,
                'recipient_count' => 0,
                'academic_year' => $year,
            ]);
        });

        $campaign->refresh();
        $this->materializeAll($campaign);
    }

    public function materializeNextChunk(NotificationCampaign $campaign): void
    {
        if ($campaign->status !== 'materializing') {
            return;
        }

        $targets = $campaign->targets()->orderBy('id')->get();
        $index = (int) $campaign->materialize_target_index;
        if ($index >= $targets->count()) {
            $this->finishMaterializing($campaign);

            return;
        }

        $perJob = max(1, (int) config('notification_campaigns.materialize_chunk_targets', 1));
        $batch = $targets->slice($index, $perJob);
        $targetPayload = $batch->map(fn ($t) => [
            'grade' => (string) $t->grade,
            'section' => (string) $t->section,
        ])->values()->all();

        $academicYear = $campaign->academic_year
            ? (string) $campaign->academic_year
            : $this->academicYearNameForCampaigns();

        if ($campaign->module === 'fees') {
            $this->warmFeesReminderCache((int) $campaign->branch_id, $academicYear);
        }

        $recipients = $this->resolveRecipients(
            $campaign->module,
            (int) $campaign->branch_id,
            optional($campaign->event_date)->toDateString(),
            $targetPayload,
            $campaign->template_map ?? [],
            $campaign->exam_id ? (int) $campaign->exam_id : null,
            $campaign->fee_type ? (string) $campaign->fee_type : null,
            $academicYear,
            $campaign->fee_notify_mode ? (string) $campaign->fee_notify_mode : null,
            $campaign->fee_structure_id ? (string) $campaign->fee_structure_id : null,
        );

        $targetKeyToId = [];
        foreach ($batch as $target) {
            $targetKeyToId[$target->grade.'|'.$target->section] = $target->id;
        }

        $added = $this->insertRecipientRows($campaign, $recipients, $targetKeyToId);

        foreach ($batch as $target) {
            $count = collect($recipients)
                ->where('grade', $target->grade)
                ->where('section', $target->section)
                ->count();
            if ($count > 0) {
                $target->update([
                    'student_count' => $target->student_count + $count,
                ]);
            }
        }

        $newIndex = $index + $batch->count();
        $campaign->update([
            'materialize_target_index' => $newIndex,
            'recipient_count' => $campaign->recipient_count + $added,
        ]);

        if ($newIndex >= $targets->count()) {
            $this->finishMaterializing($campaign->fresh());
        }
    }

    /**
     * @return array{processed:int,sent:int,failed:int}
     */
    public function sendNextChunk(NotificationCampaign $campaign): array
    {
        if ($campaign->status === 'materializing') {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        if (! in_array($campaign->status, ['queued', 'sending', 'pending', 'partial'], true)) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $campaign->update(['status' => 'sending']);

        $limit = max(1, (int) config('notification_campaigns.send_chunk_size', 150));
        $recipients = $this->claimPendingRecipients((int) $campaign->id, $limit);
        if ($recipients->isEmpty()) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0];
        }

        $templates = SmsTemplate::query()
            ->where('branch_id', $campaign->branch_id)
            ->whereIn('id', array_values($campaign->template_map ?? []))
            ->get()
            ->keyBy('id');

        $dateLabel = optional($campaign->event_date)->format('d M Y') ?: now()->format('d M Y');
        $inboxItems = [];
        $recipientModels = [];
        $failed = 0;

        foreach ($recipients as $recipient) {
            try {
                $templateId = (int) ($campaign->template_map[$recipient->status_key] ?? 0);
                $template = $templates->get($templateId);
                if (! $template) {
                    throw new \RuntimeException('Template missing for '.$recipient->status_key);
                }

                $message = $this->renderRecipientMessage($campaign, $recipient, $template, $dateLabel);
                $title = ucfirst($campaign->module).' · '.ucfirst((string) $recipient->status_key);

                $storedContext = is_array($recipient->context)
                    ? $recipient->context
                    : (json_decode((string) $recipient->context, true) ?: []);

                $metadata = [
                    'source' => 'notification_campaign',
                    'campaign_id' => $campaign->id,
                    'recipient_id' => $recipient->id,
                    'module' => $campaign->module,
                    'status_key' => $recipient->status_key,
                    'grade' => $recipient->grade,
                    'section' => $recipient->section,
                ];
                if (! empty($storedContext['assignment_id'])) {
                    $metadata['assignment_id'] = (int) $storedContext['assignment_id'];
                }

                $inboxItems[] = [
                    'user_id' => (int) $recipient->user_id,
                    'title' => $title,
                    'message' => $message,
                    'metadata' => $metadata,
                ];
                $recipientModels[] = $recipient;
            } catch (\Throwable $e) {
                $recipient->update([
                    'delivery_status' => 'failed',
                    'error' => mb_substr($e->getMessage(), 0, 500),
                ]);
                $failed++;
            }
        }

        $sent = 0;

        if ($inboxItems !== []) {
            $batchId = (string) Str::uuid();
            try {
                $notificationIds = $this->inbox->insertCampaignNotificationBatch(
                    $inboxItems,
                    (int) $campaign->branch_id,
                    $campaign->created_by,
                    $batchId,
                );

                foreach ($recipientModels as $recipient) {
                    $notificationId = $notificationIds[$recipient->id] ?? null;
                    if (! $notificationId) {
                        $recipient->update([
                            'delivery_status' => 'failed',
                            'error' => 'Inbox row not linked',
                        ]);
                        $failed++;

                        continue;
                    }
                    $recipient->update([
                        'delivery_status' => 'sent',
                        'notification_id' => $notificationId,
                        'error' => null,
                    ]);
                    $sent++;
                }
            } catch (\Throwable $e) {
                $message = mb_substr($e->getMessage(), 0, 500);
                foreach ($recipientModels as $recipient) {
                    if ($recipient->delivery_status === 'processing') {
                        $recipient->update([
                            'delivery_status' => 'failed',
                            'error' => $message,
                        ]);
                        $failed++;
                    }
                }
            }
        }

        if ($sent > 0 || $failed > 0) {
            NotificationCampaign::query()
                ->where('id', $campaign->id)
                ->update([
                    'sent_count' => DB::raw('sent_count + '.$sent),
                    'failed_count' => DB::raw('failed_count + '.$failed),
                ]);
        }

        return [
            'processed' => $recipients->count(),
            'sent' => $sent,
            'failed' => $failed,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function progressSnapshot(NotificationCampaign $campaign): array
    {
        $materialized = (int) $campaign->recipient_count;
        $pending = $campaign->recipients()->where('delivery_status', 'pending')->count();
        $processing = $campaign->recipients()->where('delivery_status', 'processing')->count();

        return [
            'status' => $campaign->status,
            'recipient_count' => $materialized,
            'expected_recipient_count' => (int) $campaign->expected_recipient_count,
            'materialized_count' => $materialized,
            'sent_count' => (int) $campaign->sent_count,
            'failed_count' => (int) $campaign->failed_count,
            'pending_count' => $pending + $processing,
            'processing_count' => $processing,
        ];
    }

    /**
     * @return array{data:\Illuminate\Support\Collection,total:int}
     */
    public function paginateRecipients(NotificationCampaign $campaign, int $page, int $perPage, ?string $deliveryStatus = null): array
    {
        $query = $campaign->recipients()->orderBy('id');
        if ($deliveryStatus !== null && $deliveryStatus !== '') {
            $query->where('delivery_status', $deliveryStatus);
        }

        $total = (clone $query)->count();
        $rows = $query->forPage($page, $perPage)->get();

        return ['data' => $rows, 'total' => $total];
    }

    public function retryFailed(NotificationCampaign $campaign): void
    {
        $campaign->recipients()
            ->where('delivery_status', 'failed')
            ->update([
                'delivery_status' => 'pending',
                'error' => null,
            ]);

        $campaign->update(['status' => 'queued']);
    }

    public function reclaimStaleProcessing(?int $timeoutMinutes = null): int
    {
        $minutes = $timeoutMinutes ?? (int) config('notification_campaigns.processing_timeout_minutes', 15);
        $cutoff = now()->subMinutes($minutes);

        return NotificationCampaignRecipient::query()
            ->where('delivery_status', 'processing')
            ->where('updated_at', '<', $cutoff)
            ->update([
                'delivery_status' => 'pending',
                'updated_at' => now(),
            ]);
    }

    public function rollup(NotificationCampaign $campaign): void
    {
        $sent = $campaign->recipients()->where('delivery_status', 'sent')->count();
        $failed = $campaign->recipients()->where('delivery_status', 'failed')->count();
        $pending = $campaign->recipients()->whereIn('delivery_status', ['pending', 'processing'])->count();
        $status = 'sending';
        if ($pending === 0) {
            $status = $failed === 0 ? 'sent' : ($sent === 0 ? 'failed' : 'partial');
        }

        $campaign->update([
            'sent_count' => $sent,
            'failed_count' => $failed,
            'status' => $status,
        ]);

        foreach ($campaign->targets as $target) {
            $tSent = $target->recipients()->where('delivery_status', 'sent')->count();
            $tFailed = $target->recipients()->where('delivery_status', 'failed')->count();
            $tPending = $target->recipients()->whereIn('delivery_status', ['pending', 'processing'])->count();
            $targetStatus = 'pending';
            if ($tPending === 0 && ($tSent + $tFailed) > 0) {
                $targetStatus = $tFailed === 0 ? 'sent' : ($tSent === 0 ? 'failed' : 'partial');
            } elseif ($tSent > 0 || $tFailed > 0) {
                $targetStatus = 'sending';
            }
            $target->update([
                'sent_count' => $tSent,
                'failed_count' => $tFailed,
                'status' => $targetStatus,
            ]);
        }
    }

    public function markViewed(int $notificationId): void
    {
        NotificationCampaignRecipient::query()
            ->where('notification_id', $notificationId)
            ->whereNull('viewed_at')
            ->update(['viewed_at' => now()]);
    }

    private function finishMaterializing(NotificationCampaign $campaign): void
    {
        if ($campaign->recipient_count <= 0) {
            $campaign->update(['status' => 'failed']);
            $campaign->targets()->where('status', 'pending')->update(['status' => 'failed']);

            return;
        }

        $campaign->update(['status' => 'queued']);
    }

    /**
     * @param  list<string>  $mappedKeys
     */
    private function resolveStatusKeyForTemplateMap(string $statusKey, array $mappedKeys): ?string
    {
        if (in_array($statusKey, $mappedKeys, true)) {
            return $statusKey;
        }

        $fallback = match ($statusKey) {
            'overdue' => 'due',
            'due' => 'overdue',
            default => null,
        };

        if ($fallback !== null && in_array($fallback, $mappedKeys, true)) {
            return $fallback;
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $recipients
     * @param  array<string, int>  $targetKeyToId
     */
    private function insertRecipientRows(NotificationCampaign $campaign, array $recipients, array $targetKeyToId): int
    {
        if ($recipients === []) {
            return 0;
        }

        $chunkSize = max(50, (int) config('notification_campaigns.recipient_insert_chunk_size', 200));
        $inserted = 0;

        foreach (array_chunk($recipients, $chunkSize) as $chunk) {
            $now = now();
            NotificationCampaignRecipient::insert(array_map(function (array $row) use ($campaign, $targetKeyToId, $now) {
                return [
                    'campaign_id' => $campaign->id,
                    'target_id' => $targetKeyToId[$row['grade'].'|'.$row['section']] ?? null,
                    'user_id' => $row['user_id'],
                    'student_id' => $row['student_id'],
                    'student_name' => $row['student_name'],
                    'grade' => $row['grade'],
                    'section' => $row['section'],
                    'status_key' => $row['status_key'],
                    'context' => json_encode($row['context'] ?? []),
                    'delivery_status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }, $chunk));
            $inserted += count($chunk);
        }

        return $inserted;
    }

    private function claimPendingRecipients(int $campaignId, int $limit): Collection
    {
        $ids = DB::table('notification_campaign_recipients')
            ->where('campaign_id', $campaignId)
            ->where('delivery_status', 'pending')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return collect();
        }

        DB::table('notification_campaign_recipients')
            ->whereIn('id', $ids)
            ->where('delivery_status', 'pending')
            ->update([
                'delivery_status' => 'processing',
                'updated_at' => now(),
            ]);

        return NotificationCampaignRecipient::query()
            ->whereIn('id', $ids)
            ->where('delivery_status', 'processing')
            ->get();
    }

    private function renderRecipientMessage(
        NotificationCampaign $campaign,
        NotificationCampaignRecipient $recipient,
        SmsTemplate $template,
        string $dateLabel,
    ): string {
        $storedContext = is_array($recipient->context)
            ? $recipient->context
            : (json_decode((string) $recipient->context, true) ?: []);
        $defaults = [
            'student_name' => (string) $recipient->student_name,
            'grade' => (string) $recipient->grade,
            'section' => (string) $recipient->section,
            'class_name' => trim($recipient->grade.' '.$recipient->section),
            'date' => $dateLabel,
            'attendance_date' => $dateLabel,
            'status' => ucfirst((string) $recipient->status_key),
        ];

        return $this->renderer->render(
            (string) $template->body,
            array_merge($defaults, $storedContext)
        );
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     */
    private function studentsForTargets(int $branchId, array $targets)
    {
        $query = Student::query()
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->with(['user:id,first_name,last_name,phone']);

        $query->where(function ($q) use ($targets) {
            foreach ($targets as $target) {
                $q->orWhere(function ($inner) use ($target) {
                    $inner->where('grade', $target['grade'])->where('section', $target['section']);
                });
            }
        });

        return $query->get();
    }

    private function sectionMapKey(string $grade, string $section): string
    {
        return trim($grade).'|'.trim($section);
    }
}
