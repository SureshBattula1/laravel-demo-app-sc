<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\FeeDue;
use App\Models\FeePayment;
use App\Models\FeeStructure;
use App\Models\Student;
use App\Services\Concerns\BuildsStudentReportBranding;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class StudentFeeStatementService
{
    use BuildsStudentReportBranding;

    public function __construct(
        protected FeeDuesService $feeDuesService
    ) {}

    /**
     * @return array{filename: string, pdf: \Barryvdh\DomPDF\PDF}
     */
    public function generatePdf(Request $request, int $studentUserId): array
    {
        $student = Student::with(['user', 'branch.school'])
            ->where('user_id', $studentUserId)
            ->firstOrFail();

        $academicYearId = $request->attributes->get('academic_year_id') ?? $request->input('academic_year_id');
        $academicYearName = $academicYearId
            ? (AcademicYear::query()->where('id', $academicYearId)->value('name') ?? null)
            : null;

        if ($academicYearName && $student->academic_year !== $academicYearName) {
            $student->setAttribute('academic_year', $academicYearName);
        }

        $dueRows = $this->collectMergedDueRows($student, $studentUserId, $academicYearId, $academicYearName);
        $paymentRows = $this->collectPaymentRows($studentUserId, $academicYearName);

        [$dueRows, $paymentRows, $pillLabel] = $this->applyFilters(
            $request,
            $student,
            $dueRows,
            $paymentRows
        );

        $summary = $this->buildSummary($dueRows, $paymentRows);

        $viewData = array_merge(
            $this->buildReportBrandingContext($student, 'FEE STATEMENT'),
            [
                'pillLabel' => $pillLabel,
                'summary' => $summary,
                'dueRows' => $dueRows,
                'paymentRows' => $paymentRows,
                'generatedAt' => now()->format('d M Y'),
            ]
        );

        $filename = $this->buildFilename($student, $request, $pillLabel, $paymentRows);

        $pdf = app('dompdf.wrapper');
        $pdf->loadView('pdf.student-fee-statement', $viewData);
        $pdf->setPaper('a4', 'portrait');

        return ['filename' => $filename, 'pdf' => $pdf];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectMergedDueRows(
        Student $student,
        int $studentUserId,
        ?int $academicYearId,
        ?string $academicYearName
    ): array {
        $duesResult = $this->feeDuesService->getStudentDues($studentUserId);
        /** @var Collection<int, FeeDue> $feeDues */
        $feeDues = collect($duesResult['dues'] ?? []);

        if ($academicYearName) {
            $feeDues = $feeDues->filter(function (FeeDue $due) use ($academicYearName) {
                return ! $due->academic_year || $due->academic_year === $academicYearName;
            });
        }

        $rows = $feeDues->map(fn (FeeDue $due) => $this->normalizeFeeDueRow($due))->values()->all();

        $pending = $this->collectPendingStructureRows($student, $studentUserId, $academicYearId, $academicYearName);
        $seen = collect($rows)->map(fn (array $r) => $this->dueMergeKey($r))->flip();

        foreach ($pending as $pendingRow) {
            $key = $this->dueMergeKey($pendingRow);
            if (! $seen->has($key)) {
                $rows[] = $pendingRow;
                $seen->put($key, true);
            }
        }

        usort($rows, function (array $a, array $b) {
            return strcmp($a['sort_date'] ?? '', $b['sort_date'] ?? '');
        });

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectPendingStructureRows(
        Student $student,
        int $studentUserId,
        ?int $academicYearId,
        ?string $academicYearName
    ): array {
        $paidStructureIdsQuery = FeePayment::where('student_id', $studentUserId)
            ->where('payment_status', 'Completed');
        if ($academicYearName) {
            $paidStructureIdsQuery->where('academic_year', $academicYearName);
        }
        $paidStructureIds = $paidStructureIdsQuery->pluck('fee_structure_id')
            ->filter()
            ->unique()
            ->toArray();

        $pendingQuery = FeeStructure::where('is_active', true)
            ->where('branch_id', $student->branch_id)
            ->where(function ($q) use ($student) {
                $q->where('grade', $student->grade)
                    ->orWhere('grade', 'Grade '.$student->grade)
                    ->orWhere('grade', str_replace('Grade ', '', $student->grade));
            });

        if ($academicYearId && Schema::hasColumn('fee_structures', 'academic_year_id')) {
            $pendingQuery->where('academic_year_id', $academicYearId);
        } elseif ($academicYearName) {
            $pendingQuery->where('academic_year', $academicYearName);
        }

        $pending = $pendingQuery
            ->when(! empty($paidStructureIds), fn ($q) => $q->whereNotIn('id', $paidStructureIds))
            ->get();

        $rows = [];
        foreach ($pending as $fee) {
            $feeType = $fee->fee_type ?: 'General Fee';
            $amountPaidQuery = FeePayment::where('student_id', $studentUserId)
                ->where('fee_structure_id', $fee->id)
                ->whereIn('payment_status', ['Completed', 'Partial']);
            if ($academicYearName) {
                $amountPaidQuery->where('academic_year', $academicYearName);
            }
            $amountPaid = (float) $amountPaidQuery->sum('amount_paid');
            $assessed = (float) $fee->amount;
            $balance = max(0, $assessed - $amountPaid);

            if ($balance <= 0 && $amountPaid <= 0) {
                continue;
            }

            $dueDate = $fee->due_date;
            $rows[] = [
                'fee_type' => $feeType,
                'due_date' => $this->formatDate($dueDate),
                'sort_date' => $this->sortDate($dueDate),
                'assessed' => $this->moneyLabel($assessed),
                'paid' => $this->moneyLabel($amountPaid),
                'balance' => $this->moneyLabel($balance),
                'status' => $this->displayDueStatus($amountPaid, $balance, $dueDate),
                'fee_structure_id' => (string) $fee->id,
                'fee_due_id' => null,
                'assessed_raw' => $assessed,
                'paid_raw' => $amountPaid,
                'balance_raw' => $balance,
            ];
        }

        return $rows;
    }

    protected function normalizeFeeDueRow(FeeDue $due): array
    {
        $assessed = (float) $due->original_amount;
        $paid = (float) $due->paid_amount;
        $balance = (float) $due->balance_amount;

        return [
            'fee_type' => $due->fee_type ?: 'General Fee',
            'due_date' => $this->formatDate($due->due_date),
            'sort_date' => $this->sortDate($due->due_date),
            'assessed' => $this->moneyLabel($assessed),
            'paid' => $this->moneyLabel($paid),
            'balance' => $this->moneyLabel($balance),
            'status' => $this->mapDueStatus($due->status, $balance, $due->due_date),
            'fee_structure_id' => $due->fee_structure_id ? (string) $due->fee_structure_id : null,
            'fee_due_id' => (string) $due->id,
            'assessed_raw' => $assessed,
            'paid_raw' => $paid,
            'balance_raw' => $balance,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function collectPaymentRows(int $studentUserId, ?string $academicYearName): array
    {
        $paymentsQuery = FeePayment::with(['feeStructure'])
            ->where('student_id', $studentUserId);
        if ($academicYearName) {
            $paymentsQuery->where('academic_year', $academicYearName);
        }

        return $paymentsQuery
            ->orderByDesc('payment_date')
            ->limit(200)
            ->get()
            ->map(function (FeePayment $payment) {
                $feeType = $payment->feeStructure?->fee_type ?: 'General Fee';
                $amount = (float) ($payment->total_amount ?: $payment->amount_paid);

                return [
                    'receipt_number' => $payment->receipt_number ?: ('PAYMENT-'.$payment->id),
                    'payment_date' => $this->formatDate($payment->payment_date),
                    'fee_type' => $feeType,
                    'method' => $payment->payment_method ?: '—',
                    'amount' => $this->moneyLabel($amount),
                    'status' => $payment->payment_status ?: '—',
                    'amount_raw' => $amount,
                    'fee_structure_id' => $payment->fee_structure_id ? (string) $payment->fee_structure_id : null,
                    'payment_id' => (string) $payment->id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $dueRows
     * @param  array<int, array<string, mixed>>  $paymentRows
     * @return array{0: array, 1: array, 2: string}
     */
    protected function applyFilters(
        Request $request,
        Student $student,
        array $dueRows,
        array $paymentRows
    ): array {
        $pillLabel = 'FEES OVERVIEW';

        if ($request->filled('payment_id')) {
            $paymentId = (string) $request->input('payment_id');
            $paymentRows = array_values(array_filter(
                $paymentRows,
                fn (array $r) => ($r['payment_id'] ?? '') === $paymentId
            ));
            if (empty($paymentRows)) {
                abort(404, 'Payment not found for this student.');
            }
            $target = $paymentRows[0];
            $structureId = $target['fee_structure_id'] ?? null;
            $feeType = $target['fee_type'] ?? 'Fee';
            $pillLabel = strtoupper($feeType).' — STATEMENT';

            if ($structureId) {
                $dueRows = array_values(array_filter(
                    $dueRows,
                    fn (array $r) => ($r['fee_structure_id'] ?? null) === $structureId
                ));
            } else {
                $dueRows = array_values(array_filter(
                    $dueRows,
                    fn (array $r) => strcasecmp($r['fee_type'] ?? '', $feeType) === 0
                ));
            }

            return [$dueRows, $paymentRows, $pillLabel];
        }

        if ($request->filled('fee_due_id')) {
            $dueId = (string) $request->input('fee_due_id');
            $dueRows = array_values(array_filter(
                $dueRows,
                fn (array $r) => ($r['fee_due_id'] ?? null) === $dueId
            ));
            if (empty($dueRows)) {
                abort(404, 'Fee due not found for this student.');
            }
            $pillLabel = strtoupper($dueRows[0]['fee_type'] ?? 'FEE').' — STATEMENT';
            $structureId = $dueRows[0]['fee_structure_id'] ?? null;
            if ($structureId) {
                $paymentRows = array_values(array_filter(
                    $paymentRows,
                    fn (array $r) => ($r['fee_structure_id'] ?? null) === $structureId
                ));
            }

            return [$dueRows, $paymentRows, $pillLabel];
        }

        if ($request->filled('fee_structure_id')) {
            $structureId = (string) $request->input('fee_structure_id');
            $dueRows = array_values(array_filter(
                $dueRows,
                fn (array $r) => ($r['fee_structure_id'] ?? null) === $structureId
            ));
            $paymentRows = array_values(array_filter(
                $paymentRows,
                fn (array $r) => ($r['fee_structure_id'] ?? null) === $structureId
            ));
            if (empty($dueRows) && empty($paymentRows)) {
                abort(404, 'No fee records found for this fee type.');
            }
            $feeType = $dueRows[0]['fee_type']
                ?? ($paymentRows[0]['fee_type'] ?? 'Fee');
            $pillLabel = strtoupper($feeType).' — STATEMENT';

            return [$dueRows, $paymentRows, $pillLabel];
        }

        return [$dueRows, $paymentRows, $pillLabel];
    }

    /**
     * @param  array<int, array<string, mixed>>  $dueRows
     * @param  array<int, array<string, mixed>>  $paymentRows
     * @return array<string, string>
     */
    protected function buildSummary(array $dueRows, array $paymentRows): array
    {
        $assessed = array_sum(array_column($dueRows, 'assessed_raw'));
        if ($assessed <= 0) {
            $assessed = array_sum(array_column($paymentRows, 'amount_raw'));
            $assessed += array_sum(array_column($dueRows, 'balance_raw'));
        }

        $paid = array_sum(array_column($paymentRows, 'amount_raw'));
        $balance = array_sum(array_column($dueRows, 'balance_raw'));
        if ($balance <= 0 && $assessed > 0) {
            $balance = max(0, $assessed - $paid);
        }

        return [
            'total_assessed' => $this->moneyLabel((float) $assessed),
            'total_paid' => $this->moneyLabel((float) $paid),
            'balance_due' => $this->moneyLabel((float) $balance),
        ];
    }

    protected function dueMergeKey(array $row): string
    {
        return strtolower(trim($row['fee_type'] ?? '')).'|'.($row['due_date'] ?? '');
    }

    protected function formatDate(mixed $date): string
    {
        if (! $date) {
            return '—';
        }
        try {
            return Carbon::parse($date)->format('d-m-Y');
        } catch (\Throwable) {
            return (string) $date;
        }
    }

    protected function sortDate(mixed $date): string
    {
        if (! $date) {
            return '9999-12-31';
        }
        try {
            return Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable) {
            return '9999-12-31';
        }
    }

    protected function mapDueStatus(?string $status, float $balance, mixed $dueDate): string
    {
        if ($balance <= 0) {
            return 'Paid';
        }
        $normalized = $status ?: 'Pending';
        if (strcasecmp($normalized, 'PartiallyPaid') === 0) {
            return 'Partial';
        }
        if (strcasecmp($normalized, 'Overdue') === 0) {
            return 'Overdue';
        }
        try {
            if ($dueDate && Carbon::parse($dueDate)->isPast()) {
                return 'Overdue';
            }
        } catch (\Throwable) {
            // ignore
        }

        return $normalized === 'Pending' ? 'Pending' : ucfirst($normalized);
    }

    protected function displayDueStatus(float $paid, float $balance, mixed $dueDate): string
    {
        if ($balance <= 0) {
            return 'Paid';
        }
        if ($paid > 0) {
            $status = 'Partial';
        } else {
            $status = 'Pending';
        }
        try {
            if ($dueDate && Carbon::parse($dueDate)->isPast()) {
                return 'Overdue';
            }
        } catch (\Throwable) {
            // ignore
        }

        return $status;
    }

    protected function buildFilename(Student $student, Request $request, string $pillLabel, array $paymentRows): string
    {
        $slug = fn (?string $s) => preg_replace('/[^a-zA-Z0-9-]+/', '-', trim($s ?? 'student')) ?: 'student';
        $user = $student->user;
        $name = $slug(trim(($user?->first_name ?? '').'-'.($user?->last_name ?? '')));

        if ($request->filled('payment_id') && ! empty($paymentRows)) {
            $receipt = $slug($paymentRows[0]['receipt_number'] ?? 'payment');

            return "fee-statement-{$name}-payment-{$receipt}.pdf";
        }

        if ($request->filled('fee_due_id') || $request->filled('fee_structure_id')) {
            $type = $slug(str_replace(' — STATEMENT', '', str_replace(' — statement', '', $pillLabel)));

            return "fee-statement-{$name}-{$type}.pdf";
        }

        return "fee-statement-{$name}-all.pdf";
    }
}
