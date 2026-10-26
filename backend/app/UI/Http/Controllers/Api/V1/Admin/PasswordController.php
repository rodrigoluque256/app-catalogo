<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\Auth\Actions\AcceptInvitationAction;
use App\Application\Auth\Actions\LoginAction;
use App\Application\Auth\Actions\ResetPasswordAction;
use App\Application\Auth\Actions\SendPasswordResetLinkAction;
use App\Domain\Auth\Exceptions\InvalidAccessLinkException;
use App\UI\Http\Requests\Auth\AccessLinkRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Accesos por email al panel de la tienda: "olvidé mi contraseña" e invitaciones.
 */
final class PasswordController
{
    /** Siempre la misma respuesta: no revela si el email existe. */
    public function forgot(Request $request, SendPasswordResetLinkAction $action): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:255']]);

        $action->execute(strtolower(trim($request->string('email')->toString())));

        return response()->json([
            'message' => 'Si el email está registrado, te enviamos un link para restablecer la contraseña.',
        ], Response::HTTP_ACCEPTED);
    }

    public function reset(AccessLinkRequest $request, ResetPasswordAction $action): Response
    {
        $action->execute($request->string('email')->toString(), $request->string('token')->toString(), $request->string('password')->toString());

        return response()->noContent();
    }

    /** Valida el link antes de mostrar el formulario (sin consumirlo) y dice a qué tienda se entra. */
    public function invitation(Request $request, AcceptInvitationAction $action): JsonResponse
    {
        $store = $action->storeName(
            strtolower(trim($request->string('email')->toString())),
            $request->string('token')->toString(),
        );

        if ($store === null) {
            throw InvalidAccessLinkException::invitation();
        }

        return response()->json(['data' => ['store' => $store]]);
    }

    public function accept(AccessLinkRequest $request, AcceptInvitationAction $action): JsonResponse
    {
        $token = $action->execute($request->string('email')->toString(), $request->string('token')->toString(), $request->string('password')->toString());

        return response()->json(['data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => LoginAction::TOKEN_TTL_HOURS * 3600,
        ]]);
    }
}
