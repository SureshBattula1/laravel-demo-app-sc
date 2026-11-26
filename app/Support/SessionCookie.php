<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SessionCookie
{
    public const SCHOOL = 'school_session';

    public const COMPANY = 'company_session';

    public static function sign(string $token): string
    {
        $signature = hash_hmac('sha256', $token, (string) config('app.key'));

        return $signature.'.'.$token;
    }

    public static function verify(?string $value): ?string
    {
        if (! is_string($value) || ! str_contains($value, '.')) {
            return null;
        }

        [$signature, $token] = explode('.', $value, 2);
        if ($token === '') {
            return null;
        }

        $expected = hash_hmac('sha256', $token, (string) config('app.key'));
        if (! hash_equals($expected, $signature)) {
            return null;
        }

        return $token;
    }

    public static function attach(Response $response, Request $request, string $name, string $token, int $minutes): Response
    {
        return $response->cookie(
            $name,
            self::sign($token),
            $minutes,
            '/',
            null,
            $request->isSecure(),
            true,
            false,
            'lax'
        );
    }

    public static function clear(Response $response, string $name): Response
    {
        return $response->withoutCookie($name, '/');
    }
}
