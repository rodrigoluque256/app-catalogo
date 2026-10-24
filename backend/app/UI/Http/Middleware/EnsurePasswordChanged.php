<?php

declare(strict_types=1);

namespace App\UI\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Quien entra con una contraseña que eligió otra persona (alta o reseteo) no puede usar la API admin
 * hasta cambiarla. Se exige acá y no solo en el panel, para que no se pueda saltear con un cliente HTTP.
 */
final readonly class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->must_change_password) {
            return new JsonResponse([
                'message' => 'Tenés que cambiar tu contraseña antes de continuar.',
                'code' => 'password_change_required',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
