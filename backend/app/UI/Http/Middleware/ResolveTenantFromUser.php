<?php

declare(strict_types=1);

namespace App\UI\Http\Middleware;

use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Panel admin: el tenant es la tienda del usuario autenticado. Nunca se toma de la URL ni de headers,
 * así un usuario no puede "cambiarse" de tienda manipulando el request.
 */
final readonly class ResolveTenantFromUser
{
    public function __construct(
        private TenantContext $tenant,
        private StoreRepositoryInterface $stores,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        $store = $user instanceof User && $user->store_id !== null
            ? $this->stores->findActiveById($user->store_id)
            : null;

        if ($store === null) {
            abort(Response::HTTP_FORBIDDEN, 'El usuario no pertenece a una tienda activa.');
        }

        $this->tenant->set($store);
        setPermissionsTeamId($store->id);

        return $next($request);
    }
}
