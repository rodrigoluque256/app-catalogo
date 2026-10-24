<?php

declare(strict_types=1);

namespace App\Application\Auth\Actions;

use App\Domain\Auth\Exceptions\UserManagementException;
use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Support\Facades\DB;

final readonly class ChangePasswordAction
{
    public function __construct(
        private Hasher $hasher,
    ) {}

    /**
     * Cierra las demás sesiones (token) del usuario: si alguien tenía la clave vieja, pierde el acceso.
     *
     * @throws UserManagementException
     */
    public function execute(User $user, string $currentPassword, string $newPassword, ?int $keepTokenId): void
    {
        if (! $this->hasher->check($currentPassword, $user->password)) {
            throw UserManagementException::wrongCurrentPassword();
        }

        DB::transaction(function () use ($user, $newPassword, $keepTokenId): void {
            $user->forceFill(['password' => $newPassword, 'must_change_password' => false])->save();
            $user->tokens()->when($keepTokenId, fn ($query, int $id) => $query->whereKeyNot($id))->delete();
        });
    }
}
