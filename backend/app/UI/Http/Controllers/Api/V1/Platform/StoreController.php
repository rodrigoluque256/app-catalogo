<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Platform;

use App\Application\Platform\Actions\Stores\ChangeStoreStatusAction;
use App\Application\Platform\Actions\Stores\CreateStoreAction;
use App\Application\Platform\Actions\Stores\DeleteStoreAction;
use App\Application\Platform\Actions\Stores\RestoreStoreAction;
use App\Domain\Platform\Exceptions\StoreNotFoundException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Store\Models\Store;
use App\UI\Http\Requests\Platform\CreateStoreRequest;
use App\UI\Http\Requests\Platform\DeleteStoreRequest;
use App\UI\Http\Requests\Platform\ListStoresRequest;
use App\UI\Http\Requests\Platform\UpdateStoreStatusRequest;
use App\UI\Http\Resources\PlatformStoreResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class StoreController
{
    public function __construct(
        private readonly PlatformStoreRepositoryInterface $stores,
    ) {}

    public function index(ListStoresRequest $request): AnonymousResourceCollection
    {
        return PlatformStoreResource::collection($this->stores->paginate($request->search(), $request->status(), $request->perPage()));
    }

    public function show(string $store): PlatformStoreResource
    {
        return PlatformStoreResource::make($this->findOrFail($store));
    }

    public function store(CreateStoreRequest $request, CreateStoreAction $action): JsonResponse
    {
        $result = $action->execute(
            $this->admin($request),
            $request->string('name')->trim()->toString(),
            $request->string('slug')->toString(),
            $request->string('owner_name')->trim()->toString(),
            $request->string('owner_email')->toString(),
        );

        return response()->json(['data' => [
            ...PlatformStoreResource::make($result['store'])->resolve($request),
            // Se muestra una sola vez: no se puede volver a consultar.
            'temporary_password' => $result['temporary_password'],
        ]], Response::HTTP_CREATED);
    }

    public function update(UpdateStoreStatusRequest $request, string $store, ChangeStoreStatusAction $action): PlatformStoreResource
    {
        $action->execute($this->admin($request), $this->findOrFail($store), $request->status());

        return PlatformStoreResource::make($this->findOrFail($store));
    }

    public function destroy(DeleteStoreRequest $request, string $store, DeleteStoreAction $action): Response
    {
        $action->execute($this->admin($request), $this->findOrFail($store), $request->string('confirm_slug')->toString());

        return response()->noContent();
    }

    public function restore(Request $request, string $store, RestoreStoreAction $action): PlatformStoreResource
    {
        $action->execute($this->admin($request), $this->findOrFail($store));

        return PlatformStoreResource::make($this->findOrFail($store));
    }

    private function findOrFail(string $id): Store
    {
        return $this->stores->find($id) ?? throw StoreNotFoundException::withId($id);
    }

    private function admin(Request $request): PlatformAdmin
    {
        /** @var PlatformAdmin $admin el middleware platform.admin ya lo garantizó */
        $admin = $request->user();

        return $admin;
    }
}
