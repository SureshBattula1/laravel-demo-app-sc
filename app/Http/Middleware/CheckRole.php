<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckRole
{
    /**
     * Handle role-based access control
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!$request->user()) {
            Log::warning('Unauthorized access attempt', [
                'ip' => $request->ip(),
                'route' => $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!in_array($request->user()->role, $roles)) {
            Log::warning('Forbidden access attempt', [
                'user_id' => $request->user()->id,
                'user_role' => $request->user()->role,
                'required_roles' => $roles,
                'route' => $request->path()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Forbidden. Insufficient permissions.'
            ], 403);
        }

        return $next($request);
    }
}
