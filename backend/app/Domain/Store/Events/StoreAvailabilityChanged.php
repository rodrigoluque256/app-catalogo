<?php

declare(strict_types=1);

namespace App\Domain\Store\Events;

use App\Domain\Shared\Events\RevalidatesRegardlessOfStatus;
use App\Domain\Shared\Events\StorefrontTags;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

/**
 * La tienda se suspendió, reactivó, borró o restauró: el layout del storefront depende del tag de la tienda,
 * así que revalidarlo apaga (404) o prende todas sus páginas a la vez.
 */
final readonly class StoreAvailabilityChanged implements RevalidatesRegardlessOfStatus, ShouldDispatchAfterCommit
{
    public function __construct(
        public string $storeId,
        public string $slug,
    ) {}

    public function storeId(): string
    {
        return $this->storeId;
    }

    public function storeSlug(): string
    {
        return $this->slug;
    }

    public function tags(string $storeSlug): array
    {
        return [StorefrontTags::store($storeSlug)];
    }
}
