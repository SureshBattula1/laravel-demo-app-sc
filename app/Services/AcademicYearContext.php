<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the active academic year from the request for scoping queries and operations.
 * Sources (in order): query param academic_year_id, header X-Academic-Year-Id, request body, then current year.
 */
class AcademicYearContext
{
    protected ?int $resolvedId = null;

    protected ?AcademicYear $resolvedModel = null;

    public function __construct(
        protected Request $request
    ) {}

    protected function currentUser()
    {
        return $this->request->user() ?? auth()->user();
    }

    /**
     * Resolve academic year id from request (query, header, body) or current. Caches per request.
     */
    public function id(?bool $required = true): ?int
    {
        if ($this->resolvedId !== null) {
            return $this->resolvedId;
        }

        $raw = $this->request->query('academic_year_id')
            ?? $this->request->header('X-Academic-Year-Id')
            ?? $this->request->input('academic_year_id');

        if ($raw !== null && $raw !== '') {
            // Value may arrive as a Hashids token (header bypasses the decode
            // middleware) or as a raw integer. Resolve either form to an int.
            $id = app(\App\Support\IdHasher::class)->decode($raw) ?? (is_numeric($raw) ? (int) $raw : null);
            $model = $id !== null ? AcademicYear::query()->where('id', $id)->first() : null;
            $user = $this->currentUser();
            $companyId = $user?->company_id ? (int) $user->company_id : null;
            if ($model && $companyId && (int) $model->company_id !== $companyId) {
                $model = null;
            }
            if ($model) {
                $this->resolvedId = $id;
                $this->resolvedModel = $model;

                return $this->resolvedId;
            }
        }

        $current = $this->getCurrent();
        if ($current) {
            $this->resolvedId = $current->id;
            $this->resolvedModel = $current;

            return $this->resolvedId;
        }

        if ($required) {
            throw new \RuntimeException('No academic year context available and no current academic year is set.');
        }

        return null;
    }

    /**
     * Get the resolved or current AcademicYear model. Returns null if not required and none set.
     */
    public function model(?bool $required = true): ?AcademicYear
    {
        if ($this->resolvedModel !== null) {
            return $this->resolvedModel;
        }
        $this->id($required);

        return $this->resolvedModel;
    }

    /**
     * Get the current (is_current = true) academic year for this tenant. Cached for the request.
     */
    public function getCurrent(): ?AcademicYear
    {
        $user = $this->currentUser();

        // 1. If student, their academic year is strictly their active enrolled academic year
        if ($user && ($user->role === 'Student' || $user->user_type === 'Student')) {
            $student = \Illuminate\Support\Facades\DB::table('students')
                ->where('user_id', $user->id)
                ->first(['id', 'academic_year_id', 'branch_id', 'school_id']);
            if ($student) {
                $enrollmentAyId = \Illuminate\Support\Facades\DB::table('student_enrollments')
                    ->where('student_id', $student->id)
                    ->where('status', 'Active')
                    ->orderByDesc('id')
                    ->value('academic_year_id');
                $ayId = $enrollmentAyId ?? $student->academic_year_id;
                if ($ayId) {
                    $ay = AcademicYear::find($ayId);
                    if ($ay) {
                        return $ay;
                    }
                }
            }
        }

        // 2. If user is tied to a branch, resolve the active/current year for that branch's school
        if ($user?->branch_id && \Illuminate\Support\Facades\Schema::hasColumn('academic_years', 'school_id')) {
            $schoolId = \Illuminate\Support\Facades\DB::table('branches')->where('id', $user->branch_id)->value('school_id');
            if ($schoolId) {
                $schoolAy = AcademicYear::query()
                    ->where('school_id', $schoolId)
                    ->current()
                    ->active()
                    ->first()
                    ?? AcademicYear::query()
                        ->where('school_id', $schoolId)
                        ->active()
                        ->first();
                if ($schoolAy) {
                    return $schoolAy;
                }
            }
        }

        // 3. Fallback by company_id if available (or via school)
        $companyId = $user?->company_id ? (int) $user->company_id : null;
        if (! $companyId && $user?->branch_id) {
            $companyId = \Illuminate\Support\Facades\DB::table('branches')
                ->join('schools', 'branches.school_id', '=', 'schools.id')
                ->where('branches.id', $user->branch_id)
                ->value('schools.company_id');
        }

        $cacheKey = 'academic_year_current_'.($companyId ?? 'global');

        return Cache::remember($cacheKey, 60, function () use ($companyId) {
            $query = AcademicYear::query()->current()->active();
            if ($companyId) {
                $query->where('company_id', $companyId);
            }

            return $query->first();
        });
    }

    /**
     * Alias for model() for readability in controllers.
     */
    public function current(?bool $required = true): ?AcademicYear
    {
        return $this->model($required);
    }

    /**
     * Whether the given date falls within the resolved/current academic year.
     */
    public function isWithinYear($date): bool
    {
        $model = $this->model(false);

        return $model ? $model->containsDate($date) : false;
    }

    /**
     * Whether the resolved/current academic year has ended (past).
     */
    public function isPast(): bool
    {
        $model = $this->model(false);

        return $model ? $model->isPast() : false;
    }

    /**
     * Whether a specific academic year has ended (by id).
     */
    public function isPastForId(?int $academicYearId): bool
    {
        if ($academicYearId === null) {
            return $this->isPast();
        }

        $model = AcademicYear::query()->find($academicYearId);

        return $model ? $model->isPast() : false;
    }

    /**
     * Throw 422 if the resolved academic year is past (for structural operations like new admissions, fee structure, promotions).
     */
    public function rejectIfPast(string $message = 'This operation is not allowed for past academic years.'): void
    {
        if ($this->isPast()) {
            $this->throwPastYearException($message);
        }
    }

    /**
     * Throw 422 if the given academic year is past.
     * When $academicYearId is null, falls back to toolbar/context resolution (rejectIfPast).
     * Use this when the operation targets an explicit year from the request body (e.g. fee structure form).
     */
    public function rejectIfPastForId(?int $academicYearId, string $message = 'This operation is not allowed for past academic years.'): void
    {
        if ($this->isPastForId($academicYearId)) {
            $this->throwPastYearException($message);
        }
    }

    private function throwPastYearException(string $message): void
    {
        throw new HttpResponseException(
            response()->json(['success' => false, 'message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY)
        );
    }

    /**
     * Clear cached current year (e.g. after setting a new current year).
     */
    public static function clearCurrentCache(?int $companyId = null): void
    {
        if ($companyId) {
            Cache::forget('academic_year_current_'.$companyId);
        }
        Cache::forget('academic_year_current_global');
        Cache::forget('academic_year_current'); // legacy key
    }
}
