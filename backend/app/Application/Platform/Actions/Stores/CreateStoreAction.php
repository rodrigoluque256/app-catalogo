<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions\Stores;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Application\User\Actions\CreateUserAction;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Repositories\PlatformStoreRepositoryInterface;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Models\Store;
use Illuminate\Support\Facades\DB;

final readonly class CreateStoreAction
{
    public function __construct(
        private PlatformStoreRepositoryInterface $stores,
        private TenantContext $tenant,
        private CreateUserAction $createUser,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * Tienda + dueño en una transacción. El dueño recibe una contraseña temporal (se devuelve UNA sola vez)
     * y tiene que cambiarla al entrar, igual que un usuario creado desde el panel de la tienda.
     *
     * @return array{store: Store, temporary_password: string}
     */
    public function execute(PlatformAdmin $admin, string $name, string $slug, string $ownerName, string $ownerEmail): array
    {
        $result = DB::transaction(function () use ($admin, $name, $slug, $ownerName, $ownerEmail): array {
            $store = $this->stores->create($name, $slug);

            // El alta del usuario usa el tenant y el team de Spatie del request: acá se fijan a la tienda nueva.
            $owner = $this->tenant->runAs($store, function () use ($store, $ownerName, $ownerEmail): array {
                setPermissionsTeamId($store->id);

                try {
                    return $this->createUser->execute($ownerName, $ownerEmail, 'owner');
                } finally {
                    setPermissionsTeamId(null);
                }
            });

            $this->audit->record('store_created', $admin, $store->id, ['slug' => $store->slug, 'owner_email' => $owner['user']->email]);

            return ['store' => $store, 'temporary_password' => $owner['temporary_password']];
        });

        return ['store' => $this->stores->find($result['store']->id) ?? $result['store'], 'temporary_password' => $result['temporary_password']];
    }
}
