<?php

declare(strict_types=1);

namespace App\Application\Product\Services;

use App\Domain\Product\Repositories\ProductRepositoryInterface;
use Illuminate\Support\Str;

final readonly class ProductSlugGenerator
{
    public function __construct(
        private ProductRepositoryInterface $products,
    ) {}

    /**
     * Slug único dentro de la tienda: "remera", "remera-2", "remera-3"...
     */
    public function generate(string $source, ?string $ignoreProductId = null): string
    {
        $base = Str::slug($source);
        $base = $base !== '' ? $base : 'producto';

        $slug = $base;
        $suffix = 2;

        while ($this->products->slugExists($slug, $ignoreProductId)) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
