<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Platform;

use App\Application\Platform\Actions\ConfirmTwoFactorAction;
use App\Application\Platform\Actions\PlatformLoginAction;
use App\Application\Platform\Actions\StartTwoFactorSetupAction;
use App\Application\Platform\Actions\VerifyTwoFactorAction;
use App\Application\Platform\Services\PlatformAuditLogger;
use App\Application\Platform\Services\PlatformSessionIssuer;
use App\Domain\Platform\Models\PlatformAdmin;
use App\UI\Http\Requests\Auth\LoginRequest;
use App\UI\Http\Requests\Platform\TwoFactorCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Login del super panel en dos pasos: contraseña → token de desafío (5 min) → código TOTP → token de sesión (8 h).
 */
final class AuthController
{
    public function login(LoginRequest $request, PlatformLoginAction $action): JsonResponse
    {
        $result = $action->execute(
            strtolower($request->string('email')->trim()->toString()),
            $request->string('password')->toString(),
        );

        return response()->json(['data' => [
            ...$result,
            'expires_in' => PlatformLoginAction::CHALLENGE_TTL_MINUTES * 60,
        ]]);
    }

    public function setup(Request $request, StartTwoFactorSetupAction $action): JsonResponse
    {
        return response()->json(['data' => $action->execute($this->admin($request))]);
    }

    public function confirm(TwoFactorCodeRequest $request, ConfirmTwoFactorAction $action): JsonResponse
    {
        $admin = $this->admin($request);
        $result = $action->execute($admin, (string) $request->code(), $this->currentTokenId($admin));

        return response()->json(['data' => [
            ...$this->session($result['token']),
            // Se muestran una sola vez: no se pueden volver a consultar.
            'recovery_codes' => $result['recovery_codes'],
        ]]);
    }

    public function verify(TwoFactorCodeRequest $request, VerifyTwoFactorAction $action): JsonResponse
    {
        $admin = $this->admin($request);
        $token = $action->execute($admin, $request->code(), $request->recoveryCode(), $this->currentTokenId($admin));

        return response()->json(['data' => $this->session($token)]);
    }

    public function me(Request $request): JsonResponse
    {
        $admin = $this->admin($request);

        return response()->json(['data' => [
            'admin' => ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email],
        ]]);
    }

    public function logout(Request $request, PlatformAuditLogger $audit): Response
    {
        $admin = $this->admin($request);
        $admin->tokens()->whereKey($this->currentTokenId($admin))->delete();
        $audit->record('logout', $admin);

        return response()->noContent();
    }

    private function admin(Request $request): PlatformAdmin
    {
        /** @var PlatformAdmin $admin el middleware platform.admin ya lo garantizó */
        $admin = $request->user();

        return $admin;
    }

    private function currentTokenId(PlatformAdmin $admin): int
    {
        return (int) $admin->currentAccessToken()->getKey();
    }

    /**
     * @return array{token: string, token_type: string, expires_in: int}
     */
    private function session(string $token): array
    {
        return ['token' => $token, 'token_type' => 'Bearer', 'expires_in' => PlatformSessionIssuer::TOKEN_TTL_HOURS * 3600];
    }
}
