<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions\Stores;

use App\Application\Auth\Services\AccessLinks;
use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Platform\Exceptions\StoreManagementException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Store\Models\Store;
use App\Models\User;

final readonly class ResendOwnerInvitationAction
{
    public function __construct(
        private AccessLinks $links,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * El dueño de una tienda nueva no puede pedirse la invitación a sí mismo (todavía no entra):
     * se la reenvía el super admin. Solo a dueños con la invitación pendiente.
     *
     * @return list<string> emails a los que se reenvió
     */
    public function execute(PlatformAdmin $admin, Store $store): array
    {
        if ($store->trashed()) {
            throw StoreManagementException::deleted();
        }

        $pending = $store->owners()->whereNull('users.invitation_accepted_at')->get();

        if ($pending->isEmpty()) {
            throw StoreManagementException::noPendingInvitation();
        }

        $pending->each(fn (User $owner) => $this->links->sendInvitation($owner));
        $emails = array_values($pending->map(fn (User $owner): string => $owner->email)->all());
        $this->audit->record('owner_invitation_resent', $admin, $store->id, ['slug' => $store->slug, 'emails' => $emails]);

        return $emails;
    }
}
