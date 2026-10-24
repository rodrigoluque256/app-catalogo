<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\ProductRepositoryInterface;

final readonly class GetStorefrontProductAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /**
     * Un borrador o archivado responde igual que uno inexistente (404).
     *
     * @throws ProductNotFoundException
     */
    public function execute(string $slug): Product
    {
        $product = $this->products->findBySlug($slug);

        if ($product === null || ! $product->status->isVisibleInStorefront()) {
            throw ProductNotFoundException::withSlug($slug);
        }

        return $product;
    }
}
