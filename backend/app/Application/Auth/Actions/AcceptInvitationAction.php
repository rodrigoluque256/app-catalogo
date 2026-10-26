<?php

declare(strict_types=1);

namespace App\Application\Auth\Actions;

use App\Application\Auth\Services\AccessLinks;
use App\Domain\Auth\Exceptions\InvalidAccessLinkException;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\Password;

final readonly class AcceptInvitationAction
{
    public function __construct(
        private AccessLinks $links,
        private StoreRepositoryInterface $stores,
    ) {}

    /**
     * Tienda del invitado si el link es válido (para mostrar "Elegí tu contraseña para entrar a …").
     */
    public function storeName(string $email, string $token): ?string
    {
        $user = $this->invitee($email);

        return $user !== null && $this->links->broker(AccessLinks::INVITATIONS)->tokenExists($user, $token)
            ? $user->store?->name
            : null;
    }

    /**
     * Elige su contraseña y entra: devuelve un token de sesión, como el login.
     *
     * @throws InvalidAccessLinkException
     */
    public function execute(string $email, string $token, string $password): string
    {
        if ($this->invitee($email) === null) {
            throw InvalidAccessLinkException::invitation();
        }

        $accepted = null;
        $status = $this->links->broker(AccessLinks::INVITATIONS)->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password) use (&$accepted): void {
                $user->forceFill([
                    'password' => $password,
                    'must_change_password' => false,
                    'invitation_accepted_at' => $user->invitation_accepted_at ?? now(),
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();
                $accepted = $user;
            },
        );

        if ($status !== Password::PASSWORD_RESET || ! $accepted instanceof User) {
            throw InvalidAccessLinkException::invitation();
        }

        return $accepted->createToken('admin', ['*'], now()->addHours(LoginAction::TOKEN_TTL_HOURS))->plainTextToken;
    }

    /** Solo usuarios de una tienda activa: una tienda suspendida no suma gente. */
    private function invitee(string $email): ?User
    {
        $user = User::query()->where('email', $email)->first();

        return $user !== null && $user->store_id !== null && $this->stores->findActiveById($user->store_id) !== null ? $user : null;
    }
}
