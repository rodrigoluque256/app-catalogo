<?php

declare(strict_types=1);

namespace App\Application\Category\Services;

use App\Domain\Product\Exceptions\InvalidCategoryParentException;
use App\Domain\Product\Repositories\CategoryRepositoryInterface;

final readonly class CategoryParentGuard
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    /**
     * El padre debe ser de la tienda y, al editar, no puede ser la propia categoría ni un descendiente suyo.
     *
     * @throws InvalidCategoryParentException
     */
    public function assertValid(?string $parentId, ?string $categoryId = null): void
    {
        if ($parentId === null) {
            return;
        }

        $visited = [];

        for ($currentId = $parentId; $currentId !== null; ) {
            if ($currentId === $categoryId) {
                throw InvalidCategoryParentException::cycle();
            }

            $current = $this->categories->findById($currentId);

            if ($current === null) {
                throw InvalidCategoryParentException::missing($parentId);
            }

            if (isset($visited[$currentId])) {
                return; // ciclo preexistente que no involucra a esta categoría
            }

            $visited[$currentId] = true;
            $currentId = $current->parent_id;
        }
    }
}
