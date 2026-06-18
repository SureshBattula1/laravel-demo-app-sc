<?php

namespace App\Http\Middleware;

use App\Services\AcademicYearContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves academic year from request and attaches it to request attributes
 * so controllers can use $request->attributes->get('academic_year_id').
 * Does not throw if no current year; leaves attribute null.
 */
class SetAcademicYearContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(AcademicYearContext::class);
        try {
            $id = $context->id(false);
            $request->attributes->set('academic_year_id', $id);
        } catch (\Throwable $e) {
            $request->attributes->set('academic_year_id', null);
        }
        return $next($request);
    }
}
