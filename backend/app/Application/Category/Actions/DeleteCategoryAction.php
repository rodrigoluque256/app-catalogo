<?php

declare(strict_types=1);

namespace App\Application\Category\Actions;

use App\Domain\Product\Events\CategoriesChanged;
use App\Domain\Product\Exceptions\CategoryRecordNotFoundException;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class DeleteCategoryAction
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private Dispatcher $events,
    ) {}

    /**
     * @throws CategoryRecordNotFoundException
     */
    public function execute(string $categoryId): void
    {
        $category = $this->categories->findById($categoryId)
            ?? throw CategoryRecordNotFoundException::withId($categoryId);

        $this->categories->delete($category);

        $this->events->dispatch(new CategoriesChanged($category->store_id));
    }
}
