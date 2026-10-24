<?php

declare(strict_types=1);

namespace App\Domain\Product\Repositories;

use App\Application\Product\DTOs\CreateProductData;
use App\Application\Product\DTOs\ProductFilters;
use App\Application\Product\DTOs\UpdateProductData;
use App\Domain\Product\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Toda implementación opera exclusivamente sobre la tienda del TenantContext actual.
 */
interface ProductRepositoryInterface
{
    public function findById(string $id): ?Product;

    public function findBySlug(string $slug): ?Product;

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function paginate(ProductFilters $filters): LengthAwarePaginator;

    public function slugExists(string $slug, ?string $ignoreProductId = null): bool;

    /**
     * SKUs de la lista que ya usa algún producto o variante de la tienda.
     *
     * @param  list<string>  $skus
     * @return list<string>
     */
    public function existingSkus(array $skus, ?string $ignoreProductId = null): array;

    public function create(CreateProductData $data): Product;

    public function update(Product $product, UpdateProductData $data): Product;

    public function delete(Product $product): void;
}
