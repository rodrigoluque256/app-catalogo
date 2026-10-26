<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Application\Auth\Services\AccessLinks;
use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use Illuminate\Support\Facades\DB;

final readonly class SendUserAccessAction
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
        private AccessLinks $links,
    ) {}

    /**
     * Desde la gestión de usuarios: si la invitación sigue pendiente, la reenvía (link nuevo); si no,
     * cierra todas las sesiones de esa persona y le manda un link para elegir otra contraseña.
     *
     * @return 'invitation'|'reset'
     *
     * @throws UserNotFoundException
     */
    public function execute(string $userId): string
    {
        $user = $this->users->findById($userId) ?? throw UserNotFoundException::withId($userId);

        if ($user->invitationPending()) {
            $this->links->sendInvitation($user);

            return 'invitation';
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $this->links->sendReset($user);
        });

        return 'reset';
    }
}
