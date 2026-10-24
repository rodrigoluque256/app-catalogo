<?php

declare(strict_types=1);

namespace App\Application\Category\Actions;

use App\Domain\Product\Models\Category;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use Illuminate\Support\Collection;

final readonly class ListCategoriesAction
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    /**
     * @return Collection<int, Category>
     */
    public function execute(bool $onlyActive = false): Collection
    {
        return $this->categories->all($onlyActive);
    }
}
