<?php

namespace App\NotificationCampaigns\Modules;

use App\Models\FeeDue;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\NotificationCampaigns\Concerns\EligibleTargetHelpers;
use App\NotificationCampaigns\NotificationCampaignModule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FeesCampaignModule implements NotificationCampaignModule
{
    use EligibleTargetHelpers;

    /** Sentinel stored on campaigns / sent from UI for all fee types on a due date. */
    public const FEE_TYPE_ALL = '__all__';

    /**
     * Full reminder rows per branch + academic year (built once per HTTP request).
     *
     * @var array<string, list<object>>
     */
    private array $reminderRowsCache = [];

    public function slug(): string
    {
        return 'fees';
    }

    public function label(): string
    {
        return 'Fees';
    }

    public function statuses(): array
    {
        return [
            ['key' => 'due', 'label' => 'Due'],
            ['key' => 'overdue', 'label' => 'Overdue'],
            ['key' => 'structure', 'label' => 'Fee structure'],
        ];
    }

    public function requiresEventDate(): bool
    {
        return true;
    }

    public function confirmSelection(): bool
    {
        return true;
    }

    public function eventDatePolicy(): string
    {
        return 'any';
    }

    public function eligibleTargets(int $branchId, string $date): array
    {
        return $this->eligibleTargetsForReminder($branchId, $date, null, null);
    }

    /**
     * @return list<array{grade:string,section:string,class_name:string,student_count:int}>
     */
    public function eligibleTargetsForReminder(
        int $branchId,
        ?string $dueDate,
        ?string $feeType,
        ?string $academicYear,
    ): array {
        if ($dueDate === null || $dueDate === '') {
            return [];
        }

        $counts = [];
        $seenStudents = [];
        foreach ($this->unpaidReminderRows($branchId, $academicYear, $feeType, $dueDate) as $row) {
            $key = $this->sectionKey($row->grade, $row->section);
            $studentKey = $key.'|'.(string) $row->student_id;
            if (isset($seenStudents[$studentKey])) {
                continue;
            }
            $seenStudents[$studentKey] = true;
            $counts[$key] = ($counts[$key] ?? 0) + 1;
        }

        $rows = collect($counts)->map(function (int $count, string $key) {
            [$grade, $section] = explode('|', $key, 2);

            return (object) [
                'grade' => $grade,
                'section' => $section,
                'student_count' => $count,
            ];
        })->sortBy([['grade', 'asc'], ['section', 'asc']])->values();

        return $this->mapEligibleRows($rows, 'grade', 'student_count');
    }

    /**
     * @return array<string, array{grade:string,section:string,unpaid_count:int,overdue_count:int}>
     */
    public function sectionFeeSummaries(
        int $branchId,
        ?string $dueDate,
        ?string $feeType,
        ?string $academicYear,
    ): array {
        if ($dueDate === null || $dueDate === '') {
            return [];
        }

        $today = now()->toDateString();
        $map = [];
        $seenStudents = [];
        foreach ($this->unpaidReminderRows($branchId, $academicYear, $feeType, $dueDate) as $row) {
            $grade = trim((string) $row->grade);
            $section = trim((string) $row->section);
            if ($grade === '' || $section === '') {
                continue;
            }
            $key = $this->sectionKey($grade, $section);
            if (! isset($map[$key])) {
                $map[$key] = [
                    'grade' => $grade,
                    'section' => $section,
                    'unpaid_count' => 0,
                    'overdue_count' => 0,
                    'matched_student_count' => 0,
                ];
            }
            $studentId = (string) $row->student_id;
            $seenKey = $key.'|'.$studentId;
            if (isset($seenStudents[$seenKey])) {
                continue;
            }
            $seenStudents[$seenKey] = true;

            $map[$key]['unpaid_count']++;
            $map[$key]['matched_student_count'] = $map[$key]['unpaid_count'];
            $dueDateStr = $row->due_date ? Carbon::parse($row->due_date)->toDateString() : $dueDate;
            $isOverdue = (string) $row->due_status === 'Overdue' || $dueDateStr < $today;
            if ($isOverdue) {
                $map[$key]['overdue_count']++;
            }
        }

        return $map;
    }

    /**
     * @return list<array{fee_type:string,label:string,unpaid_student_count:int,list_state:string,selectable:bool}>
     */
    public function feeTypeOptions(int $branchId, ?string $academicYear, ?string $dueDate = null): array
    {
        $byType = [];
        $allStudentIds = [];
        foreach ($this->unpaidReminderRows($branchId, $academicYear, null, $dueDate) as $row) {
            $type = trim((string) $row->fee_type);
            if ($type === '') {
                continue;
            }
            $studentId = (string) $row->student_id;
            $byType[$type][$studentId] = true;
            $allStudentIds[$studentId] = true;
        }

        $options = [];
        ksort($byType);
        foreach ($byType as $type => $studentIds) {
            $options[] = [
                'fee_type' => $type,
                'label' => $type,
                'unpaid_student_count' => count($studentIds),
                'list_state' => 'active',
                'selectable' => true,
            ];
        }

        $totalStudents = count($allStudentIds);
        if ($totalStudents > 0) {
            array_unshift($options, [
                'fee_type' => self::FEE_TYPE_ALL,
                'label' => 'All fee types (total)',
                'unpaid_student_count' => $totalStudents,
                'list_state' => 'active',
                'selectable' => true,
            ]);
        }

        return $options;
    }

    /**
     * Distinct due dates from active fee structures (for Due notify picker).
     * Fetches recent dates in descending order, then returns up to $limit with upcoming dates first (asc).
     *
     * @return list<string> Y-m-d
     */
    public function distinctDueDatesForBranch(int $branchId, ?string $academicYear, ?string $feeType, int $limit = 15): array
    {
        $rows = $this->filterReminderRows(
            $this->reminderRowsForBranch($branchId, $academicYear),
            $feeType,
            null,
        );
        $dates = [];
        foreach ($rows as $row) {
            if ($row->due_date !== '') {
                $dates[$row->due_date] = true;
            }
        }

        return $this->orderDueDatesUpcomingFirst(array_keys($dates), $limit);
    }

    /**
     * Warm full-branch reminder rows so later meta/eligibility calls filter in memory only.
     */
    public function preloadReminderRows(int $branchId, ?string $academicYear): void
    {
        $this->reminderRowsForBranch($branchId, $academicYear);
    }

    /**
     * @return array<string, list<string>> fee_type (incl. {@see FEE_TYPE_ALL}) => section keys with unpaid students
     */
    public function sectionKeysWithUnpaidByFeeType(int $branchId, string $dueDate, ?string $academicYear): array
    {
        $rows = $this->filterReminderRows(
            $this->reminderRowsForBranch($branchId, $academicYear),
            null,
            $dueDate,
        );

        $byType = [];
        $allSections = [];
        $seen = [];
        foreach ($rows as $row) {
            $type = trim((string) $row->fee_type);
            if ($type === '') {
                continue;
            }
            $sectionKey = $this->sectionKey((string) $row->grade, (string) $row->section);
            $dedupe = $type.'|'.$sectionKey.'|'.(string) $row->student_id;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $byType[$type][$sectionKey] = true;
            $allSections[$sectionKey] = true;
        }

        $out = [];
        foreach ($byType as $type => $sections) {
            $out[$type] = array_keys($sections);
        }
        if ($allSections !== []) {
            $out[self::FEE_TYPE_ALL] = array_keys($allSections);
        }

        return $out;
    }

    /**
     * @param  list<string>  $dates  Y-m-d
     * @return list<string>
     */
    public function orderDueDatesUpcomingFirst(array $dates, int $limit = 15): array
    {
        $today = now()->toDateString();
        $upcoming = [];
        $past = [];
        foreach ($dates as $date) {
            if ($date >= $today) {
                $upcoming[] = $date;
            } else {
                $past[] = $date;
            }
        }
        sort($upcoming);
        rsort($past);
        $ordered = array_values(array_unique(array_merge($upcoming, $past)));

        return array_slice($ordered, 0, $limit);
    }

    /**
     * @return list<string> section keys grade|section with unpaid students for filter.
     */
    public function sectionKeysForReminder(int $branchId, string $dueDate, ?string $feeType, ?string $academicYear): array
    {
        $summaries = $this->sectionFeeSummaries($branchId, $dueDate, $feeType, $academicYear);
        $keys = [];
        foreach ($summaries as $key => $summary) {
            if (($summary['unpaid_count'] ?? 0) > 0) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    public function duesForBranch(
        int $branchId,
        ?string $academicYear,
        ?string $feeType,
        ?string $dueDate,
    ): Builder {
        $query = FeeDue::query()
            ->join('students as s', 's.id', '=', 'fee_dues.student_id')
            ->where('s.branch_id', $branchId)
            ->where('fee_dues.balance_amount', '>', 0)
            ->where(function ($q) {
                $q->whereNull('fee_dues.status')
                    ->orWhere('fee_dues.status', '!=', 'Paid');
            });

        if ($academicYear !== null && $academicYear !== '') {
            $query->where('fee_dues.academic_year', $academicYear);
        }

        if ($feeType !== null && $feeType !== '' && $feeType !== self::FEE_TYPE_ALL) {
            $query->where('fee_dues.fee_type', $feeType);
        }

        if ($dueDate !== null && $dueDate !== '') {
            $query->whereDate('fee_dues.due_date', $dueDate);
        }

        return $query;
    }

    public function classifyByUserId(int $branchId, string $date, Collection $students): array
    {
        return $this->classifyByUserIdForReminder($branchId, $date, $students, null, null);
    }

    /**
     * @return array<int, string> user_id => due|overdue (unpaid only; omit paid / no matching due)
     */
    public function classifyByUserIdForReminder(
        int $branchId,
        string $dueDate,
        Collection $students,
        ?string $feeType,
        ?string $academicYear,
    ): array {
        $studentIds = $students->pluck('id')->map(fn ($id) => (string) $id)->all();
        if ($studentIds === []) {
            return [];
        }

        $today = now()->toDateString();
        $byStudent = [];
        foreach ($this->unpaidReminderRows($branchId, $academicYear, $feeType, $dueDate) as $row) {
            $sid = (string) $row->student_id;
            if (! in_array($sid, $studentIds, true)) {
                continue;
            }
            $dueDateStr = $row->due_date ? Carbon::parse($row->due_date)->toDateString() : $dueDate;
            $overdue = (string) $row->due_status === 'Overdue' || $dueDateStr < $today;
            $byStudent[$sid] = $overdue ? 'overdue' : 'due';
        }

        $map = [];
        foreach ($students as $student) {
            $status = $byStudent[(string) $student->id] ?? null;
            if ($status === null) {
                continue;
            }
            $userId = (int) ($student->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $map[$userId] = $status;
        }

        return $map;
    }

    public function enrichContext(array $baseContext, $student, string $statusKey, string $date, int $branchId): array
    {
        return $this->enrichContextForReminder($baseContext, $student, $date, $branchId, null, null);
    }

    public function enrichContextForReminder(
        array $baseContext,
        $student,
        string $dueDate,
        int $branchId,
        ?string $feeType,
        ?string $academicYear,
    ): array {
        $match = null;
        foreach ($this->unpaidReminderRows($branchId, $academicYear, $feeType, $dueDate) as $row) {
            if ((string) $row->student_id !== (string) $student->id) {
                continue;
            }
            $match = $row;
            if ($feeType !== null && $feeType !== '' && $feeType !== self::FEE_TYPE_ALL) {
                break;
            }
        }

        if ($match) {
            $baseContext['amount_due'] = number_format((float) $match->balance, 2, '.', '');
            $baseContext['due_date'] = $match->due_date
                ? Carbon::parse($match->due_date)->format('d M Y')
                : '';
            $baseContext['fee_type'] = (string) ($match->fee_type ?? '');
        }

        return $baseContext;
    }

    public function hasOpenDuesForBranch(int $branchId, string $dueDate, ?string $feeType, ?string $academicYear): bool
    {
        foreach ($this->unpaidReminderRows($branchId, $academicYear, $feeType, $dueDate) as $row) {
            return true;
        }

        return false;
    }

    public function structuresForBranch(int $branchId, ?string $academicYear): Builder
    {
        $query = FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true);

        if ($academicYear !== null && $academicYear !== '') {
            $query->where('academic_year', $academicYear);
        }

        return $query->orderByDesc('due_date')->orderBy('grade')->orderBy('fee_type');
    }

    /**
     * @return list<array{fee_structure_id:string,label:string,grade:string,fee_type:string,due_date:?string,amount:string,list_state:string,selectable:bool}>
     */
    public function structureNotifyOptions(int $branchId, ?string $academicYear): array
    {
        $structures = $this->structuresForBranch($branchId, $academicYear)
            ->get(['id', 'grade', 'fee_type', 'amount', 'due_date']);

        $options = [];
        foreach ($structures as $structure) {
            $due = $structure->due_date ? Carbon::parse($structure->due_date)->toDateString() : null;
            $amount = number_format((float) $structure->amount, 2, '.', '');
            $options[] = [
                'fee_structure_id' => (string) $structure->id,
                'label' => sprintf(
                    'Grade %s · %s · ₹%s%s',
                    (string) $structure->grade,
                    (string) $structure->fee_type,
                    $amount,
                    $due ? ' · due '.$due : ''
                ),
                'grade' => (string) $structure->grade,
                'fee_type' => (string) $structure->fee_type,
                'due_date' => $due,
                'amount' => $amount,
                'list_state' => 'active',
                'selectable' => true,
            ];
        }

        return $options;
    }

    /**
     * @return list<array{grade:string,section:string,class_name:string,student_count:int}>
     */
    public function eligibleTargetsForStructure(int $branchId, string $feeStructureId): array
    {
        $structure = FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('id', $feeStructureId)
            ->first(['id', 'grade']);

        if (! $structure) {
            return [];
        }

        $structureGrade = (string) $structure->grade;
        $rows = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as student_count')
            ->groupBy('grade', 'section')
            ->orderBy('section')
            ->get()
            ->filter(fn ($row) => $this->gradeMatches((string) $row->grade, $structureGrade))
            ->values();

        return $this->mapEligibleRows($rows, 'grade', 'student_count');
    }

    /**
     * @return array<string, array{grade:string,section:string,enrolled_count:int,structure_applies:bool}>
     */
    public function sectionStructureSummaries(int $branchId, string $feeStructureId): array
    {
        $structure = FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('id', $feeStructureId)
            ->first(['grade']);

        if (! $structure) {
            return [];
        }

        $structureGrade = (string) $structure->grade;
        $rows = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->selectRaw('grade, section, COUNT(*) as enrolled_count')
            ->groupBy('grade', 'section')
            ->get()
            ->filter(fn ($row) => $this->gradeMatches((string) $row->grade, $structureGrade));

        $map = [];
        foreach ($rows as $row) {
            $key = $this->sectionKey((string) $row->grade, (string) $row->section);
            $map[$key] = [
                'grade' => (string) $row->grade,
                'section' => (string) $row->section,
                'enrolled_count' => (int) $row->enrolled_count,
                'structure_applies' => true,
            ];
        }

        return $map;
    }

    /**
     * @return list<string>
     */
    public function sectionKeysForStructure(int $branchId, string $feeStructureId): array
    {
        $summaries = $this->sectionStructureSummaries($branchId, $feeStructureId);
        $keys = [];
        foreach ($summaries as $key => $summary) {
            if (($summary['enrolled_count'] ?? 0) > 0) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    /**
     * @return array<int, string>
     */
    public function classifyByUserIdForStructure(Collection $students): array
    {
        $map = [];
        foreach ($students as $student) {
            $userId = (int) ($student->user_id ?? 0);
            if ($userId <= 0) {
                continue;
            }
            $map[$userId] = 'structure';
        }

        return $map;
    }

    public function enrichContextForStructure(array $baseContext, $student, int $branchId, string $feeStructureId): array
    {
        $structure = FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('id', $feeStructureId)
            ->first();

        if ($structure) {
            $baseContext['fee_type'] = (string) $structure->fee_type;
            $baseContext['amount_due'] = number_format((float) $structure->amount, 2, '.', '');
            $baseContext['due_date'] = $structure->due_date
                ? Carbon::parse($structure->due_date)->format('d M Y')
                : '';
            $baseContext['grade'] = (string) $structure->grade;
        }

        return $baseContext;
    }

    public function findStructureForBranch(int $branchId, string $feeStructureId): ?FeeStructure
    {
        return FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('id', $feeStructureId)
            ->first();
    }

    private function sectionKey(string $grade, string $section): string
    {
        return trim($grade).'|'.trim($section);
    }

    /**
     * Open fee_dues plus unpaid balances from active fee structures (when no due row exists yet).
     *
     * @return list<object{
     *   grade:string,
     *   section:string,
     *   student_id:int|string,
     *   user_id:int|string,
     *   fee_type:string,
     *   due_date:string,
     *   due_status:string,
     *   balance:float
     * }>
     */
    /**
     * @return list<object{
     *   grade:string,
     *   section:string,
     *   student_id:int|string,
     *   user_id:int|string,
     *   fee_type:string,
     *   due_date:string,
     *   due_status:string,
     *   balance:float
     * }>
     */
    private function unpaidReminderRows(
        int $branchId,
        ?string $academicYear,
        ?string $feeType,
        ?string $dueDate,
    ): array {
        return $this->filterReminderRows(
            $this->reminderRowsForBranch($branchId, $academicYear),
            $feeType,
            $dueDate,
        );
    }

    /**
     * @return list<object>
     */
    private function reminderRowsForBranch(int $branchId, ?string $academicYear): array
    {
        $key = $branchId.'|'.($academicYear ?? '');
        if (isset($this->reminderRowsCache[$key])) {
            return $this->reminderRowsCache[$key];
        }

        $this->reminderRowsCache[$key] = $this->buildReminderRowsForBranch($branchId, $academicYear);

        return $this->reminderRowsCache[$key];
    }

    /**
     * @param  list<object>  $rows
     * @return list<object>
     */
    private function filterReminderRows(array $rows, ?string $feeType, ?string $dueDate): array
    {
        if (
            ($feeType === null || $feeType === '' || $feeType === self::FEE_TYPE_ALL)
            && ($dueDate === null || $dueDate === '')
        ) {
            return $rows;
        }

        return array_values(array_filter($rows, function ($row) use ($feeType, $dueDate) {
            if ($feeType !== null && $feeType !== '' && $feeType !== self::FEE_TYPE_ALL) {
                if ((string) $row->fee_type !== $feeType) {
                    return false;
                }
            }
            if ($dueDate !== null && $dueDate !== '') {
                if ((string) $row->due_date !== $dueDate) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * @return list<object>
     */
    private function buildReminderRowsForBranch(int $branchId, ?string $academicYear): array
    {
        $rows = [];
        $seen = [];

        $dueRows = $this->duesForBranch($branchId, $academicYear, null, null)->get([
            's.grade as grade',
            's.section as section',
            's.id as student_id',
            's.user_id as user_id',
            'fee_dues.fee_type as fee_type',
            'fee_dues.due_date as due_date',
            'fee_dues.status as due_status',
            'fee_dues.balance_amount as balance',
        ]);

        foreach ($dueRows as $row) {
            $studentId = (string) $row->student_id;
            $type = trim((string) $row->fee_type);
            $dueDateStr = $row->due_date ? Carbon::parse($row->due_date)->toDateString() : '';
            if ($dueDateStr === '') {
                continue;
            }
            $dedupe = $studentId.'|'.$type.'|'.$dueDateStr;
            if (isset($seen[$dedupe])) {
                continue;
            }
            $seen[$dedupe] = true;
            $rows[] = (object) [
                'grade' => (string) $row->grade,
                'section' => (string) $row->section,
                'student_id' => $row->student_id,
                'user_id' => $row->user_id,
                'fee_type' => $type,
                'due_date' => $dueDateStr,
                'due_status' => (string) ($row->due_status ?? 'Pending'),
                'balance' => (float) $row->balance,
            ];
        }

        $structureQuery = FeeStructure::query()
            ->where('branch_id', $branchId)
            ->where('is_active', true)
            ->whereNotNull('due_date');

        if ($academicYear !== null && $academicYear !== '') {
            $structureQuery->where('academic_year', $academicYear);
        }

        $structures = $structureQuery->get(['id', 'grade', 'fee_type', 'amount', 'due_date']);
        if ($structures->isEmpty()) {
            return $rows;
        }

        $structureIds = $structures->pluck('id')->all();
        $paidByUserStructure = [];
        $paymentRows = FeePayment::query()
            ->whereIn('fee_structure_id', $structureIds)
            ->selectRaw('student_id as user_id, fee_structure_id, SUM(amount_paid) as paid')
            ->groupBy('student_id', 'fee_structure_id')
            ->get();
        foreach ($paymentRows as $payment) {
            $paidByUserStructure[(string) $payment->user_id.'|'.(string) $payment->fee_structure_id] = (float) $payment->paid;
        }

        $studentsByGrade = [];
        $students = DB::table('students')
            ->where('branch_id', $branchId)
            ->whereNotNull('user_id')
            ->whereNotNull('section')
            ->where('section', '!=', '')
            ->get(['id', 'user_id', 'grade', 'section']);

        foreach ($students as $student) {
            $gradeKey = $this->normalizeGradeKey((string) $student->grade);
            $studentsByGrade[$gradeKey][] = $student;
        }

        $today = now()->toDateString();
        foreach ($structures as $structure) {
            $type = trim((string) $structure->fee_type);
            $dueDateStr = Carbon::parse($structure->due_date)->toDateString();
            $original = (float) $structure->amount;
            $gradeKey = $this->normalizeGradeKey((string) $structure->grade);
            $gradeStudents = $studentsByGrade[$gradeKey] ?? [];

            foreach ($gradeStudents as $student) {
                $studentId = (string) $student->id;
                $dedupe = $studentId.'|'.$type.'|'.$dueDateStr;
                if (isset($seen[$dedupe])) {
                    continue;
                }

                $paid = $paidByUserStructure[(string) $student->user_id.'|'.(string) $structure->id] ?? 0.0;
                $balance = round(max(0, $original - $paid), 2);
                if ($balance <= 0) {
                    continue;
                }

                $seen[$dedupe] = true;
                $status = $paid > 0 ? 'PartiallyPaid' : 'Pending';
                if ($dueDateStr < $today) {
                    $status = 'Overdue';
                }

                $rows[] = (object) [
                    'grade' => (string) $student->grade,
                    'section' => (string) $student->section,
                    'student_id' => $student->id,
                    'user_id' => $student->user_id,
                    'fee_type' => $type,
                    'due_date' => $dueDateStr,
                    'due_status' => $status,
                    'balance' => $balance,
                ];
            }
        }

        return $rows;
    }

    private function gradeMatches(string $studentGrade, string $structureGrade): bool
    {
        return $this->normalizeGradeKey($studentGrade) === $this->normalizeGradeKey($structureGrade);
    }

    private function normalizeGradeKey(string $grade): string
    {
        $g = trim($grade);
        if (preg_match('/^grade\s+(.+)$/i', $g, $matches)) {
            return trim($matches[1]);
        }

        return $g;
    }
}
