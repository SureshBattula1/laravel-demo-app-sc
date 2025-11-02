<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class ApiLogger
{
    /**
     * Log all API requests for security audit
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);

        // Log API requests
        $logData = [
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_id' => $request->user()->id ?? null,
            'user_email' => $request->user()->email ?? null,
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration
        ];

        // Log slow requests as warnings
        if ($duration > 1000) {
            Log::channel('api')->warning('Slow API Request', $logData);
        } else {
            Log::channel('api')->info('API Request', $logData);
        }

        // Add performance header
        $response->headers->set('X-Response-Time', $duration . 'ms');

        return $response;
    }
}

