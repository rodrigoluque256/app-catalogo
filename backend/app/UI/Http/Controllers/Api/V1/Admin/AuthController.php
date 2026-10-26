<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\Auth\Actions\ChangePasswordAction;
use App\Application\Auth\Actions\LoginAction;
use App\Application\Billing\Services\PlanLimits;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Models\User;
use App\UI\Http\Requests\Auth\ChangePasswordRequest;
use App\UI\Http\Requests\Auth\LoginRequest;
use App\UI\Http\Resources\PlanResource;
use App\UI\Http\Resources\StoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class AuthController
{
    public function login(LoginRequest $request, LoginAction $action): JsonResponse
    {
        $result = $action->execute(
            strtolower($request->string('email')->trim()->toString()),
            $request->string('password')->toString(),
        );

        return response()->json(['data' => [
            'token' => $result['token'],
            'token_type' => 'Bearer',
            'expires_in' => LoginAction::TOKEN_TTL_HOURS * 3600,
        ]]);
    }

    /**
     * Usuario, su tienda y lo que puede hacer (el panel oculta secciones según `permissions`).
     */
    public function me(Request $request, TenantContext $tenant, PlanLimits $limits): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'must_change_password' => $user->must_change_password],
            'store' => StoreResource::make($tenant->store())->resolve($request),
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            // Límites del plan: el panel deshabilita lo que no se puede agregar (la API igual lo valida).
            'plan' => PlanResource::make($limits->planOf($tenant->store()))->resolve($request),
        ]]);
    }

    public function changePassword(ChangePasswordRequest $request, ChangePasswordAction $action): Response
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute(
            $user,
            $request->string('current_password')->toString(),
            $request->string('password')->toString(),
            $user->currentAccessToken()->getKey(),
        );

        return response()->noContent();
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()->delete();

        return response()->noContent();
    }
}
