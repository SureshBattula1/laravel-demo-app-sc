<?php

namespace App\Services;

use App\Models\NotificationCampaign;
use App\Models\NotificationCampaignRecipient;
use App\Models\NotificationCampaignTarget;
use App\Models\SmsTemplate;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\NotificationCampaigns\NotificationCampaignModule;
use App\NotificationCampaigns\NotificationCampaignModuleRegistry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationCampaignService
{
    /** @var array{branch_name:string,school_name:string}|null */
    private ?array $branchSchoolContextCache = null;

    private ?int $branchSchoolContextBranchId = null;

    public function __construct(
        protected SmsTemplateTagRenderer $renderer,
        protected SmsTemplateTagContextFactory $tagContextFactory,
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

    public function publishedAssignmentsCount(int $branchId, string $date): int
    {
        $plugin = $this->registry->get('assignments');
        if ($plugin instanceof \App\NotificationCampaigns\Modules\AssignmentsCampaignModule) {
            return $plugin->assignmentsPublishedOnDate($branchId, $date)->count();
        }

        return 0;
    }

    public function attendanceSummaryBySection(string $module, int $branchId, string $date): array
    {
        if ($module === 'attendance') {
            $plugin = $this->registry->get('attendance');
            if ($plugin instanceof \App\NotificationCampaigns\Modules\AttendanceCampaignModule) {
                return $plugin->sectionAttendanceSummaries($branchId, $date);
            }

            return [];
        }

        if ($module === 'teacher_attendance') {
            $plugin = $this->registry->get('teacher_attendance');
            if ($plugin instanceof \App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule) {
                $summaries = $plugin->departmentAttendanceSummaries($branchId, $date);
                $out = [];
                foreach ($summaries as $deptKey => $row) {
                    $out[$deptKey.'|'.\App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule::SECTION_KEY] = [
                        'enrolled_count' => $row['enrolled_count'],
                        'marked_count' => $row['marked_count'],
                        'present' => $row['present'],
                        'absent' => $row['absent'],
                        'leave' => $row['leave'],
                    ];
                }

                return $out;
            }
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
        $map = [];

        $targetQuery = DB::table('notification_campaign_targets as t')
            ->join('notification_campaigns as c', 'c.id', '=', 't.campaign_id')
            ->where('c.module', $module)
            ->where('c.branch_id', $branchId)
            ->orderByDesc('c.id')
            ->orderByDesc('t.id');
        $this->applyDeliveryScopeToCampaignQuery(
            $targetQuery,
            $date,
            $examId,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
        );

        $rows = $targetQuery->get([
            't.grade',
            't.section',
            't.status as target_status',
            't.sent_count',
            'c.id as campaign_id',
            'c.status as campaign_status',
            'c.template_map',
        ]);

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

            $entry = [
                'notification_status' => $this->resolveSectionNotificationStatus(
                    (string) $row->target_status,
                    (string) $row->campaign_status,
                ),
                'campaign_id' => (int) $row->campaign_id,
                'sent_count' => (int) $row->sent_count,
            ];
            $this->putSectionDeliveryEntry($map, (string) $row->grade, (string) $row->section, $entry);
        }

        $recipientQuery = DB::table('notification_campaign_recipients as r')
            ->join('notification_campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->where('c.module', $module)
            ->where('c.branch_id', $branchId)
            ->whereNotNull('r.grade')
            ->where('r.grade', '!=', '')
            ->whereNotNull('r.section')
            ->where('r.section', '!=', '')
            ->orderByDesc('r.id');
        $this->applyDeliveryScopeToCampaignQuery(
            $recipientQuery,
            $date,
            $examId,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
            'c',
        );

        $recipientRows = $recipientQuery->get([
            'r.grade',
            'r.section',
            'r.delivery_status',
            'c.id as campaign_id',
            'c.status as campaign_status',
            'c.template_map',
        ]);

        foreach ($recipientRows as $row) {
            $grade = trim((string) ($row->grade ?? ''));
            $section = trim((string) ($row->section ?? ''));
            if ($grade === '' || $section === '' || strcasecmp($grade, 'Staff') === 0) {
                continue;
            }
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

            $status = $this->resolveSectionNotificationStatus(
                (string) $row->delivery_status,
                (string) $row->campaign_status,
            );
            if (! $this->notificationStatusBlocksResend($status)) {
                continue;
            }
            $this->putSectionDeliveryEntry($map, (string) $row->grade, (string) $row->section, [
                'notification_status' => $status,
                'campaign_id' => (int) $row->campaign_id,
                'sent_count' => 0,
            ]);
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

        $assignmentSummary = ($module === 'assignments' && $date !== null && $date !== '')
            ? $this->assignmentSummaryBySection($branchId, $date)
            : [];

        return array_map(function (array $row) use ($delivery, $module, $assignmentSummary) {
            $info = $this->resolveDeliveryForSection($delivery, $row['grade'], $row['section'])
                ?? ['notification_status' => 'not_sent'];

            $merged = array_merge($row, [
                'notification_status' => $info['notification_status'],
                'campaign_id' => $info['campaign_id'] ?? null,
                'sent_count' => $info['sent_count'] ?? null,
            ]);

            if ($module === 'assignments') {
                $key = $this->sectionMapKey($row['grade'], $row['section']);
                $summary = $assignmentSummary[$key] ?? null;
                if ($summary !== null) {
                    $merged['assignment_count'] = $summary['assignment_count'];
                    $merged['subject_names'] = $summary['subject_names'];
                    $merged['assignment_has_new'] = $summary['has_new'];
                }
            }

            return $merged;
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
        if ($targetStatus === 'pending' && in_array($campaignStatus, ['sent', 'partial', 'sending'], true)) {
            return $campaignStatus;
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
        ?array $teacherUserIds = null,
    ): array {
        $plugin = $this->registry->get($module);
        if (! $plugin) {
            return [];
        }

        if ($module === 'teacher_attendance') {
            return $this->resolveTeacherAttendanceRecipients(
                $branchId,
                $eventDate,
                $targets,
                $templateMap,
                $plugin,
                $teacherUserIds,
            );
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

        $assignmentsPublished = null;
        $notifiedAssignmentIdsByUser = [];
        if ($module === 'assignments' && $plugin instanceof \App\NotificationCampaigns\Modules\AssignmentsCampaignModule) {
            $assignmentsPublished = $plugin->assignmentsPublishedOnDate($branchId, $date);
            $notifiedAssignmentIdsByUser = $this->notifiedAssignmentIdsByUser($branchId, $date);
        }

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
            if ($module === 'assignments' && $assignmentsPublished !== null) {
                if (! $this->studentHasAssignmentDelta(
                    $assignmentsPublished,
                    $notifiedAssignmentIdsByUser,
                    $date,
                    $student,
                )) {
                    continue;
                }
            }
            $user = $student->user;
            $name = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '';
            $baseContext = array_merge(
                $this->tagContextFactory->buildForStudent($student, $branchId),
                [
                    'student_name' => $name,
                    'class_name' => trim($student->grade.' '.($student->section ?? '')),
                    'date' => Carbon::parse($date)->format('d M Y'),
                    'attendance_date' => Carbon::parse($date)->format('d M Y'),
                    'status' => ucfirst($statusKey),
                ]
            );
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
        ?array $staffUserIds = null,
        ?int $staffTemplateId = null,
        ?array $teacherUserIds = null,
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
            $teacherUserIds,
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

        $staffIds = $staffUserIds ?? [];
        if ($staffIds !== [] && $staffTemplateId) {
            $staffTemplate = SmsTemplate::query()
                ->where('branch_id', $branchId)
                ->where('id', $staffTemplateId)
                ->where('is_active', true)
                ->first();
            $staffRows = $this->resolveStaffRecipients($branchId, $staffIds, $module, $eventDate);
            if ($staffTemplate && $staffRows !== []) {
                $example = $staffRows[0];
                $context = $example['context'] ?? [];
                $samples[] = [
                    'status_key' => self::STAFF_STATUS_KEY,
                    'label' => 'Staff',
                    'student_name' => $example['student_name'] ?? 'Sample Staff',
                    'message' => $this->renderer->render((string) $staffTemplate->body, $context),
                    'recipient_count' => count($staffRows),
                ];
            }
        }

        return $samples;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     */
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function syncInboxAttachments(NotificationCampaign $campaign, array $items): void
    {
        if ($items === []) {
            return;
        }
        $this->inbox->syncCampaignAttachments((int) $campaign->id, $items);
    }

    public function customCampaignScopeLabel(NotificationCampaign $campaign): string
    {
        return $this->campaignListScopeLabel($campaign);
    }

    public function campaignListScopeLabel(NotificationCampaign $campaign): string
    {
        $campaign->loadMissing('targets');
        $sectionLabels = $this->customCampaignSectionLabels($campaign);
        $staffIds = is_array($campaign->staff_user_ids) ? $campaign->staff_user_ids : [];
        $staff = count($staffIds);
        $parts = [];
        if ($sectionLabels !== []) {
            if (count($sectionLabels) <= 4) {
                $parts[] = implode('; ', $sectionLabels);
            } else {
                $preview = implode('; ', array_slice($sectionLabels, 0, 3));
                $parts[] = $preview.'; +'.(count($sectionLabels) - 3).' more';
            }
        }
        if ($staff > 0) {
            $parts[] = $staff === 1 ? '1 branch team member' : $staff.' branch team';
        }

        if ($parts === []) {
            return $campaign->module === 'custom' ? 'Custom notification' : 'Notification campaign';
        }

        return implode(' · ', $parts);
    }

    public function campaignListStudentCount(NotificationCampaign $campaign): int
    {
        $campaign->loadMissing('targets');
        $fromTargets = (int) $campaign->targets->sum('student_count');
        if ($fromTargets > 0) {
            return $fromTargets;
        }

        return (int) ($campaign->recipient_count ?? 0);
    }

    /**
     * @return list<string>
     */
    public function customCampaignSectionLabels(NotificationCampaign $campaign): array
    {
        $campaign->loadMissing('targets');
        $gradeLabels = DB::table('grades')->pluck('label', 'value');
        $labels = [];
        foreach ($campaign->targets as $target) {
            $className = (string) ($gradeLabels[$target->grade] ?? ('Grade '.$target->grade));
            $labels[] = trim($className.($target->section ? ' · Section '.$target->section : ''));
        }

        return array_values(array_unique(array_filter($labels)));
    }

    public function campaignMatchesTargetFilters(
        NotificationCampaign $campaign,
        string $gradeFilter,
        string $sectionFilter,
    ): bool {
        if ($gradeFilter === '' && $sectionFilter === '') {
            return true;
        }

        $campaign->loadMissing('targets');
        foreach ($campaign->targets as $target) {
            if ($gradeFilter !== '' && (string) $target->grade !== $gradeFilter) {
                continue;
            }
            if ($sectionFilter !== '' && strcasecmp((string) $target->section, $sectionFilter) !== 0) {
                continue;
            }

            return true;
        }

        return false;
    }

    public function customCampaignListStatus(NotificationCampaign $campaign): string
    {
        $status = strtolower((string) $campaign->status);

        return match ($status) {
            'sent' => 'sent',
            'failed' => 'failed',
            'partial' => 'partial',
            'sending' => 'sending',
            'materializing', 'queued' => 'materializing',
            default => 'pending',
        };
    }

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
        ?array $staffUserIds = null,
        ?int $staffTemplateId = null,
        ?array $teacherUserIds = null,
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
            $staffUserIds,
            $staffTemplateId,
            $teacherUserIds,
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
                'staff_user_ids' => $staffUserIds ?: null,
                'staff_template_id' => $staffTemplateId,
                'teacher_user_ids' => $teacherUserIds ?: null,
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
            if (
                $campaign->module === 'teacher_attendance'
                && $index === 0
                && $targets->isEmpty()
            ) {
                $teacherIds = is_array($campaign->teacher_user_ids) ? $campaign->teacher_user_ids : [];
                if ($teacherIds !== []) {
                    $academicYear = $campaign->academic_year
                        ? (string) $campaign->academic_year
                        : $this->academicYearNameForCampaigns();
                    $recipients = $this->resolveRecipients(
                        'teacher_attendance',
                        (int) $campaign->branch_id,
                        optional($campaign->event_date)->toDateString(),
                        [],
                        $campaign->template_map ?? [],
                        null,
                        null,
                        $academicYear,
                        null,
                        null,
                        $teacherIds,
                    );
                    $added = $this->insertRecipientRows($campaign, $recipients, []);
                    $campaign->update([
                        'materialize_target_index' => 1,
                        'recipient_count' => $campaign->recipient_count + $added,
                    ]);
                }
            }
            $this->materializeStaffIfNeeded($campaign);
            $this->finishMaterializing($campaign->fresh());

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
            $fresh = $campaign->fresh();
            $this->materializeStaffIfNeeded($fresh);
            $this->finishMaterializing($fresh->fresh());
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

        $templateIds = array_values($campaign->template_map ?? []);
        if ($campaign->staff_template_id) {
            $templateIds[] = (int) $campaign->staff_template_id;
        }
        $templates = SmsTemplate::query()
            ->where('branch_id', $campaign->branch_id)
            ->whereIn('id', array_unique($templateIds))
            ->get()
            ->keyBy('id');

        $dateLabel = optional($campaign->event_date)->format('d M Y') ?: now()->format('d M Y');
        $inboxItems = [];
        $recipientModels = [];
        $failed = 0;

        foreach ($recipients as $recipient) {
            try {
                if ($recipient->status_key === self::STAFF_STATUS_KEY) {
                    $templateId = (int) ($campaign->staff_template_id ?? 0);
                } else {
                    $templateId = (int) ($campaign->template_map[$recipient->status_key] ?? 0);
                }
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
    /**
     * @param  array{
     *   delivery_status?:?string,
     *   search?:?string,
     *   grade?:?string,
     *   section?:?string,
     *   status_key?:?string,
     *   viewed?:?string,
     *   liked?:?string,
     *   audience?:?string,
     *   team_role?:?string
     * }  $filters
     * @return array{data:\Illuminate\Support\Collection,total:int}
     */
    public function paginateRecipients(
        NotificationCampaign $campaign,
        int $page,
        int $perPage,
        array $filters = [],
    ): array {
        $query = $campaign->recipients()->orderBy('id');

        $deliveryStatus = trim((string) ($filters['delivery_status'] ?? ''));
        if ($deliveryStatus !== '') {
            $query->where('delivery_status', $deliveryStatus);
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('student_name', 'like', $like)
                    ->orWhere('grade', 'like', $like)
                    ->orWhere('section', 'like', $like)
                    ->orWhere('status_key', 'like', $like)
                    ->orWhere('delivery_status', 'like', $like);
            });
        }

        $grade = trim((string) ($filters['grade'] ?? ''));
        if ($grade !== '') {
            $query->where('grade', $grade);
        }

        $section = trim((string) ($filters['section'] ?? ''));
        if ($section !== '') {
            $query->where('section', $section);
        }

        $statusKey = trim((string) ($filters['status_key'] ?? ''));
        if ($statusKey !== '') {
            $query->where('status_key', $statusKey);
        }

        $viewed = trim((string) ($filters['viewed'] ?? ''));
        if ($viewed === '1') {
            $query->whereNotNull('viewed_at');
        } elseif ($viewed === '0') {
            $query->whereNull('viewed_at');
        }

        $liked = trim((string) ($filters['liked'] ?? ''));
        if ($liked === '1') {
            $query->whereNotNull('liked_at');
        } elseif ($liked === '0') {
            $query->whereNull('liked_at');
        }

        $audience = strtolower(trim((string) ($filters['audience'] ?? '')));
        if ($audience === 'student') {
            $query->whereNotNull('student_id');
        } elseif ($audience === 'team') {
            $query->whereNull('student_id');
        }

        $teamRole = trim((string) ($filters['team_role'] ?? ''));
        if ($teamRole !== '') {
            $query->where('grade', 'Staff')->where('section', $teamRole);
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

        $existingUserIds = NotificationCampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->pluck('user_id')
            ->map(fn ($id) => (int) $id)
            ->flip()
            ->all();

        $deduped = [];
        foreach ($recipients as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId <= 0 || isset($existingUserIds[$userId])) {
                continue;
            }
            $existingUserIds[$userId] = true;
            $deduped[] = $row;
        }
        if ($deduped === []) {
            return 0;
        }

        $chunkSize = max(50, (int) config('notification_campaigns.recipient_insert_chunk_size', 200));
        $inserted = 0;

        foreach (array_chunk($deduped, $chunkSize) as $chunk) {
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
        $defaults = array_merge(
            SmsTemplateTagContextFactory::emptyTagContext(),
            [
                'student_name' => (string) $recipient->student_name,
                'teacher_name' => (string) $recipient->student_name,
                'grade' => (string) $recipient->grade,
                'section' => (string) $recipient->section,
                'class_name' => trim($recipient->grade.' '.$recipient->section),
                'date' => $dateLabel,
                'attendance_date' => $dateLabel,
                'status' => ucfirst((string) $recipient->status_key),
            ]
        );

        return $this->renderer->render(
            (string) $template->body,
            array_merge($defaults, $storedContext)
        );
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @param  array<string, int>  $templateMap
     * @return list<array<string, mixed>>
     */
    private function resolveTeacherAttendanceRecipients(
        int $branchId,
        ?string $eventDate,
        array $targets,
        array $templateMap,
        \App\NotificationCampaigns\NotificationCampaignModule $plugin,
        ?array $teacherUserIds = null,
    ): array {
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule) {
            return [];
        }

        $date = ($eventDate !== null && trim($eventDate) !== '') ? trim($eventDate) : now()->toDateString();
        $mapped = array_keys(array_filter($templateMap));

        if ($teacherUserIds !== null && $teacherUserIds !== []) {
            $userIds = array_values(array_unique(array_map('intval', $teacherUserIds)));
            $users = User::query()
                ->whereIn('id', $userIds)
                ->where('is_active', 1)
                ->whereNull('deleted_at')
                ->get(['id', 'first_name', 'last_name', 'phone', 'role']);
            $teacherByUser = Teacher::query()
                ->whereIn('user_id', $userIds)
                ->with(['user:id,first_name,last_name,phone,role', 'department:id,name'])
                ->get()
                ->keyBy('user_id');
            $statusByUser = $plugin->classifyTeacherUserIds($branchId, $date, $userIds);
            $rows = [];
            foreach ($users as $user) {
                $userId = (int) $user->id;
                $rawStatus = $statusByUser[$userId] ?? null;
                if (! $rawStatus) {
                    continue;
                }
                $statusKey = $this->resolveStatusKeyForTemplateMap($rawStatus, $mapped);
                if ($statusKey === null) {
                    continue;
                }
                $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
                $teacher = $teacherByUser->get($userId);
                $subtitle = $teacher
                    ? trim((string) ($teacher->designation ?: $teacher->employee_id ?: 'Teacher'))
                    : (string) $user->role;
                $baseContext = array_merge(
                    $this->tagContextFactory->buildForStaffUser($user, $teacher, $branchId),
                    [
                        'student_name' => $name,
                        'teacher_name' => $name,
                        'grade' => 'Staff',
                        'section' => (string) $user->role,
                        'class_name' => $subtitle,
                        'date' => Carbon::parse($date)->format('d M Y'),
                        'attendance_date' => Carbon::parse($date)->format('d M Y'),
                        'status' => ucfirst($statusKey),
                    ]
                );
                $context = $plugin->enrichContext($baseContext, $user, $statusKey, $date, $branchId);

                $rows[] = [
                    'user_id' => $userId,
                    'student_id' => null,
                    'student_name' => $name,
                    'grade' => 'Staff',
                    'section' => (string) $user->role,
                    'status_key' => $statusKey,
                    'context' => $context,
                ];
            }

            return $rows;
        }

        $teachers = $this->teachersForTargets($branchId, $targets);
        if ($teachers->isEmpty()) {
            return [];
        }

        $userIds = $teachers->pluck('user_id')->filter()->map(fn ($id) => (int) $id)->all();
        $statusByUser = $plugin->classifyTeacherUserIds($branchId, $date, $userIds);

        $rows = [];
        foreach ($teachers as $teacher) {
            $userId = (int) $teacher->user_id;
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
            $user = $teacher->user;
            $name = $user ? trim(($user->first_name ?? '').' '.($user->last_name ?? '')) : '';
            $deptKey = (string) (int) ($teacher->department_id ?? 0);
            $baseContext = array_merge(
                $this->tagContextFactory->buildForTeacher($teacher, $branchId),
                [
                    'student_name' => $name,
                    'teacher_name' => $name,
                    'grade' => $deptKey,
                    'section' => \App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule::SECTION_KEY,
                    'class_name' => (string) ($teacher->designation ?? 'Teacher'),
                    'date' => Carbon::parse($date)->format('d M Y'),
                    'attendance_date' => Carbon::parse($date)->format('d M Y'),
                    'status' => ucfirst($statusKey),
                ]
            );
            $context = $plugin->enrichContext($baseContext, $teacher, $statusKey, $date, $branchId);

            $rows[] = [
                'user_id' => $userId,
                'student_id' => null,
                'student_name' => $name,
                'grade' => $deptKey,
                'section' => \App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule::SECTION_KEY,
                'status_key' => $statusKey,
                'context' => $context,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @return \Illuminate\Support\Collection<int, Teacher>
     */
    private function teachersForTargets(int $branchId, array $targets)
    {
        $deptKeys = [];
        foreach ($targets as $target) {
            if (($target['section'] ?? '') !== \App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule::SECTION_KEY) {
                continue;
            }
            $deptKeys[] = (int) $target['grade'];
        }
        $deptKeys = array_values(array_unique($deptKeys));
        if ($deptKeys === []) {
            return collect();
        }

        $query = Teacher::query()
            ->where('branch_id', $branchId)
            ->where('teacher_status', 'Active')
            ->whereNotNull('user_id')
            ->with(['user:id,first_name,last_name,phone,role', 'department:id,name']);

        $query->where(function ($q) use ($deptKeys) {
            foreach ($deptKeys as $deptId) {
                $q->orWhere(function ($inner) use ($deptId) {
                    if ($deptId === 0) {
                        $inner->whereNull('department_id');
                    } else {
                        $inner->where('department_id', $deptId);
                    }
                });
            }
        });

        return $query->get();
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

    /**
     * @return list<string>
     */
    private function sectionLookupKeys(string $grade, string $section): array
    {
        $grade = trim($grade);
        $section = trim($section);
        $keys = [$this->sectionMapKey($grade, $section)];
        if (preg_match('/^grade\s*(.+)$/i', $grade, $match)) {
            $keys[] = $this->sectionMapKey(trim($match[1]), $section);
        } elseif (preg_match('/^\d+(\.\d+)?$/', $grade)) {
            $keys[] = $this->sectionMapKey('Grade '.$grade, $section);
        }

        return array_values(array_unique($keys));
    }

    /**
     * @param  array<string, array{notification_status:string,campaign_id?:int,sent_count?:int}>  $map
     * @param  array{notification_status:string,campaign_id?:int,sent_count?:int}  $entry
     */
    private function putSectionDeliveryEntry(array &$map, string $grade, string $section, array $entry): void
    {
        foreach ($this->sectionLookupKeys($grade, $section) as $key) {
            if (! isset($map[$key])) {
                $map[$key] = $entry;

                continue;
            }
            $existing = $map[$key]['notification_status'] ?? 'not_sent';
            $incoming = $entry['notification_status'] ?? 'not_sent';
            if (! $this->notificationStatusBlocksResend($existing) && $this->notificationStatusBlocksResend($incoming)) {
                $map[$key] = $entry;
            }
        }
    }

    /**
     * @param  array<string, array{notification_status:string,campaign_id?:int,sent_count?:int}>  $delivery
     * @return array{notification_status:string,campaign_id?:int,sent_count?:int}|null
     */
    public function resolveDeliveryForSection(array $delivery, string $grade, string $section): ?array
    {
        foreach ($this->sectionLookupKeys($grade, $section) as $key) {
            if (isset($delivery[$key])) {
                return $delivery[$key];
            }
        }

        $sectionName = trim($section);
        $wantGrade = trim($grade);
        if (preg_match('/^grade\s*(.+)$/i', $wantGrade, $match)) {
            $wantGrade = trim($match[1]);
        }

        foreach ($delivery as $key => $value) {
            $parts = explode('|', (string) $key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            $g = trim($parts[0]);
            $s = trim($parts[1]);
            if ($s !== $sectionName) {
                continue;
            }
            $gNorm = preg_match('/^grade\s*(.+)$/i', $g, $m) ? trim($m[1]) : $g;
            if (strcasecmp($gNorm, $wantGrade) === 0) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $query
     */
    private function applyDeliveryScopeToCampaignQuery(
        $query,
        ?string $date,
        ?int $examId,
        ?string $feeType,
        ?string $feeNotifyMode,
        ?string $feeStructureId,
        string $campaignAlias = 'c',
    ): void {
        if ($date !== null && $date !== '') {
            $query->whereDate("{$campaignAlias}.event_date", $date);
        }
        if ($examId !== null) {
            $query->where("{$campaignAlias}.exam_id", $examId);
        }
        if ($feeType !== null && $feeType !== '') {
            $query->where("{$campaignAlias}.fee_type", $feeType);
        }
        if ($feeNotifyMode !== null && $feeNotifyMode !== '') {
            $query->where("{$campaignAlias}.fee_notify_mode", $feeNotifyMode);
        }
        if ($feeStructureId !== null && $feeStructureId !== '') {
            $query->where("{$campaignAlias}.fee_structure_id", $feeStructureId);
        }
    }

    public const STAFF_STATUS_KEY = 'staff';

    /** @var list<string> */
    public const BRANCH_TEAM_ROLE_KEYS = ['teachers', 'admins', 'staff', 'accounts'];

    /**
     * User IDs in a branch team role group (teachers, staff, accounts, admins).
     *
     * @return list<int>
     */
    public function staffUserIdsForTeamRole(int $branchId, string $teamRole): array
    {
        $teamRole = strtolower(trim($teamRole));
        if (! in_array($teamRole, self::BRANCH_TEAM_ROLE_KEYS, true)) {
            return [];
        }

        foreach ($this->staffRecipientOptions($branchId)['groups'] as $group) {
            if ($group['key'] !== $teamRole) {
                continue;
            }

            return array_values(array_unique(array_map(
                fn (array $person) => (int) $person['user_id'],
                $group['people']
            )));
        }

        return [];
    }

    /**
     * Hub list filters: branch team included / role notified on the campaign.
     */
    public function campaignMatchesTeamListFilters(
        NotificationCampaign $campaign,
        string $includesTeam,
        string $teamRole,
    ): bool {
        $staffIds = is_array($campaign->staff_user_ids)
            ? array_values(array_unique(array_map('intval', $campaign->staff_user_ids)))
            : [];

        if ($includesTeam === '1' && $staffIds === []) {
            return false;
        }
        if ($includesTeam === '0' && $staffIds !== []) {
            return false;
        }

        if ($teamRole === '') {
            return true;
        }

        if ($staffIds === []) {
            return false;
        }

        $roleUserIds = $this->staffUserIdsForTeamRole((int) $campaign->branch_id, $teamRole);
        if ($roleUserIds === []) {
            return false;
        }

        return count(array_intersect($staffIds, $roleUserIds)) > 0;
    }

    /**
     * @return array{groups: list<array{key:string,label:string,people:list<array{user_id:int,name:string,subtitle:string,attendance_marked:bool,present:int,absent:int,leave:int}>}>}
     */
    public function staffRecipientOptions(int $branchId, ?string $date = null): array
    {
        $schoolId = (int) (DB::table('branches')->where('id', $branchId)->value('school_id') ?? 0);
        $resolvedDate = ($date !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date))
            ? $date
            : now()->toDateString();
        $teacherAttendanceDate = $this->resolveEventDateForModule('teacher_attendance', $resolvedDate);

        $teachers = Teacher::query()
            ->where('branch_id', $branchId)
            ->where('teacher_status', 'Active')
            ->with(['user:id,first_name,last_name,phone,role,is_active'])
            ->orderBy('id')
            ->get()
            ->filter(fn (Teacher $t) => $t->user && $t->user->is_active)
            ->map(function (Teacher $t) {
                $u = $t->user;

                return [
                    'user_id' => (int) $u->id,
                    'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                    'subtitle' => trim((string) ($t->designation ?: $t->employee_id ?: 'Teacher')),
                ];
            })
            ->values()
            ->all();

        $adminsQuery = User::query()
            ->whereIn('role', ['SuperAdmin', 'BranchAdmin'])
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->where(function ($q) use ($branchId, $schoolId) {
                $q->where('branch_id', $branchId);
                if ($schoolId > 0 && Schema::hasColumn('users', 'school_id')) {
                    $q->orWhere(function ($inner) use ($schoolId) {
                        $inner->where('role', 'SuperAdmin')->where('school_id', $schoolId);
                    });
                }
            });

        $admins = $adminsQuery
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'role', 'phone'])
            ->map(fn (User $u) => [
                'user_id' => (int) $u->id,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'subtitle' => (string) $u->role,
            ])
            ->values()
            ->all();

        $staff = User::query()
            ->where('branch_id', $branchId)
            ->where('role', 'Staff')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->map(fn (User $u) => [
                'user_id' => (int) $u->id,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'subtitle' => 'Staff',
            ])
            ->values()
            ->all();

        $accounts = User::query()
            ->where('branch_id', $branchId)
            ->where('role', 'Accountant')
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'phone'])
            ->map(fn (User $u) => [
                'user_id' => (int) $u->id,
                'name' => trim(($u->first_name ?? '').' '.($u->last_name ?? '')),
                'subtitle' => 'Accountant',
            ])
            ->values()
            ->all();

        $groups = [
            ['key' => 'teachers', 'label' => 'Teachers', 'people' => $teachers],
            ['key' => 'admins', 'label' => 'Admins & super admins', 'people' => $admins],
            ['key' => 'staff', 'label' => 'Staff', 'people' => $staff],
            ['key' => 'accounts', 'label' => 'Accounts', 'people' => $accounts],
        ];

        $allUserIds = [];
        foreach ($groups as $group) {
            foreach ($group['people'] as $person) {
                $allUserIds[] = (int) $person['user_id'];
            }
        }
        $allUserIds = array_values(array_unique($allUserIds));

        $plugin = $this->registry->get('teacher_attendance');
        $statusByUser = ($plugin instanceof \App\NotificationCampaigns\Modules\TeacherAttendanceCampaignModule)
            ? $plugin->classifyTeacherUserIds($branchId, $teacherAttendanceDate, $allUserIds)
            : [];

        foreach ($groups as $gi => $group) {
            $people = [];
            foreach ($group['people'] as $person) {
                $uid = (int) $person['user_id'];
                $status = $statusByUser[$uid] ?? null;
                $people[] = array_merge($person, [
                    'attendance_marked' => $status !== null,
                    'present' => $status === 'present' ? 1 : 0,
                    'absent' => $status === 'absent' ? 1 : 0,
                    'leave' => $status === 'leave' ? 1 : 0,
                ]);
            }
            $groups[$gi]['people'] = $people;
        }

        return [
            'groups' => $groups,
            'event_date' => $resolvedDate,
        ];
    }

    /**
     * @param  list<array{key:string,label:string,people:list<array<string,mixed>>}>  $groups
     * @param  array<int, array{notification_status:string,campaign_id?:int,sent_count?:int}>  $deliveryByUser
     * @return list<array{key:string,label:string,people:list<array<string,mixed>>}>
     */
    public function applyStaffDeliveryByUser(array $groups, array $deliveryByUser): array
    {
        foreach ($groups as $gi => $group) {
            $people = [];
            foreach ($group['people'] as $person) {
                $userId = (int) ($person['user_id'] ?? 0);
                $status = $deliveryByUser[$userId]['notification_status'] ?? 'not_sent';
                $people[] = array_merge($person, [
                    'notification_status' => $status,
                ]);
            }
            $groups[$gi]['people'] = $people;
        }

        return $groups;
    }

    /**
     * Latest campaign notify state per user (teacher attendance campaigns).
     *
     * @return array<int, array{notification_status:string,campaign_id?:int,sent_count?:int}>
     */
    public function deliveryStatusByUserId(string $module, int $branchId, ?string $date): array
    {
        $query = DB::table('notification_campaign_recipients as r')
            ->join('notification_campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->where('c.module', $module)
            ->where('c.branch_id', $branchId)
            ->orderByDesc('r.id');

        if ($date !== null && $date !== '') {
            $query->whereDate('c.event_date', $date);
        }

        $rows = $query->get([
            'r.user_id',
            'r.delivery_status',
            'c.id as campaign_id',
            'c.status as campaign_status',
        ]);

        $map = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            if ($userId <= 0 || isset($map[$userId])) {
                continue;
            }
            $map[$userId] = [
                'notification_status' => $this->resolveSectionNotificationStatus(
                    (string) $row->delivery_status,
                    (string) $row->campaign_status,
                ),
                'campaign_id' => (int) $row->campaign_id,
            ];
        }

        if ($module === 'teacher_attendance') {
            $campaignQuery = DB::table('notification_campaigns')
                ->where('module', $module)
                ->where('branch_id', $branchId)
                ->whereNotNull('teacher_user_ids')
                ->orderByDesc('id');

            if ($date !== null && $date !== '') {
                $campaignQuery->whereDate('event_date', $date);
            }

            foreach ($campaignQuery->get(['id', 'status', 'teacher_user_ids']) as $campaign) {
                $campaignStatus = $this->resolveSectionNotificationStatus(
                    'pending',
                    (string) $campaign->status,
                );
                if (! $this->notificationStatusBlocksResend($campaignStatus)) {
                    continue;
                }
                $ids = json_decode((string) ($campaign->teacher_user_ids ?? '[]'), true);
                if (! is_array($ids)) {
                    continue;
                }
                foreach ($ids as $rawUserId) {
                    $userId = (int) $rawUserId;
                    if ($userId <= 0 || isset($map[$userId])) {
                        continue;
                    }
                    $map[$userId] = [
                        'notification_status' => $campaignStatus,
                        'campaign_id' => (int) $campaign->id,
                    ];
                }
            }
        }

        return $map;
    }

    public function notificationStatusBlocksResend(string $status): bool
    {
        $status = strtolower(trim($status));

        return in_array($status, ['sent', 'sending', 'partial'], true);
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int> User ids that already have a blocking notification for this date.
     */
    public function blockedTeacherUserIdsForDate(int $branchId, string $date, array $userIds): array
    {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn ($id) => $id > 0)));
        if ($userIds === []) {
            return [];
        }

        $delivery = $this->deliveryStatusByUserId('teacher_attendance', $branchId, $date);
        $blocked = [];
        foreach ($userIds as $userId) {
            $status = $delivery[$userId]['notification_status'] ?? 'not_sent';
            if ($this->notificationStatusBlocksResend($status)) {
                $blocked[] = $userId;
            }
        }

        return $blocked;
    }

    /**
     * @return array<int, list<int>>
     */
    public function notifiedAssignmentIdsByUser(int $branchId, string $eventDate): array
    {
        $rows = DB::table('notification_campaign_recipients as r')
            ->join('notification_campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->where('c.module', 'assignments')
            ->where('c.branch_id', $branchId)
            ->whereDate('c.event_date', $eventDate)
            ->where('r.delivery_status', 'sent')
            ->orderByDesc('r.id')
            ->get(['r.user_id', 'r.context']);

        $map = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            if ($userId <= 0) {
                continue;
            }
            $context = is_string($row->context)
                ? json_decode($row->context, true)
                : (array) ($row->context ?? []);
            if (! is_array($context)) {
                $context = [];
            }
            $ids = [];
            if (! empty($context['assignment_ids']) && is_array($context['assignment_ids'])) {
                foreach ($context['assignment_ids'] as $id) {
                    $id = (int) $id;
                    if ($id > 0) {
                        $ids[$id] = true;
                    }
                }
            }
            if ($ids === [] && ! empty($context['assignment_id'])) {
                $id = (int) $context['assignment_id'];
                if ($id > 0) {
                    $ids[$id] = true;
                }
            }
            if ($ids === []) {
                continue;
            }
            if (! isset($map[$userId])) {
                $map[$userId] = [];
            }
            $map[$userId] = array_values(array_unique(array_merge($map[$userId], array_keys($ids))));
        }

        return $map;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\Assignment>  $assignmentsPublished
     * @param  array<int, list<int>>  $notifiedByUser
     */
    public function studentHasAssignmentDelta(
        Collection $assignmentsPublished,
        array $notifiedByUser,
        string $date,
        Student $student,
    ): bool {
        $forStudent = \App\NotificationCampaigns\Modules\AssignmentsCampaignModule::assignmentsForStudent(
            $assignmentsPublished,
            $date,
            (string) $student->grade,
            (string) ($student->section ?? ''),
        );
        $current = $forStudent
            ->map(fn ($a) => (int) $a->id)
            ->filter(fn (int $id) => $id > 0)
            ->values()
            ->all();
        if ($current === []) {
            return false;
        }
        $userId = (int) $student->user_id;
        $notified = $notifiedByUser[$userId] ?? [];

        return \App\NotificationCampaigns\Modules\AssignmentsCampaignModule::hasNewAssignmentIds($current, $notified);
    }

    public function sectionHasAssignmentDelta(int $branchId, string $date, string $grade, string $section): bool
    {
        $plugin = $this->registry->get('assignments');
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\AssignmentsCampaignModule) {
            return false;
        }
        $published = $plugin->assignmentsPublishedOnDate($branchId, $date);
        if ($published->isEmpty()) {
            return false;
        }
        $notifiedByUser = $this->notifiedAssignmentIdsByUser($branchId, $date);
        $students = Student::query()
            ->where('branch_id', $branchId)
            ->where('grade', $grade)
            ->where('section', $section)
            ->whereNotNull('user_id')
            ->get();

        foreach ($students as $student) {
            if ($this->studentHasAssignmentDelta($published, $notifiedByUser, $date, $student)) {
                return true;
            }
        }

        return false;
    }

    public function assignmentSectionHasInFlightSend(int $branchId, string $date, string $grade, string $section): bool
    {
        return DB::table('notification_campaign_recipients as r')
            ->join('notification_campaigns as c', 'c.id', '=', 'r.campaign_id')
            ->where('c.module', 'assignments')
            ->where('c.branch_id', $branchId)
            ->whereDate('c.event_date', $date)
            ->where('r.grade', $grade)
            ->where('r.section', $section)
            ->whereIn('r.delivery_status', ['pending', 'processing'])
            ->exists();
    }

    /**
     * @return array<string, array{notification_status:string,campaign_id?:int,sent_count?:int}>
     */
    public function assignmentDeliveryStatusBySection(int $branchId, ?string $date): array
    {
        if ($date === null || $date === '') {
            return [];
        }

        $base = $this->deliveryStatusBySection('assignments', $branchId, $date);
        $plugin = $this->registry->get('assignments');
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\AssignmentsCampaignModule) {
            return $base;
        }

        $published = $plugin->assignmentsPublishedOnDate($branchId, $date);
        $sectionKeys = [];
        foreach ($published as $assignment) {
            $grade = (string) $assignment->grade;
            $sec = trim((string) ($assignment->section ?? ''));
            if ($sec === '') {
                $rows = DB::table('students')
                    ->where('branch_id', $branchId)
                    ->whereNotNull('user_id')
                    ->selectRaw('grade, section')
                    ->groupBy('grade', 'section')
                    ->get();
                foreach ($rows as $row) {
                    if (\App\NotificationCampaigns\Modules\AssignmentsCampaignModule::gradesMatch(
                        $grade,
                        (string) $row->grade,
                    )) {
                        foreach ($this->sectionLookupKeys((string) $row->grade, (string) $row->section) as $key) {
                            $sectionKeys[$key] = [(string) $row->grade, (string) $row->section];
                        }
                    }
                }
            } else {
                foreach ($this->sectionLookupKeys($grade, $sec) as $key) {
                    $sectionKeys[$key] = [$grade, $sec];
                }
            }
        }

        foreach (array_keys($base) as $key) {
            if (! isset($sectionKeys[$key])) {
                $parts = explode('|', $key, 2);
                if (count($parts) === 2) {
                    $sectionKeys[$key] = [$parts[0], $parts[1]];
                }
            }
        }

        $out = [];
        foreach ($sectionKeys as $key => [$grade, $section]) {
            if ($this->assignmentSectionHasInFlightSend($branchId, $date, $grade, $section)) {
                $out[$key] = [
                    'notification_status' => 'sending',
                ];

                continue;
            }
            if ($this->sectionHasAssignmentDelta($branchId, $date, $grade, $section)) {
                $entry = $this->resolveDeliveryForSection($base, $grade, $section);
                $out[$key] = [
                    'notification_status' => 'not_sent',
                    'campaign_id' => $entry['campaign_id'] ?? null,
                    'sent_count' => $entry['sent_count'] ?? 0,
                ];

                continue;
            }
            $entry = $this->resolveDeliveryForSection($base, $grade, $section);
            $status = $entry['notification_status'] ?? 'not_sent';
            $out[$key] = [
                'notification_status' => $status,
                'campaign_id' => $entry['campaign_id'] ?? null,
                'sent_count' => $entry['sent_count'] ?? 0,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{assignment_count:int,has_new:bool,subject_names:string}>
     */
    public function assignmentSummaryBySection(int $branchId, string $date): array
    {
        $plugin = $this->registry->get('assignments');
        if (! $plugin instanceof \App\NotificationCampaigns\Modules\AssignmentsCampaignModule) {
            return [];
        }

        $published = $plugin->assignmentsPublishedOnDate($branchId, $date);
        /** @var array<string, array{count:int, subjects:array<string, true>}> $buckets */
        $buckets = [];
        foreach ($published as $assignment) {
            $grade = (string) $assignment->grade;
            $sec = trim((string) ($assignment->section ?? ''));
            $subjectName = \App\NotificationCampaigns\Modules\AssignmentsCampaignModule::subjectNameForAssignment($assignment);
            $keys = [];
            if ($sec === '') {
                $studentSections = DB::table('students')
                    ->where('branch_id', $branchId)
                    ->whereNotNull('user_id')
                    ->selectRaw('grade, section')
                    ->groupBy('grade', 'section')
                    ->get();
                foreach ($studentSections as $row) {
                    if (! \App\NotificationCampaigns\Modules\AssignmentsCampaignModule::gradesMatch(
                        $grade,
                        (string) $row->grade,
                    )) {
                        continue;
                    }
                    $keys[] = $this->sectionMapKey((string) $row->grade, (string) $row->section);
                }
            } else {
                foreach ($this->sectionLookupKeys($grade, $sec) as $key) {
                    $keys[] = $key;
                }
            }
            foreach (array_unique($keys) as $key) {
                if (! isset($buckets[$key])) {
                    $buckets[$key] = ['count' => 0, 'subjects' => []];
                }
                $buckets[$key]['count']++;
                if ($subjectName !== '') {
                    $buckets[$key]['subjects'][$subjectName] = true;
                }
            }
        }

        $summary = [];
        foreach ($buckets as $key => $bucket) {
            $parts = explode('|', $key, 2);
            $hasNew = count($parts) === 2
                && Schema::hasTable('notification_campaign_recipients')
                && $this->sectionHasAssignmentDelta($branchId, $date, $parts[0], $parts[1]);
            $subjectNames = array_keys($bucket['subjects']);
            sort($subjectNames, SORT_NATURAL | SORT_FLAG_CASE);
            $summary[$key] = [
                'assignment_count' => (int) $bucket['count'],
                'has_new' => $hasNew,
                'subject_names' => implode(', ', $subjectNames),
            ];
        }

        return $summary;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @return list<array{grade:string,section:string}>
     */
    public function assignmentBlockedSectionTargets(int $branchId, ?string $date, array $targets): array
    {
        if ($targets === [] || $date === null || $date === '') {
            return [];
        }

        $blocked = [];
        foreach ($targets as $target) {
            $grade = (string) $target['grade'];
            $section = (string) $target['section'];
            if ($this->assignmentSectionHasInFlightSend($branchId, $date, $grade, $section)) {
                $blocked[] = $target;

                continue;
            }
            if ($this->sectionHasAssignmentDelta($branchId, $date, $grade, $section)) {
                continue;
            }
            $delivery = $this->deliveryStatusBySection('assignments', $branchId, $date);
            $info = $this->resolveDeliveryForSection($delivery, $grade, $section);
            $status = $info['notification_status'] ?? 'not_sent';
            if (in_array($status, ['sent', 'partial', 'sending'], true)) {
                $blocked[] = $target;
            }
        }

        return $blocked;
    }

    /**
     * @param  list<array{grade:string,section:string}>  $targets
     * @return list<array{grade:string,section:string}>
     */
    public function blockedSectionTargets(
        string $module,
        int $branchId,
        ?string $date,
        array $targets,
        ?int $examId = null,
        ?string $notifyMode = null,
        ?string $feeType = null,
        ?string $feeNotifyMode = null,
        ?string $feeStructureId = null,
    ): array {
        if ($module === 'assignments') {
            return $this->assignmentBlockedSectionTargets($branchId, $date, $targets);
        }

        if ($targets === []) {
            return [];
        }

        $delivery = $this->deliveryStatusBySection(
            $module,
            $branchId,
            $date,
            $examId,
            $notifyMode,
            $feeType,
            $feeNotifyMode,
            $feeStructureId,
        );
        $blocked = [];
        foreach ($targets as $target) {
            $info = $this->resolveDeliveryForSection($delivery, $target['grade'], $target['section']);
            $status = $info['notification_status'] ?? 'not_sent';
            if ($this->notificationStatusBlocksResend($status)) {
                $blocked[] = $target;
            }
        }

        return $blocked;
    }

    /**
     * @param  list<int>  $staffUserIds
     * @return list<int>
     */
    public function filterValidStaffUserIds(int $branchId, array $staffUserIds): array
    {
        $staffUserIds = array_values(array_unique(array_filter(array_map('intval', $staffUserIds), fn ($id) => $id > 0)));
        if ($staffUserIds === []) {
            return [];
        }

        $allowed = [];
        foreach ($this->staffRecipientOptions($branchId)['groups'] as $group) {
            foreach ($group['people'] as $person) {
                $allowed[(int) $person['user_id']] = true;
            }
        }

        return array_values(array_filter($staffUserIds, fn ($id) => isset($allowed[$id])));
    }

    /**
     * @param  list<int>  $staffUserIds
     * @return list<array<string, mixed>>
     */
    public function resolveStaffRecipients(
        int $branchId,
        array $staffUserIds,
        ?string $module = null,
        ?string $eventDate = null,
    ): array {
        $staffUserIds = $this->filterValidStaffUserIds($branchId, $staffUserIds);
        if ($staffUserIds === []) {
            return [];
        }

        $groupByUser = [];
        foreach ($this->staffRecipientOptions($branchId)['groups'] as $group) {
            foreach ($group['people'] as $person) {
                $groupByUser[(int) $person['user_id']] = $group['key'];
            }
        }

        $users = User::query()
            ->whereIn('id', $staffUserIds)
            ->where('is_active', 1)
            ->whereNull('deleted_at')
            ->get(['id', 'first_name', 'last_name', 'phone', 'role']);

        $teacherByUser = Teacher::query()
            ->where('branch_id', $branchId)
            ->whereIn('user_id', $staffUserIds)
            ->with(['user:id,first_name,last_name,phone,role', 'department:id,name'])
            ->get()
            ->keyBy('user_id');

        $resolvedDate = ($eventDate !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $eventDate))
            ? $eventDate
            : now()->toDateString();
        $dateLabel = Carbon::parse($resolvedDate)->format('d M Y');
        $rows = [];
        foreach ($users as $user) {
            $userId = (int) $user->id;
            $name = trim(($user->first_name ?? '').' '.($user->last_name ?? ''));
            $groupKey = $groupByUser[$userId] ?? 'staff';
            $teacher = $teacherByUser->get($userId);
            $context = array_merge(
                $this->tagContextFactory->buildForStaffUser($user, $teacher, $branchId),
                [
                    'student_name' => $name,
                    'teacher_name' => $name,
                    'date' => $dateLabel,
                    'attendance_date' => $dateLabel,
                    'status' => 'Staff',
                    'grade' => 'Staff',
                    'section' => $groupKey,
                ]
            );
            $context = $this->enrichStaffRecipientContext($context, $module, $resolvedDate, $branchId);
            $rows[] = [
                'user_id' => $userId,
                'student_id' => null,
                'student_name' => $name,
                'grade' => 'Staff',
                'section' => $groupKey,
                'status_key' => self::STAFF_STATUS_KEY,
                'context' => $context,
            ];
        }

        return $rows;
    }

    public function materializeStaffIfNeeded(NotificationCampaign $campaign): void
    {
        if ($campaign->staff_materialized_at !== null) {
            return;
        }
        $ids = is_array($campaign->staff_user_ids) ? $campaign->staff_user_ids : [];
        if ($ids === [] || ! $campaign->staff_template_id) {
            return;
        }

        $eventDate = $campaign->event_date !== null ? (string) $campaign->event_date : null;
        $recipients = $this->resolveStaffRecipients(
            (int) $campaign->branch_id,
            $ids,
            (string) $campaign->module,
            $eventDate,
        );
        if ($recipients === []) {
            $campaign->update(['staff_materialized_at' => now()]);

            return;
        }

        $added = $this->insertRecipientRows($campaign, $recipients, []);
        $campaign->update([
            'staff_materialized_at' => now(),
            'recipient_count' => $campaign->recipient_count + $added,
        ]);
    }

    /**
     * Merge module-specific template tags (e.g. #holiday_date#) for branch-team messages.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function enrichStaffRecipientContext(array $context, ?string $module, string $eventDate, int $branchId): array
    {
        if ($module === null || $module === '') {
            return $context;
        }

        $plugin = $this->registry->get($module);
        if (! $plugin instanceof NotificationCampaignModule) {
            return $context;
        }

        if ($module === 'holidays') {
            return $plugin->enrichContext($context, null, self::STAFF_STATUS_KEY, $eventDate, $branchId);
        }

        return $context;
    }

    /**
     * @return array{branch_name:string,school_name:string}
     */
    private function branchSchoolTags(int $branchId): array
    {
        if ($this->branchSchoolContextBranchId === $branchId && $this->branchSchoolContextCache !== null) {
            return $this->branchSchoolContextCache;
        }

        $this->branchSchoolContextCache = $this->tagContextFactory->branchSchoolTags($branchId);
        $this->branchSchoolContextBranchId = $branchId;

        return $this->branchSchoolContextCache;
    }
}
