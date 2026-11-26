<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  $permission  The required permission slug
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! $request->user()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
            ], 401);
        }

        // Branch scope comes from the record in the controller. A client-supplied
        // branch_id must not satisfy this check for a different record.
        if (! $request->user()->hasPermission($permission)) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to perform this action',
            ], 403);
        }

        return $next($request);
    }
}
