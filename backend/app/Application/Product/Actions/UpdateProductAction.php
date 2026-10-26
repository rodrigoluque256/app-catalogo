<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Application\Product\DTOs\UpdateProductData;
use App\Application\Product\Services\ProductSlugGenerator;
use App\Application\Product\Services\SkuUniquenessGuard;
use App\Domain\Billing\Contracts\PlanLimitGuard;
use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Domain\Product\Events\ProductUpdated;
use App\Domain\Product\Exceptions\CategoryNotFoundException;
use App\Domain\Product\Exceptions\DuplicateSkuException;
use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\ConnectionInterface;

final readonly class UpdateProductAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
        private CategoryRepositoryInterface $categories,
        private ProductSlugGenerator $slugs,
        private SkuUniquenessGuard $skus,
        private ConnectionInterface $db,
        private Dispatcher $events,
        private PlanLimitGuard $limits,
    ) {}

    /**
     * @throws ProductNotFoundException
     * @throws CategoryNotFoundException
     * @throws DuplicateSkuException
     * @throws PlanLimitExceededException
     */
    public function execute(string $productId, UpdateProductData $data): Product
    {
        $previousSlug = null;

        $updated = $this->db->transaction(function () use ($productId, $data, &$previousSlug): Product {
            $product = $this->products->findById($productId) ?? throw ProductNotFoundException::withId($productId);

            if ($data->categoryId !== null && ! $this->categories->exists($data->categoryId)) {
                throw CategoryNotFoundException::withId($data->categoryId);
            }

            $this->skus->ensureUnique($data->skus(), $product->id);

            if ($data->images !== null) {
                // Comparado con las fotos actuales: después de bajar de plan se conservan, solo no se suman.
                $this->limits->ensureImagesAllowed(count($data->images), $product->images()->count());
            }

            $previousSlug = $product->slug;

            // Renombrar no cambia el slug (estabilidad de URLs / SEO); solo un slug explícito lo hace.
            $slug = $data->slug === null || $data->slug === $product->slug
                ? $product->slug
                : $this->slugs->generate($data->slug, $product->id);

            return $this->products->update($product, $data->withSlug($slug));
        });

        $this->events->dispatch(new ProductUpdated($updated->id, $updated->store_id, $updated->slug, $previousSlug ?? $updated->slug));

        return $updated;
    }
}
