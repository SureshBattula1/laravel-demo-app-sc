<?php

namespace App\Services;

use App\Models\AcademicYear;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Exceptions\HttpResponseException;
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
    ) {
    }

    /**
     * Resolve academic year id from request (query, header, body) or current. Caches per request.
     */
    public function id(?bool $required = true): ?int
    {
        if ($this->resolvedId !== null) {
            return $this->resolvedId;
        }

        $id = $this->request->query('academic_year_id')
            ?? $this->request->header('X-Academic-Year-Id')
            ?? $this->request->input('academic_year_id');

        if ($id !== null && $id !== '') {
            $id = (int) $id;
            $model = AcademicYear::query()->where('id', $id)->first();
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
     * Get the current (is_current = true) academic year. Cached for the request.
     */
    public function getCurrent(): ?AcademicYear
    {
        return Cache::remember('academic_year_current', 60, function () {
            return AcademicYear::query()->current()->active()->first();
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
     * Throw 422 if the resolved academic year is past (for structural operations like new admissions, fee structure, promotions).
     */
    public function rejectIfPast(string $message = 'This operation is not allowed for past academic years.'): void
    {
        if ($this->isPast()) {
            throw new HttpResponseException(
                response()->json(['success' => false, 'message' => $message], Response::HTTP_UNPROCESSABLE_ENTITY)
            );
        }
    }

    /**
     * Clear cached current year (e.g. after setting a new current year).
     */
    public static function clearCurrentCache(): void
    {
        Cache::forget('academic_year_current');
    }
}
