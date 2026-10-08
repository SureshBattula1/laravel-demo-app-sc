<?php

namespace App\Services\Library;

use App\Models\Book;
use App\Models\BookIssue;
use App\Models\LibraryBookCopy;
use App\Models\LibraryFine;
use App\Models\LibraryReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LibraryCirculationService
{
    /** Default circulation loan policies */
    public const DEFAULT_RULES = [
        'Student' => ['days' => 14, 'max' => 3, 'fine_per_day' => 5, 'max_renewals' => 2],
        'Teacher' => ['days' => 30, 'max' => 10, 'fine_per_day' => 5, 'max_renewals' => 3],
    ];

    /**
     * Issue a book or a specific copy to a student/teacher.
     */
    public function issue(
        int $branchId,
        ?int $schoolId,
        int $bookId,
        ?int $copyId,
        int $memberId,
        string $borrowerType,
        ?string $dueDate = null,
        ?string $issueDate = null,
        ?string $remarks = null,
        ?int $academicYearId = null
    ): BookIssue {
        return DB::transaction(function () use (
            $branchId, $schoolId, $bookId, $copyId, $memberId,
            $borrowerType, $dueDate, $issueDate, $remarks, $academicYearId
        ) {
            $book = Book::withoutTenantScope()->lockForUpdate()->findOrFail($bookId);
            $rules = self::DEFAULT_RULES[$borrowerType] ?? self::DEFAULT_RULES['Student'];
            $memberColumn = $borrowerType === 'Teacher' ? 'teacher_id' : 'student_id';

            // Check if member already holds this book
            $alreadyIssued = BookIssue::withoutTenantScope()
                ->where('book_id', $book->id)
                ->where($memberColumn, $memberId)
                ->where('status', 'Issued')
                ->exists();
            if ($alreadyIssued) {
                throw ValidationException::withMessages([
                    'book_id' => ['This member already has an active loan for this book.'],
                ]);
            }

            // Check borrowing limit
            $activeCount = BookIssue::withoutTenantScope()
                ->where($memberColumn, $memberId)
                ->where('branch_id', $branchId)
                ->where('status', 'Issued')
                ->count();
            if ($activeCount >= $rules['max']) {
                throw ValidationException::withMessages([
                    'member_id' => ["Borrowing limit reached ({$rules['max']} books for {$borrowerType})."],
                ]);
            }

            // Find or assign specific copy
            $copy = null;
            if ($copyId) {
                $copy = LibraryBookCopy::withoutTenantScope()->lockForUpdate()->findOrFail($copyId);
                if ($copy->status !== 'Available') {
                    throw ValidationException::withMessages([
                        'copy_id' => ["Copy {$copy->barcode} is currently {$copy->status} and cannot be issued."],
                    ]);
                }
            } else {
                // Pick first available copy
                $copy = LibraryBookCopy::withoutTenantScope()
                    ->where('book_id', $book->id)
                    ->where('branch_id', $branchId)
                    ->where('status', 'Available')
                    ->lockForUpdate()
                    ->first();
            }

            if (! $copy && (int) $book->available_copies <= 0) {
                throw ValidationException::withMessages([
                    'book_id' => ['No copies available to issue.'],
                ]);
            }

            $issueDateCarbon = $issueDate ? Carbon::parse($issueDate) : Carbon::today();
            $dueDateCarbon = $dueDate
                ? Carbon::parse($dueDate)
                : (clone $issueDateCarbon)->addDays($rules['days']);

            $issue = BookIssue::create([
                'book_id' => $book->id,
                'copy_id' => $copy?->id,
                'branch_id' => $branchId,
                'school_id' => $schoolId ?? $book->school_id,
                'academic_year_id' => $academicYearId,
                'student_id' => $borrowerType === 'Student' ? $memberId : null,
                'teacher_id' => $borrowerType === 'Teacher' ? $memberId : null,
                'borrower_type' => $borrowerType,
                'issue_date' => $issueDateCarbon->toDateString(),
                'due_date' => $dueDateCarbon->toDateString(),
                'status' => 'Issued',
                'renewed_count' => 0,
                'fine_amount' => 0,
                'remarks' => $remarks,
            ]);

            if ($copy) {
                $copy->update(['status' => 'Issued']);
            }
            $book->decrement('available_copies');

            // Fulfill reservation if this member had one
            LibraryReservation::withoutTenantScope()
                ->where('book_id', $book->id)
                ->where('member_id', $memberId)
                ->whereIn('status', ['Pending', 'Ready for Pickup'])
                ->update(['status' => 'Fulfilled']);

            return $issue;
        });
    }

    /**
     * Return an issued book copy, calculate fine, and handle reservation holding.
     */
    public function return(
        int $issueId,
        ?string $returnDate = null,
        bool $collectFine = true,
        ?string $copyCondition = null,
        ?string $remarks = null,
        ?int $collectedBy = null,
        ?string $paymentMethod = 'Cash'
    ): array {
        return DB::transaction(function () use (
            $issueId, $returnDate, $collectFine, $copyCondition, $remarks, $collectedBy, $paymentMethod
        ) {
            $issue = BookIssue::withoutTenantScope()->with(['book', 'copy'])->lockForUpdate()->findOrFail($issueId);

            if ($issue->status === 'Returned') {
                throw ValidationException::withMessages([
                    'issue_id' => ['Book has already been returned.'],
                ]);
            }

            $returnDateCarbon = $returnDate ? Carbon::parse($returnDate) : Carbon::today();
            $dueDateCarbon = Carbon::parse($issue->due_date);

            $fineAmount = 0;
            if ($returnDateCarbon->gt($dueDateCarbon)) {
                $daysLate = $dueDateCarbon->diffInDays($returnDateCarbon);
                $rules = self::DEFAULT_RULES[$issue->borrower_type] ?? self::DEFAULT_RULES['Student'];
                $fineAmount = $daysLate * $rules['fine_per_day'];
            }

            $isFineCollected = $fineAmount > 0 && $collectFine;

            $issue->update([
                'return_date' => $returnDateCarbon->toDateString(),
                'status' => 'Returned',
                'fine_amount' => $fineAmount,
                'fine_paid' => $isFineCollected,
                'fine_paid_at' => $isFineCollected ? now() : null,
                'remarks' => $remarks ?? $issue->remarks,
            ]);

            // Create fine ledger record if fine > 0
            if ($fineAmount > 0) {
                $memberId = $issue->student_id ?? $issue->teacher_id;
                LibraryFine::create([
                    'school_id' => $issue->school_id,
                    'branch_id' => $issue->branch_id,
                    'book_issue_id' => $issue->id,
                    'member_id' => $memberId,
                    'borrower_type' => $issue->borrower_type,
                    'type' => 'Late Return',
                    'amount' => $fineAmount,
                    'paid_amount' => $isFineCollected ? $fineAmount : 0,
                    'waived_amount' => 0,
                    'status' => $isFineCollected ? 'Paid' : 'Pending',
                    'payment_method' => $isFineCollected ? $paymentMethod : null,
                    'collected_by' => $isFineCollected ? $collectedBy : null,
                    'paid_at' => $isFineCollected ? now() : null,
                ]);
            }

            // Check if there is an active reservation waiting for this book
            $nextReservation = LibraryReservation::withoutTenantScope()
                ->where('book_id', $issue->book_id)
                ->where('status', 'Pending')
                ->orderBy('reserved_at', 'asc')
                ->first();

            if ($issue->copy) {
                $newStatus = $nextReservation ? 'Reserved' : 'Available';
                $copyUpdates = ['status' => $newStatus];
                if ($copyCondition) {
                    $copyUpdates['condition'] = $copyCondition;
                }
                $issue->copy->update($copyUpdates);
            }

            if ($nextReservation) {
                $nextReservation->update([
                    'copy_id' => $issue->copy_id,
                    'status' => 'Ready for Pickup',
                    'hold_until' => Carbon::now()->addHours(48),
                    'notified_at' => Carbon::now(),
                ]);
            }

            if ($issue->book) {
                $issue->book->increment('available_copies');
            }

            return [
                'issue' => $issue->fresh(['book', 'copy', 'branch']),
                'fine_amount' => $fineAmount,
                'fine_paid' => $isFineCollected,
                'reservation_held' => $nextReservation !== null,
            ];
        });
    }

    /**
     * Renew an issued book loan.
     */
    public function renew(int $issueId, ?int $additionalDays = null): BookIssue
    {
        return DB::transaction(function () use ($issueId, $additionalDays) {
            $issue = BookIssue::withoutTenantScope()->lockForUpdate()->findOrFail($issueId);

            if ($issue->status !== 'Issued') {
                throw ValidationException::withMessages([
                    'issue_id' => ['Only actively issued books can be renewed.'],
                ]);
            }

            $rules = self::DEFAULT_RULES[$issue->borrower_type] ?? self::DEFAULT_RULES['Student'];
            if (($issue->renewed_count ?? 0) >= $rules['max_renewals']) {
                throw ValidationException::withMessages([
                    'issue_id' => ["Maximum renewal limit reached ({$rules['max_renewals']} renewals)."],
                ]);
            }

            // Check if someone has reserved this book
            $hasReservation = LibraryReservation::withoutTenantScope()
                ->where('book_id', $issue->book_id)
                ->where('status', 'Pending')
                ->exists();
            if ($hasReservation) {
                throw ValidationException::withMessages([
                    'issue_id' => ['Cannot renew this book because another member has reserved it.'],
                ]);
            }

            $days = $additionalDays ?: $rules['days'];
            $currentDue = Carbon::parse($issue->due_date);
            $newDue = $currentDue->isPast() ? Carbon::today()->addDays($days) : $currentDue->addDays($days);

            $issue->update([
                'due_date' => $newDue->toDateString(),
                'renewed_count' => ($issue->renewed_count ?? 0) + 1,
                'last_renewed_at' => now(),
            ]);

            return $issue->fresh(['book', 'copy', 'branch']);
        });
    }
}
