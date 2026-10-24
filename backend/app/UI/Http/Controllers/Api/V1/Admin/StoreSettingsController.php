<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Admin;

use App\Application\Store\Actions\UpdateStoreSettingsAction;
use App\Domain\Shared\Tenancy\TenantContext;
use App\UI\Http\Requests\Store\UpdateStoreSettingsRequest;
use App\UI\Http\Resources\StoreResource;

final class StoreSettingsController
{
    public function show(TenantContext $tenant): StoreResource
    {
        return StoreResource::make($tenant->store());
    }

    public function update(UpdateStoreSettingsRequest $request, UpdateStoreSettingsAction $action): StoreResource
    {
        return StoreResource::make($action->execute($request->toData()));
    }
}
