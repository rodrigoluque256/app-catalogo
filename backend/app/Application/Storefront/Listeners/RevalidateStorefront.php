<?php

declare(strict_types=1);

namespace App\Application\Storefront\Listeners;

use App\Domain\Shared\Events\AffectsStorefront;
use App\Domain\Shared\Events\RevalidatesRegardlessOfStatus;
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
        // Suspender o borrar: hay que revalidar justamente porque la tienda dejó de estar activa.
        if ($event instanceof RevalidatesRegardlessOfStatus) {
            $this->revalidator->revalidate($event->tags($event->storeSlug()));

            return;
        }

        $store = $this->stores->findActiveById($event->storeId());

        // Cambios de contenido de una tienda suspendida o borrada: su storefront ya responde 404.
        if ($store === null) {
            return;
        }

        $this->revalidator->revalidate($event->tags($store->slug));
    }
}
