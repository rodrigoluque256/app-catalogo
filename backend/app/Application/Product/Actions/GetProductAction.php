<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\ProductRepositoryInterface;

final readonly class GetProductAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /**
     * @throws ProductNotFoundException
     */
    public function execute(string $productId): Product
    {
        return $this->products->findById($productId) ?? throw ProductNotFoundException::withId($productId);
    }
}
