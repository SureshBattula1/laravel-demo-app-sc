<?php

namespace App\Http\Middleware;

use App\Support\SessionCookie;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AttachSessionCookie
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->bearerToken()) {
            $name = $request->is('api/company-portal') || $request->is('api/company-portal/*')
                ? SessionCookie::COMPANY
                : SessionCookie::SCHOOL;
            $token = SessionCookie::verify($request->cookie($name));
            if ($token !== null) {
                $request->headers->set('Authorization', 'Bearer '.$token);
            }
        }

        return $next($request);
    }
}
