<?php

declare(strict_types=1);

namespace App\Application\Category\Actions;

use App\Application\Category\DTOs\CategoryData;
use App\Application\Category\Services\CategoryParentGuard;
use App\Application\Category\Services\CategorySlugGenerator;
use App\Domain\Product\Events\CategoriesChanged;
use App\Domain\Product\Exceptions\CategoryRecordNotFoundException;
use App\Domain\Product\Exceptions\InvalidCategoryParentException;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCategoryAction
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private Dispatcher $events,
        private CategorySlugGenerator $slugs,
        private CategoryParentGuard $parentGuard,
    ) {}

    /**
     * @throws CategoryRecordNotFoundException
     * @throws InvalidCategoryParentException
     */
    public function execute(string $categoryId, CategoryData $data): Category
    {
        $category = DB::transaction(function () use ($categoryId, $data): Category {
            $category = $this->categories->findById($categoryId)
                ?? throw CategoryRecordNotFoundException::withId($categoryId);

            $this->parentGuard->assertValid($data->parentId, $category->id);

            $slug = $data->slug === null ? $category->slug : $this->slugs->generate($data->slug, $category->id);

            return $this->categories->update($category, $data, $slug);
        });

        $this->events->dispatch(new CategoriesChanged($category->store_id));

        return $category;
    }
}
