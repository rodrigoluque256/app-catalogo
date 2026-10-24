<?php

declare(strict_types=1);

namespace App\UI\Http\Middleware;

use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Storefront público: el middleware de Next.js extrae el subdominio (tienda.dominio.com)
 * y lo reenvía en X-Store-Slug, porque la API vive en otro host (api.dominio.com).
 */
final readonly class ResolveTenantFromHeader
{
    public const string HEADER = 'X-Store-Slug';

    public function __construct(
        private TenantContext $tenant,
        private StoreRepositoryInterface $stores,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $slug = $request->header(self::HEADER);

        $store = is_string($slug) && $slug !== '' ? $this->stores->findActiveBySlug($slug) : null;

        if ($store === null) {
            abort(Response::HTTP_NOT_FOUND, 'Tienda no encontrada.');
        }

        $this->tenant->set($store);

        return $next($request);
    }
}
