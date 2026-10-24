<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Application\Product\DTOs\ProductFilters;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Catálogo público: el estado se fuerza a "active" sin importar lo que pida el cliente.
 */
final readonly class ListStorefrontProductsAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function execute(ProductFilters $filters): LengthAwarePaginator
    {
        return $this->products->paginate(new ProductFilters(
            search: $filters->search,
            categoryId: $filters->categoryId,
            status: ProductStatus::Active,
            featured: $filters->featured,
            sort: $filters->sort,
            perPage: $filters->perPage,
            page: $filters->page,
        ));
    }
}
