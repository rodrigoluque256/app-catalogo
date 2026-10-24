<?php

declare(strict_types=1);

namespace App\Domain\Shared\Events;

/**
 * Convención de tags de caché compartida con el frontend (fetch(..., { next: { tags } })).
 */
final class StorefrontTags
{
    public static function store(string $storeSlug): string
    {
        return "store:{$storeSlug}";
    }

    public static function categories(string $storeSlug): string
    {
        return "store:{$storeSlug}:categories";
    }

    public static function products(string $storeSlug): string
    {
        return "store:{$storeSlug}:products";
    }

    public static function product(string $storeSlug, string $productSlug): string
    {
        return "store:{$storeSlug}:product:{$productSlug}";
    }
}
