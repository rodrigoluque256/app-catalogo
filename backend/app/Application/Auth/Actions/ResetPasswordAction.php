<?php

declare(strict_types=1);

namespace App\Application\Auth\Actions;

use App\Application\Auth\Services\AccessLinks;
use App\Domain\Auth\Exceptions\InvalidAccessLinkException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

final readonly class ResetPasswordAction
{
    public function __construct(
        private AccessLinks $links,
    ) {}

    /**
     * Contraseña nueva desde el link del email. Cierra todas las sesiones (si alguien más tenía acceso, lo pierde).
     * Si era un invitado que nunca aceptó, el reseteo cuenta como aceptación.
     *
     * @throws InvalidAccessLinkException
     */
    public function execute(string $email, string $token, string $password): void
    {
        $status = $this->links->broker(AccessLinks::RESETS)->reset(
            ['email' => $email, 'token' => $token, 'password' => $password],
            function (User $user, string $password): void {
                DB::transaction(function () use ($user, $password): void {
                    $user->forceFill([
                        'password' => $password, // el cast "hashed" la hashea
                        'must_change_password' => false,
                        'invitation_accepted_at' => $user->invitation_accepted_at ?? now(),
                        'email_verified_at' => $user->email_verified_at ?? now(),
                    ])->save();
                    $user->tokens()->delete();
                });
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw InvalidAccessLinkException::reset();
        }
    }
}
