<?php

declare(strict_types=1);

use App\UI\Http\Controllers\Api\V1\Admin\AuthController;
use App\UI\Http\Controllers\Api\V1\Admin\CategoryController;
use App\UI\Http\Controllers\Api\V1\Admin\ProductController;
use App\UI\Http\Controllers\Api\V1\Admin\StoreSettingsController;
use App\UI\Http\Controllers\Api\V1\Admin\UploadController;
use App\UI\Http\Controllers\Api\V1\Admin\UserController;
use App\UI\Http\Controllers\Api\V1\Platform\AuthController as PlatformAuthController;
use App\UI\Http\Controllers\Api\V1\Storefront\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/auth')->group(function (): void {
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    Route::get('me', [AuthController::class, 'me'])->middleware(['auth:sanctum', 'tenant.user']);
    Route::post('password', [AuthController::class, 'changePassword'])->middleware(['auth:sanctum', 'tenant.user', 'throttle:10,1']);
});

Route::prefix('v1/admin')
    ->middleware(['auth:sanctum', 'tenant.user', 'password.changed'])
    ->group(function (): void {
        Route::apiResource('products', ProductController::class)
            ->middleware('can:products.manage');
        Route::apiResource('categories', CategoryController::class)
            ->middleware('can:products.manage');

        // El permiso depende del propósito, así que lo valida PresignUploadRequest.
        Route::post('uploads/presign', [UploadController::class, 'presign']);

        Route::middleware('can:users.manage')->group(function (): void {
            Route::apiResource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
            Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword']);
        });

        Route::middleware('can:store.manage')->group(function (): void {
            Route::get('store', [StoreSettingsController::class, 'show']);
            Route::patch('store', [StoreSettingsController::class, 'update']);
        });
    });

Route::prefix('v1/storefront')
    ->middleware('tenant.header')
    ->group(function (): void {
        Route::get('store', [StorefrontController::class, 'store']);
        Route::get('categories', [StorefrontController::class, 'categories']);
        Route::get('products', [StorefrontController::class, 'products']);
        Route::get('products/{slug}', [StorefrontController::class, 'product']);
    });

// Super panel: identidad, tokens y throttle propios. Ver EnsurePlatformAdmin.
Route::prefix('v1/platform/auth')->group(function (): void {
    Route::post('login', [PlatformAuthController::class, 'login'])->middleware('throttle:platform-login');

    // Solo con el token de desafío (contraseña correcta, 2FA pendiente). El throttle va donde se prueban códigos.
    Route::middleware(['auth:sanctum', 'platform.admin', 'abilities:platform:challenge'])
        ->prefix('two-factor')
        ->group(function (): void {
            Route::post('setup', [PlatformAuthController::class, 'setup']);
            Route::post('confirm', [PlatformAuthController::class, 'confirm'])->middleware('throttle:platform-2fa');
            Route::post('verify', [PlatformAuthController::class, 'verify'])->middleware('throttle:platform-2fa');
        });

    Route::middleware(['auth:sanctum', 'platform.admin', 'abilities:platform'])->group(function (): void {
        Route::get('me', [PlatformAuthController::class, 'me']);
        Route::post('logout', [PlatformAuthController::class, 'logout']);
    });
});
