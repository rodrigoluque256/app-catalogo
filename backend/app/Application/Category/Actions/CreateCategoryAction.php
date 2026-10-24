<?php

declare(strict_types=1);

namespace App\Application\Category\Actions;

use App\Application\Category\DTOs\CategoryData;
use App\Application\Category\Services\CategoryParentGuard;
use App\Application\Category\Services\CategorySlugGenerator;
use App\Domain\Product\Events\CategoriesChanged;
use App\Domain\Product\Exceptions\InvalidCategoryParentException;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final readonly class CreateCategoryAction
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
        private Dispatcher $events,
        private CategorySlugGenerator $slugs,
        private CategoryParentGuard $parentGuard,
    ) {}

    /**
     * @throws InvalidCategoryParentException
     */
    public function execute(CategoryData $data): Category
    {
        $category = DB::transaction(function () use ($data): Category {
            $this->parentGuard->assertValid($data->parentId);

            return $this->categories->create($data, $this->slugs->generate($data->slug ?? $data->name));
        });

        $this->events->dispatch(new CategoriesChanged($category->store_id));

        return $category;
    }
}
