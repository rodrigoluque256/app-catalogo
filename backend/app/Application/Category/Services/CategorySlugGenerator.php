<?php

declare(strict_types=1);

namespace App\Application\Category\Services;

use App\Domain\Product\Repositories\CategoryRepositoryInterface;
use Illuminate\Support\Str;

final readonly class CategorySlugGenerator
{
    public function __construct(
        private CategoryRepositoryInterface $categories,
    ) {}

    /**
     * "remeras", "remeras-2", "remeras-3"… dentro de la tienda actual.
     */
    public function generate(string $base, ?string $ignoreCategoryId = null): string
    {
        $slug = Str::slug($base) ?: 'categoria';
        $candidate = $slug;

        for ($suffix = 2; $this->categories->slugExists($candidate, $ignoreCategoryId); $suffix++) {
            $candidate = "{$slug}-{$suffix}";
        }

        return $candidate;
    }
}
