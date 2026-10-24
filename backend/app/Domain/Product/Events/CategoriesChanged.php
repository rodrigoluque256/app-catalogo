<?php

declare(strict_types=1);

namespace App\Domain\Product\Events;

use App\Domain\Shared\Events\AffectsStorefront;
use App\Domain\Shared\Events\StorefrontTags;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * Crear, editar o borrar una categoría. Los productos embeben su categoría, así que también se revalidan.
 */
final readonly class CategoriesChanged implements AffectsStorefront, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $storeId,
    ) {}

    public function storeId(): string
    {
        return $this->storeId;
    }

    public function tags(string $storeSlug): array
    {
        return [StorefrontTags::categories($storeSlug), StorefrontTags::products($storeSlug)];
    }
}
