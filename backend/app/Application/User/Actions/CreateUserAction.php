<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\Billing\Contracts\PlanLimitGuard;
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
    ) {}

    /**
     * El alta genera una contraseña temporal que se devuelve UNA sola vez; la persona debe cambiarla al entrar.
     *
     * @return array{user: User, role: string, temporary_password: string}
     */
    public function execute(string $name, string $email, string $role): array
    {
        $this->limits->ensureCanAddUser();

        $temporary = Str::password(14, symbols: false);

        $user = DB::transaction(function () use ($name, $email, $role, $temporary): User {
            $user = $this->users->create($name, strtolower($email), $this->hasher->make($temporary));
            $user->assignRole($role); // el team (tienda) ya lo fijó ResolveTenantFromUser

            return $user;
        });

        return ['user' => $user, 'role' => $role, 'temporary_password' => $temporary];
    }
}
