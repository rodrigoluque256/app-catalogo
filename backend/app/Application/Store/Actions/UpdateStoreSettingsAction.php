<?php

declare(strict_types=1);

namespace App\Application\Store\Actions;

use App\Application\Store\DTOs\UpdateStoreSettingsData;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Events\StoreSettingsUpdated;
use App\Domain\Store\Models\Store;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class UpdateStoreSettingsAction
{
    public function __construct(
        private TenantContext $tenant,
        private StoreRepositoryInterface $stores,
        private Dispatcher $events,
    ) {}

    /**
     * Siempre sobre la tienda del tenant actual: no recibe ids.
     */
    public function execute(UpdateStoreSettingsData $data): Store
    {
        $store = $this->stores->update($this->tenant->store(), $data->changes);

        $this->events->dispatch(new StoreSettingsUpdated($store->id));

        return $store;
    }
}
