<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
        ]);
        
        // Enable CORS for API routes; attach academic year context for scoping
        $middleware->api(
            prepend: [
                \Illuminate\Http\Middleware\HandleCors::class,
            ],
            append: [
                \App\Http\Middleware\SetAcademicYearContext::class,
            ]
        );
        
        // Disable CSRF for API routes
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
