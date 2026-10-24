<?php

declare(strict_types=1);

namespace App\Application\User\Actions;

use App\Domain\Auth\Exceptions\UserNotFoundException;
use App\Domain\Store\Repositories\StoreUserRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ResetUserPasswordAction
{
    public function __construct(
        private StoreUserRepositoryInterface $users,
    ) {}

    /**
     * Nueva contraseña temporal (se muestra una sola vez), cierra todas las sesiones de esa persona
     * y la obliga a cambiarla al volver a entrar.
     *
     * @throws UserNotFoundException
     */
    public function execute(string $userId): string
    {
        $temporary = Str::password(14, symbols: false);

        DB::transaction(function () use ($userId, $temporary): void {
            $user = $this->users->findById($userId) ?? throw UserNotFoundException::withId($userId);

            $user->forceFill(['password' => $temporary, 'must_change_password' => true])->save(); // el cast "hashed" la hashea
            $user->tokens()->delete();
        });

        return $temporary;
    }
}
