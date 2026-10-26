<?php

declare(strict_types=1);

namespace App\Domain\Platform\Repositories;

use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Tiendas vistas desde el super panel: todas, sin tenant, incluidas las borradas.
 */
interface PlatformStoreRepositoryInterface
{
    /**
     * @param  'active'|'suspended'|'deleted'|null  $status  null = todas las no borradas
     * @return LengthAwarePaginator<int, Store>
     */
    public function paginate(?string $search, ?string $status, int $perPage): LengthAwarePaginator;

    /** Incluye borradas, con dueños y conteos cargados. */
    public function find(string $id): ?Store;

    public function create(string $name, string $slug): Store;

    public function setStatus(Store $store, StoreStatus $status): void;

    public function delete(Store $store): void;

    public function restore(Store $store): void;

    /** Cierra las sesiones de todos los usuarios de la tienda. */
    public function revokeUserTokens(Store $store): void;
}
