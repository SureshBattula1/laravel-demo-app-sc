<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBranchAccess
{
    /**
     * Ensure user can only access their branch data
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated'
            ], 401);
        }

        // SuperAdmin can access all branches
        if ($user->role === 'SuperAdmin') {
            return $next($request);
        }

        // Get requested branch_id from request
        $requestedBranchId = $request->input('branch_id') 
            ?? $request->route('branch_id') 
            ?? $request->route('id');

        // If branch_id is in request, verify access
        if ($requestedBranchId && $user->branch_id != $requestedBranchId) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. You can only access your branch data.'
            ], 403);
        }

        return $next($request);
    }
}

