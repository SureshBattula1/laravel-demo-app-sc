<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\StudentExamProgressCardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class StudentExamProgressCardController extends Controller
{
    public function __construct(
        protected StudentExamProgressCardService $progressCardService
    ) {}

    /**
     * Download exam progress card PDF for a student (studentUserId = users.id).
     *
     * Query: scope=all | exam_id | mark_id
     */
    public function download(Request $request, int $studentUserId)
    {
        try {
            if (! $this->canAccessStudentMarks($request, $studentUserId)) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
            }

            $student = $this->resolveStudent($studentUserId);
            $effectiveUserId = $student ? (int) $student->user_id : $studentUserId;

            $result = $this->progressCardService->generatePdf($request, $effectiveUserId);
            $pdf = $result['pdf'];
            $filename = $result['filename'];

            return $pdf->download($filename);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            throw $e;
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['success' => false, 'message' => 'Student not found'], 404);
        } catch (\Exception $e) {
            Log::error('Progress card PDF error', [
                'student_user_id' => $studentUserId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate progress card',
            ], 500);
        }
    }

    protected function resolveStudent(int $studentUserId): ?Student
    {
        $student = Student::where('user_id', $studentUserId)
            ->whereNull('deleted_at')
            ->first();

        if (! $student) {
            $student = Student::whereNull('deleted_at')->find($studentUserId);
        }

        return $student;
    }

    protected function canAccessStudentMarks(Request $request, int $studentUserId): bool
    {
        $user = $request->user();
        if (! $user) {
            return false;
        }

        if ((int) $user->id === $studentUserId) {
            return true;
        }

        $student = $this->resolveStudent($studentUserId);
        if (! $student) {
            return false;
        }

        $branchId = (int) $student->branch_id;

        if ($user->isSuperAdmin()) {
            $accessibleBranchIds = $this->getAccessibleBranchIds($request);
            if ($accessibleBranchIds === 'all') {
                $schoolId = $this->getCurrentSchoolId($request);
                if ($schoolId && ! empty($student->school_id)) {
                    return (int) $student->school_id === (int) $schoolId;
                }

                return true;
            }

            return in_array($branchId, array_map('intval', (array) $accessibleBranchIds), true);
        }

        if ($this->canAccessBranch($request, $branchId)) {
            return true;
        }

        return $user->hasAnyPermission(
            ['students.view', 'exams.view', 'exams.results'],
            $branchId
        );
    }
}
