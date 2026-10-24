<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Application\Product\DTOs\CreateProductData;
use App\Application\Product\Services\ProductSlugGenerator;
use App\Application\Product\Services\SkuUniquenessGuard;
use App\Domain\Product\Events\ProductCreated;
use App\Domain\Product\Exceptions\CategoryNotFoundException;
use App\Domain\Product\Exceptions\DuplicateSkuException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class CreateProductAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private ProductSlugGenerator $slugs,
        private SkuUniquenessGuard $skus,
        private ConnectionInterface $db,
        private Dispatcher $events,
    ) {}

    /**
     * @throws CategoryNotFoundException
     * @throws DuplicateSkuException
     */
    public function execute(CreateProductData $data): Product
    {
        $product = $this->db->transaction(function () use ($data): Product {
            if ($data->categoryId !== null && ! $this->categories->exists($data->categoryId)) {
                throw CategoryNotFoundException::withId($data->categoryId);
            }

            $this->skus->ensureUnique($data->skus());

            $slug = $this->slugs->generate($data->slug ?? $data->name);

            return $this->products->create($data->withSlug($slug));
        });

        $this->events->dispatch(new ProductCreated($product->id, $product->store_id, $product->slug));

        return $product;
    }
}
