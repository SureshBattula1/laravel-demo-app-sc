<?php

namespace App\Services;

use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AssignmentSubmissionService
{
    public function studentIdForUser(?User $user): ?int
    {
        if (! $user || $user->role !== 'Student') {
            return null;
        }

        return Student::where('user_id', $user->id)->value('id');
    }

    public function canStudentSubmit(Request $request, Assignment $assignment, ?int $studentId = null): bool
    {
        $user = $request->user();
        if (! $user || $user->role !== 'Student') {
            return false;
        }
        $studentId = $studentId ?? $this->studentIdForUser($user);
        if (! $studentId || ! $assignment->is_published) {
            return false;
        }

        return $assignment->recipients()->where('student_id', $studentId)->exists();
    }

    /**
     * @return array{submission: AssignmentSubmission, payload: array<string, mixed>}
     */
    public function submitForStudent(
        Assignment $assignment,
        int $studentId,
        ?string $submissionText,
    ): array {
        $existing = AssignmentSubmission::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $studentId)
            ->first();

        if ($existing && $this->isComplete($existing)) {
            throw new \RuntimeException('already_submitted');
        }

        $now = now();
        $status = $this->resolveSubmitStatus($assignment, $now);
        $text = $submissionText !== null && $submissionText !== ''
            ? strip_tags($submissionText)
            : null;

        if ($existing) {
            $existing->fill([
                'submission_text' => $text,
                'submitted_at' => $now,
                'status' => $status,
            ]);
            $existing->save();
            $submission = $existing->fresh();
        } else {
            $submission = AssignmentSubmission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $studentId,
                'submission_text' => $text,
                'submitted_at' => $now,
                'status' => $status,
            ]);
        }

        return [
            'submission' => $submission,
            'payload' => $this->presentSubmission($submission),
        ];
    }

    public function presentSubmission(?AssignmentSubmission $submission): ?array
    {
        if (! $submission) {
            return null;
        }

        return [
            'status' => $submission->status,
            'submitted_at' => optional($submission->submitted_at)?->toIso8601String(),
            'submission_text' => $submission->submission_text,
            'is_complete' => $this->isComplete($submission),
        ];
    }

    public function myStatusKey(?AssignmentSubmission $submission): string
    {
        if (! $submission) {
            return 'pending';
        }

        return match (strtolower((string) $submission->status)) {
            'graded' => 'graded',
            'late' => 'late',
            'submitted' => 'submitted',
            'missing' => 'missing',
            default => $this->isComplete($submission) ? 'submitted' : 'pending',
        };
    }

    /**
     * @param  list<int>  $assignmentIds
     * @return array<int, AssignmentSubmission>
     */
    public function submissionsForStudentAssignments(int $studentId, array $assignmentIds): array
    {
        if ($assignmentIds === []) {
            return [];
        }

        return AssignmentSubmission::query()
            ->where('student_id', $studentId)
            ->whereIn('assignment_id', $assignmentIds)
            ->get()
            ->keyBy('assignment_id')
            ->all();
    }

    private function isComplete(AssignmentSubmission $submission): bool
    {
        if ($submission->submitted_at === null) {
            return false;
        }

        return in_array($submission->status, ['Submitted', 'Graded', 'Late'], true);
    }

    private function resolveSubmitStatus(Assignment $assignment, Carbon $at): string
    {
        $due = $assignment->due_date;
        if ($due && $due->copy()->endOfDay()->lt($at)) {
            return 'Late';
        }

        return 'Submitted';
    }
}
