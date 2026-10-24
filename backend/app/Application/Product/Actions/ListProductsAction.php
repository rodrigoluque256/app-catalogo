<?php

declare(strict_types=1);

namespace App\Application\Product\Actions;

use App\Application\Product\DTOs\ProductFilters;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final readonly class ListProductsAction
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /**
     * @return LengthAwarePaginator<int, Product>
     */
    public function execute(ProductFilters $filters): LengthAwarePaginator
    {
        return $this->products->paginate($filters);
    }
}
