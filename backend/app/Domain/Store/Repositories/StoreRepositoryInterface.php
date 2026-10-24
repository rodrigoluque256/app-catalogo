<?php

declare(strict_types=1);

namespace App\Domain\Store\Repositories;

use App\Domain\Store\Models\Store;

interface StoreRepositoryInterface
{
    public function findActiveById(string $id): ?Store;

    public function findActiveBySlug(string $slug): ?Store;

    /**
     * @param  array<string, mixed>  $attributes  columnas de configuración ya validadas
     */
    public function update(Store $store, array $attributes): Store;
}
