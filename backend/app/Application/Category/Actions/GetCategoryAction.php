<?php

declare(strict_types=1);

namespace App\Application\Category\Actions;

use App\Domain\Product\Exceptions\CategoryRecordNotFoundException;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;

final readonly class GetCategoryAction
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    /**
     * @throws CategoryRecordNotFoundException
     */
    public function execute(string $categoryId): Category
    {
        return $this->categories->findById($categoryId) ?? throw CategoryRecordNotFoundException::withId($categoryId);
    }
}
