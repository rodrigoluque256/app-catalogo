<?php

declare(strict_types=1);

namespace App\Application\Storefront\Listeners;

use App\Domain\Shared\Events\AffectsStorefront;
use App\Domain\Store\Contracts\StorefrontRevalidator;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use Illuminate\Contracts\Queue\ShouldQueue;

final class RevalidateStorefront implements ShouldQueue
{
    public bool $afterCommit = true;

    public int $tries = 5;

    public function __construct(
        private readonly StoreRepositoryInterface $stores,
        private readonly StorefrontRevalidator $revalidator,
    ) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [10, 30, 120, 600];
    }

    public function handle(AffectsStorefront $event): void
    {
        $store = $this->stores->findActiveById($event->storeId());

        // Tienda suspendida o borrada: su storefront ya responde 404, no hay nada que refrescar.
        if ($store === null) {
            return;
        }

        $this->revalidator->revalidate($event->tags($store->slug));
    }
}
