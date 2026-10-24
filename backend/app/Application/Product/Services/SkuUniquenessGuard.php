<?php

declare(strict_types=1);

namespace App\Application\Product\Services;

use App\Domain\Product\Exceptions\DuplicateSkuException;
use App\Domain\Product\Repositories\ProductRepositoryInterface;

final readonly class SkuUniquenessGuard
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /**
     * Un SKU es único por tienda entre productos y variantes.
     *
     * @param  list<string>  $skus
     *
     * @throws DuplicateSkuException
     */
    public function ensureUnique(array $skus, ?string $ignoreProductId = null): void
    {
        if ($skus === []) {
            return;
        }

        $repeatedInPayload = array_keys(array_filter(array_count_values($skus), static fn (int $count): bool => $count > 1));

        if ($repeatedInPayload !== []) {
            throw DuplicateSkuException::forSkus(array_map(strval(...), $repeatedInPayload));
        }

        $taken = $this->products->existingSkus($skus, $ignoreProductId);

        if ($taken !== []) {
            throw DuplicateSkuException::forSkus($taken);
        }
    }
}
