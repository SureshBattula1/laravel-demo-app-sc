<?php

namespace App\Http\Middleware;

use App\Services\SystemAuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuditActivityMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);

        try {
            app(SystemAuditLogger::class)->capturePreRequestSnapshot($request);
        } catch (\Throwable) {
            // Never block a request on pre-snapshot
        }

        $response = $next($request);

        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);

        try {
            app(SystemAuditLogger::class)->finalizeHttpRequest($request, $response, $durationMs);
        } catch (\Throwable) {
            // Never fail a user request due to audit telemetry
        }

        return $response;
    }
}
