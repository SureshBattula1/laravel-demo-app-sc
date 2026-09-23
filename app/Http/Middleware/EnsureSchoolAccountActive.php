<?php

namespace App\Http\Middleware;

use App\Services\SchoolAccountAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchoolAccountActive
{
    public function __construct(
        protected SchoolAccountAccessService $accountAccess
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $token = $user->currentAccessToken();
        if ($token && (
            (method_exists($token, 'can') && $token->can('impersonated'))
            || (($token->name ?? null) === 'impersonation_token')
        )) {
            return $next($request);
        }

        if ($this->accountAccess->isBlocked($user)) {
            return $this->accountAccess->deniedResponse();
        }

        return $next($request);
    }
}
