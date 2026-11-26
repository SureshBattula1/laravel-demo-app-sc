<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('api')
                ->prefix('api/company-portal')
                ->group(base_path('routes/company-portal.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware
        $middleware->alias([
            'role' => \App\Http\Middleware\CheckRole::class,
            'permission' => \App\Http\Middleware\CheckPermission::class,
            'branch.access' => \App\Http\Middleware\CheckBranchAccess::class,
            'api.logger' => \App\Http\Middleware\ApiLogger::class,
            'company.auth' => \App\Http\Middleware\CompanyAuthMiddleware::class,
            'academic_year.context' => \App\Http\Middleware\SetAcademicYearContext::class,
            'school.account.active' => \App\Http\Middleware\EnsureSchoolAccountActive::class,
        ]);

        $trustedProxies = env('TRUSTED_PROXIES');
        if (is_string($trustedProxies) && $trustedProxies !== '') {
            $middleware->trustProxies(at: array_map('trim', explode(',', $trustedProxies)));
        }

        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Enable CORS for API routes; attach academic year context for scoping.
        // EncodeHashids is prepended (outermost) so it transforms the final JSON
        // response; DecodeHashids is appended (innermost) so it rewrites incoming
        // tokens to integer IDs right before controllers run.
        $middleware->api(
            prepend: [
                \Illuminate\Http\Middleware\HandleCors::class,
                \App\Http\Middleware\AttachSessionCookie::class,
                \App\Http\Middleware\EncodeHashids::class,
            ],
            append: [
                // Decode incoming ID tokens first, so the academic-year context
                // (and controllers) see integer IDs.
                \App\Http\Middleware\DecodeHashids::class,
                \App\Http\Middleware\SetAcademicYearContext::class,
                \App\Http\Middleware\AuditActivityMiddleware::class,
            ]
        );

        // Disable CSRF for API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(function (Request $request) {
            return $request->is('api/*') || $request->expectsJson();
        });

        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($e instanceof ValidationException || $e instanceof AuthenticationException || $e instanceof HttpExceptionInterface) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Server error occurred',
            ], 500);
        });
    })->create();
