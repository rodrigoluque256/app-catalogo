<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use Illuminate\Support\Str;

final readonly class EloquentStoreRepository implements StoreRepositoryInterface
{
    public function findActiveById(string $id): ?Store
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        return Store::query()->whereKey($id)->where('status', StoreStatus::Active->value)->first();
    }

    public function findActiveBySlug(string $slug): ?Store
    {
        return Store::query()->where('slug', Str::lower($slug))->where('status', StoreStatus::Active->value)->first();
    }

    public function update(Store $store, array $attributes): Store
    {
        $store->fill($attributes)->save();

        return $store;
    }
}
