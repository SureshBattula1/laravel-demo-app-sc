<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompanyAuthMiddleware
{
    /**
     * Handle an incoming request - Ensure user is a company admin
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Check if user is company admin or support staff
        if (!in_array($user->user_type, ['CompanyAdmin', 'SupportStaff'])) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. Company portal access required.'
            ], 403);
        }

        // Check if user is active
        if (!$user->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive'
            ], 403);
        }

        return $next($request);
    }
}

