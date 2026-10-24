<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use App\Domain\Shared\Events\AffectsStorefront;
use App\Domain\Shared\Events\StorefrontTags;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class ProductUpdated implements AffectsStorefront, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $productId,
        public string $storeId,
        public string $slug,
        public string $previousSlug,
    ) {}

    public function storeId(): string
    {
        return $this->storeId;
    }

    public function tags(string $storeSlug): array
    {
        // Si el slug cambió, la URL vieja también debe dejar de servirse.
        return array_values(array_unique([
            StorefrontTags::products($storeSlug),
            StorefrontTags::product($storeSlug, $this->slug),
            StorefrontTags::product($storeSlug, $this->previousSlug),
        ]));
    }
}
