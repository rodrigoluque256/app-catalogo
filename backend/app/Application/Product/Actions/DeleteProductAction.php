<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Domain\Product\Events\ProductDeleted;
use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class DeleteProductAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private Dispatcher $events,
    ) {}

    /**
     * @throws ProductNotFoundException
     */
    public function execute(string $productId): void
    {
        $product = $this->products->findById($productId) ?? throw ProductNotFoundException::withId($productId);

        $this->products->delete($product);

        $this->events->dispatch(new ProductDeleted($product->id, $product->store_id, $product->slug));
    }
}
