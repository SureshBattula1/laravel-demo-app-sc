<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Branch;

class BranchAccessMiddleware
{
    /**
     * Handle an incoming request - Ensure user has access to requested branch
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

        // SuperAdmin has access to all branches
        if ($user->role === 'SuperAdmin') {
            return $next($request);
        }

        // Get branch ID from request (query param, route param, or body)
        $requestedBranchId = $request->route('branch_id') 
            ?? $request->input('branch_id') 
            ?? $request->query('branch_id');

        // If no specific branch requested, allow (will be filtered in controller)
        if (!$requestedBranchId) {
            return $next($request);
        }

        // BranchAdmin can access their branch and all descendant branches
        if ($user->role === 'BranchAdmin') {
            $userBranch = Branch::find($user->branch_id);
            
            if ($userBranch) {
                $accessibleBranchIds = $userBranch->getDescendantIds();
                
                if (in_array($requestedBranchId, $accessibleBranchIds)) {
                    return $next($request);
                }
            }
        }

        // Teacher, Student, Parent, Staff can only access their own branch
        if ($user->branch_id == $requestedBranchId) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'You do not have access to this branch'
        ], 403);
    }
}

