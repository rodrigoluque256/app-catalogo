<?php

declare(strict_types=1);

namespace App\Domain\Product\Repositories;

use App\Application\Category\DTOs\CategoryData;
use App\Domain\Product\Models\Category;
use Illuminate\Support\Collection;

/**
 * Toda implementación opera exclusivamente sobre la tienda del TenantContext actual.
 */
interface CategoryRepositoryInterface
{
    public function findById(string $id): ?Category;

    public function exists(string $id): bool;

    /**
     * Lista plana ordenada por posición y nombre; el cliente arma el árbol con parent_id.
     *
     * @return Collection<int, Category>
     */
    public function all(bool $onlyActive = false): Collection;

    public function slugExists(string $slug, ?string $ignoreCategoryId = null): bool;

    public function create(CategoryData $data, string $slug): Category;

    public function update(Category $category, CategoryData $data, string $slug): Category;

    /**
     * Borra la categoría y deja sin categoría/padre a los productos y subcategorías que la usaban.
     */
    public function delete(Category $category): void;
}
