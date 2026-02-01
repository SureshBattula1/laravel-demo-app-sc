<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyPortal\CompanyAuthController;
use App\Http\Controllers\CompanyPortal\CompanyController;
use App\Http\Controllers\CompanyPortal\SchoolController;
use App\Http\Controllers\CompanyPortal\CompanyAdminController;
use App\Http\Controllers\CompanyPortal\ImpersonationController;

/*
|--------------------------------------------------------------------------
| Company Portal API Routes
|--------------------------------------------------------------------------
|
| Separate routes for company portal with enhanced security
|
*/

// Public routes (authentication)
Route::post('/login', [CompanyAuthController::class, 'login']);

// Protected routes with company portal authentication
Route::middleware(['auth:sanctum', 'company.auth', 'throttle:120,1'])->group(function () {
    
    // Authentication routes
    Route::prefix('auth')->group(function () {
        Route::get('/me', [CompanyAuthController::class, 'me']);
        Route::post('/logout', [CompanyAuthController::class, 'logout']);
        Route::put('/profile', [CompanyAuthController::class, 'updateProfile']);
        Route::put('/change-password', [CompanyAuthController::class, 'changePassword']);
    });
    
    // Company management routes (for super admin or multi-company admins)
    Route::prefix('companies')->group(function () {
        Route::get('/', [CompanyController::class, 'index']);
        Route::post('/', [CompanyController::class, 'store']);
        Route::get('/{id}', [CompanyController::class, 'show']);
        Route::put('/{id}', [CompanyController::class, 'update']);
        Route::delete('/{id}', [CompanyController::class, 'destroy']);
    });
    
    // School management routes
    Route::prefix('schools')->group(function () {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('/{id}', [SchoolController::class, 'show']);
        Route::put('/{id}', [SchoolController::class, 'update']);
        Route::delete('/{id}', [SchoolController::class, 'destroy']);
        Route::put('/{id}/activate', [SchoolController::class, 'activate']);
        Route::put('/{id}/deactivate', [SchoolController::class, 'deactivate']);
        Route::get('/{id}/statistics', [SchoolController::class, 'statistics']);
    });
    
    // Company admin management routes
    Route::prefix('admins')->group(function () {
        Route::get('/', [CompanyAdminController::class, 'index']);
        Route::post('/', [CompanyAdminController::class, 'store']);
        Route::put('/{id}', [CompanyAdminController::class, 'update']);
        Route::delete('/{id}', [CompanyAdminController::class, 'destroy']);
    });
    
    // Virtual onboarding / Impersonation routes
    Route::prefix('impersonate')->group(function () {
        Route::post('/{userId}', [ImpersonationController::class, 'startImpersonation']);
        Route::post('/stop', [ImpersonationController::class, 'stopImpersonation']);
        Route::get('/sessions/active', [ImpersonationController::class, 'getActiveSessions']);
        Route::get('/sessions/history', [ImpersonationController::class, 'getSessionHistory']);
        Route::post('/log-action', [ImpersonationController::class, 'logAction']);
    });
});

