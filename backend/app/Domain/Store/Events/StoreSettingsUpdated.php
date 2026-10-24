<?php

declare(strict_types=1);

namespace App\Domain\Store\Events;

use App\Domain\Shared\Events\AffectsStorefront;
use App\Domain\Shared\Events\StorefrontTags;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;

final readonly class StoreSettingsUpdated implements AffectsStorefront, ShouldDispatchAfterCommit
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
        return [StorefrontTags::store($storeSlug)];
    }
}
