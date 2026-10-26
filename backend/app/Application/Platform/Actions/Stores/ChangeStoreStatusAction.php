<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions\Stores;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Platform\Exceptions\StoreManagementException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Events\StoreAvailabilityChanged;
use App\Domain\Store\Models\Store;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final readonly class ChangeStoreStatusAction
{
    public function __construct(
        private PlatformStoreRepositoryInterface $stores,
        private Dispatcher $events,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * Suspender: la tienda deja de verse (404) y sus usuarios no pueden entrar (sesiones cerradas).
     * Reactivar: vuelve a verse. Pedir el estado que ya tiene no hace nada.
     */
    public function execute(PlatformAdmin $admin, Store $store, StoreStatus $status): Store
    {
        if ($store->trashed()) {
            throw StoreManagementException::deleted();
        }

        if ($store->status === $status) {
            return $store;
        }

        DB::transaction(function () use ($admin, $store, $status): void {
            $this->stores->setStatus($store, $status);

            if ($status === StoreStatus::Suspended) {
                $this->stores->revokeUserTokens($store);
            }

            $this->audit->record($status === StoreStatus::Suspended ? 'store_suspended' : 'store_reactivated', $admin, $store->id, ['slug' => $store->slug]);
            $this->events->dispatch(new StoreAvailabilityChanged($store->id, $store->slug));
        });

        return $store;
    }
}
