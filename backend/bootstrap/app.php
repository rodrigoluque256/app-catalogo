<?php

declare(strict_types=1);

use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Platform\Exceptions\StoreNotFoundException;
use App\Domain\Product\Exceptions\CategoryRecordNotFoundException;
use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Shared\Exceptions\DomainValidationException;
use App\UI\Console\Commands\PlatformAdminCommand;
use App\UI\Http\Middleware\EnsurePasswordChanged;
use App\UI\Http\Middleware\EnsurePlatformAdmin;
use App\UI\Http\Middleware\ResolveTenantFromHeader;
use App\UI\Http\Middleware\ResolveTenantFromUser;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Sanctum\Http\Middleware\CheckAbilities;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([PlatformAdminCommand::class])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'tenant.user' => ResolveTenantFromUser::class,
            'tenant.header' => ResolveTenantFromHeader::class,
            'password.changed' => EnsurePasswordChanged::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'abilities' => CheckAbilities::class,
        ]);

        // El team de Spatie (= store) debe estar fijado antes de evaluar `can:`.
        $middleware->prependToPriorityList(Authorize::class, ResolveTenantFromUser::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request): bool => $request->is('api/*') || $request->expectsJson(),
        );

        // Mismo formato que la validación de Laravel, así el front maneja un solo shape de error.
        $exceptions->render(fn (DomainValidationException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
            'errors' => [$e->field() => [$e->getMessage()]],
        ], 422));

        $exceptions->render(fn (UserNotFoundException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
        ], 404));

        $exceptions->render(fn (CategoryRecordNotFoundException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
        ], 404));

        $exceptions->render(fn (ProductNotFoundException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
        ], 404));

        $exceptions->render(fn (StoreNotFoundException $e): JsonResponse => new JsonResponse([
            'message' => $e->getMessage(),
        ], 404));
    })->create();
