<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Application\Auth\Services\AccessLinks;
use App\Domain\Billing\Contracts\PlanLimitGuard;
use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class CreateUserAction
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
        private Hasher $hasher,
        private PlanLimitGuard $limits,
        private AccessLinks $links,
    ) {}

    /**
     * El alta es una invitación: la persona recibe un email con un link (72 h) para elegir su contraseña.
     * Hasta entonces su contraseña es aleatoria y nadie la conoce, así que no puede entrar.
     *
     * @return array{user: User, role: string}
     *
     * @throws PlanLimitExceededException
     */
    public function execute(string $name, string $email, string $role): array
    {
        $this->limits->ensureCanAddUser();

        $user = DB::transaction(function () use ($name, $email, $role): User {
            $user = $this->users->create($name, strtolower($email), $this->hasher->make(Str::random(64)));
            $user->assignRole($role); // el team (tienda) ya lo fijó ResolveTenantFromUser

            $this->links->sendInvitation($user); // en cola, después del commit

            return $user;
        });

        return ['user' => $user, 'role' => $role];
    }
}
