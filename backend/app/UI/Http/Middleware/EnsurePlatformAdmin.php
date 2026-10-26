<?php

declare(strict_types=1);

namespace App\UI\Http\Middleware;

use App\Domain\Platform\Models\PlatformAdmin;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API del super panel: solo tokens emitidos a un PlatformAdmin. Un token de tienda (aunque tenga
 * habilidades '*') nunca pasa, y a la inversa `tenant.user` rechaza los tokens de plataforma.
 */
final readonly class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Authenticatable|null $user Sanctum autentica tanto User como PlatformAdmin según el token. */
        $user = $request->user();

        if (! $user instanceof PlatformAdmin) {
            abort(Response::HTTP_FORBIDDEN, 'Acceso solo para administradores de la plataforma.');
        }

        return $next($request);
    }
}
