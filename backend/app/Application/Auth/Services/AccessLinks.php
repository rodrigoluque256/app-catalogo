<?php

declare(strict_types=1);

namespace App\Application\Auth\Services;

use App\Application\Auth\Notifications\ResetPasswordLink;
use App\Application\Auth\Notifications\UserInvitation;
use App\Models\User;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Contracts\Auth\PasswordBrokerFactory;

/**
 * Links de un solo uso para entrar al panel: invitación (72 h) y restablecer contraseña (60 min).
 * Ambos usan la tabla password_reset_tokens (un token vigente por email: pedir uno invalida el anterior).
 */
final readonly class AccessLinks
{
    public const string INVITATIONS = 'invitations';

    public const string RESETS = 'users';

    public function __construct(
        private PasswordBrokerFactory $brokers,
    ) {}

    public function sendInvitation(User $user): void
    {
        $token = $this->broker(self::INVITATIONS)->createToken($user);
        $user->notify(new UserInvitation($token, (string) $user->store?->name, intdiv($this->expires(self::INVITATIONS), 60)));
    }

    public function sendReset(User $user): void
    {
        $token = $this->broker(self::RESETS)->createToken($user);
        $user->notify(new ResetPasswordLink($token, $this->expires(self::RESETS)));
    }

    public function broker(string $name): PasswordBroker
    {
        /** @var PasswordBroker $broker */
        $broker = $this->brokers->broker($name);

        return $broker;
    }

    private function expires(string $broker): int
    {
        return (int) config("auth.passwords.{$broker}.expire");
    }
}
