<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Branch;

class EnforceActiveBranch
{
    /**
     * Ensure the user's branch is active
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

        // SuperAdmin is always allowed
        if ($user->role === 'SuperAdmin') {
            return $next($request);
        }

        // Check if user's branch is active
        if ($user->branch_id) {
            $branch = Branch::find($user->branch_id);
            
            if (!$branch || !$branch->isActive()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your branch is currently inactive. Please contact administration.',
                    'branch_status' => $branch ? $branch->status : 'Unknown'
                ], 403);
            }
        }

        return $next($request);
    }
}

