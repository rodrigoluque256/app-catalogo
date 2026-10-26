<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Shared\Tenancy\StoreScope;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class EloquentPlatformStoreRepository implements PlatformStoreRepositoryInterface
{
    public function paginate(?string $search, ?string $status, int $perPage): LengthAwarePaginator
    {
        $query = $this->query()
            ->when($status === 'deleted', fn (Builder $q) => $q->onlyTrashed())
            ->when(in_array($status, ['active', 'suspended'], true), fn (Builder $q) => $q->where('status', $status))
            ->when($search !== null && $search !== '', function (Builder $q) use ($search): void {
                $like = '%'.addcslashes(Str::lower((string) $search), '%_\\').'%';
                $q->where(fn (Builder $inner) => $inner
                    ->whereRaw('lower(stores.name) like ?', [$like])
                    ->orWhere('stores.slug', 'like', $like)
                    ->orWhereHas('owners', fn (Builder $owner) => $owner->whereRaw('lower(users.email) like ?', [$like])));
            })
            ->orderByDesc('stores.created_at')
            ->orderBy('stores.id');

        return $query->paginate($perPage);
    }

    public function find(string $id): ?Store
    {
        return Str::isUuid($id) ? $this->query()->withTrashed()->find($id) : null;
    }

    public function create(string $name, string $slug): Store
    {
        $store = Store::query()->create(['name' => $name, 'slug' => $slug]);

        return $store->refresh(); // valores por defecto de la base (colores, moneda, idioma)
    }

    public function setStatus(Store $store, StoreStatus $status): void
    {
        $store->forceFill(['status' => $status])->save();
    }

    public function delete(Store $store): void
    {
        $store->delete();
    }

    public function restore(Store $store): void
    {
        $store->restore();
    }

    public function revokeUserTokens(Store $store): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_type', (new User)->getMorphClass())
            ->whereIn('tokenable_id', User::query()->where('store_id', $store->id)->select('id'))
            ->delete();
    }

    /**
     * @return Builder<Store>
     */
    private function query(): Builder
    {
        return Store::query()
            ->with(['owners' => fn ($q) => $q->select(['users.id', 'users.store_id', 'users.name', 'users.email', 'users.invitation_accepted_at'])->orderBy('users.id'), 'subscription.plan'])
            // Product tiene el StoreScope (falla cerrado sin tenant): acá se cuentan las de todas las tiendas a propósito.
            ->withCount(['products' => fn (Builder $q) => $q->withoutGlobalScope(StoreScope::class), 'users']);
    }
}
