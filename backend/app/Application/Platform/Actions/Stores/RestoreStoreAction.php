<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions\Stores;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Platform\Exceptions\StoreManagementException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use Illuminate\Support\Facades\DB;

final readonly class RestoreStoreAction
{
    public function __construct(
        private PlatformStoreRepositoryInterface $stores,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * Vuelve como suspendida: no se publica sola, primero se revisa y se reactiva a propósito.
     */
    public function execute(PlatformAdmin $admin, Store $store): Store
    {
        if (! $store->trashed()) {
            throw StoreManagementException::notDeleted();
        }

        DB::transaction(function () use ($admin, $store): void {
            $this->stores->restore($store);
            $this->stores->setStatus($store, StoreStatus::Suspended);
            $this->audit->record('store_restored', $admin, $store->id, ['slug' => $store->slug]);
        });

        return $store;
    }
}
