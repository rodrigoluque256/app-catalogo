<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1\Platform;

use App\Application\Platform\Actions\Stores\ChangeStorePlanAction;
use App\Application\Platform\Actions\Stores\ChangeStoreStatusAction;
use App\Application\Platform\Actions\Stores\CreateStoreAction;
use App\Application\Platform\Actions\Stores\DeleteStoreAction;
use App\Application\Platform\Actions\Stores\ResendOwnerInvitationAction;
use App\Application\Platform\Actions\Stores\RestoreStoreAction;
use App\Domain\Platform\Exceptions\StoreNotFoundException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Store\Models\Store;
use App\UI\Http\Requests\Platform\ChangeStorePlanRequest;
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
        $created = $action->execute(
            $this->admin($request),
            $request->string('name')->trim()->toString(),
            $request->string('slug')->toString(),
            $request->string('owner_name')->trim()->toString(),
            $request->string('owner_email')->toString(),
        );

        // El dueño recibe la invitación por email (no hay contraseña temporal).
        return PlatformStoreResource::make($created)->response()->setStatusCode(Response::HTTP_CREATED);
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

    public function changePlan(ChangeStorePlanRequest $request, string $store, ChangeStorePlanAction $action): PlatformStoreResource
    {
        $action->execute($this->admin($request), $this->findOrFail($store), $request->plan(), $request->cycle());

        return PlatformStoreResource::make($this->findOrFail($store));
    }

    public function resendInvitation(Request $request, string $store, ResendOwnerInvitationAction $action): JsonResponse
    {
        return response()->json(['data' => ['emails' => $action->execute($this->admin($request), $this->findOrFail($store))]], Response::HTTP_ACCEPTED);
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
