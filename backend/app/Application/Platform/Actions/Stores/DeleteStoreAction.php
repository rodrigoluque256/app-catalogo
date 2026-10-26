<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions\Stores;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Platform\Exceptions\StoreManagementException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Store\Events\StoreAvailabilityChanged;
use App\Domain\Store\Models\Store;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;

final readonly class DeleteStoreAction
{
    public function __construct(
        private PlatformStoreRepositoryInterface $stores,
        private Dispatcher $events,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * Soft delete: los datos quedan (se puede restaurar) y el slug sigue ocupado, así nadie toma sus URLs.
     * Exige escribir el slug para confirmar: es la acción más destructiva del panel.
     */
    public function execute(PlatformAdmin $admin, Store $store, string $confirmSlug): void
    {
        if ($store->trashed()) {
            return;
        }

        if (! hash_equals($store->slug, strtolower(trim($confirmSlug)))) {
            throw StoreManagementException::slugConfirmationMismatch();
        }

        DB::transaction(function () use ($admin, $store): void {
            $this->stores->revokeUserTokens($store);
            $this->stores->delete($store);
            $this->audit->record('store_deleted', $admin, $store->id, ['slug' => $store->slug]);
            $this->events->dispatch(new StoreAvailabilityChanged($store->id, $store->slug));
        });
    }
}
