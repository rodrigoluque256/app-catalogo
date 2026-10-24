<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Category\DTOs\CategoryData;
use App\Domain\Product\Exceptions\CategoryRecordNotFoundException;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class EloquentCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private TenantContext $tenant,
    ) {}

    public function findById(string $id): ?Category
    {
        return Str::isUuid($id) ? $this->query()->whereKey($id)->first() : null;
    }

    public function exists(string $id): bool
    {
        return Str::isUuid($id) && $this->query()->whereKey($id)->exists();
    }

    public function all(bool $onlyActive = false): Collection
    {
        return $this->query()
            ->when($onlyActive, fn (Builder $query) => $query->where('is_active', true))
            ->orderBy('position')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    public function slugExists(string $slug, ?string $ignoreCategoryId = null): bool
    {
        return $this->query()
            ->where('slug', $slug)
            ->when($ignoreCategoryId, fn (Builder $query, string $id) => $query->whereKeyNot($id))
            ->exists();
    }

    public function create(CategoryData $data, string $slug): Category
    {
        $category = new Category;
        $category->forceFill(['store_id' => $this->tenant->id()] + $this->attributes($data, $slug));
        $category->save();

        return $category;
    }

    public function update(Category $category, CategoryData $data, string $slug): Category
    {
        $this->assertBelongsToTenant($category);

        $category->forceFill($this->attributes($data, $slug))->save();

        return $category;
    }

    public function delete(Category $category): void
    {
        $this->assertBelongsToTenant($category);

        // El soft delete no dispara los ON DELETE de las FKs, así que se desvinculan a mano.
        DB::transaction(function () use ($category): void {
            Product::query()
                ->where('store_id', $category->store_id)
                ->where('category_id', $category->id)
                ->update(['category_id' => null]);

            Category::query()
                ->where('store_id', $category->store_id)
                ->where('parent_id', $category->id)
                ->update(['parent_id' => null]);

            $category->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function attributes(CategoryData $data, string $slug): array
    {
        return [
            'parent_id' => $data->parentId,
            'name' => $data->name,
            'slug' => $slug,
            'description' => $data->description,
            'position' => $data->position,
            'is_active' => $data->isActive,
        ];
    }

    /**
     * @return Builder<Category>
     */
    private function query(): Builder
    {
        return Category::query()->where('categories.store_id', $this->tenant->id());
    }

    private function assertBelongsToTenant(Category $category): void
    {
        if ($category->store_id !== $this->tenant->id()) {
            throw CategoryRecordNotFoundException::withId($category->id);
        }
    }
}
