<?php

declare(strict_types=1);

namespace App\Application\Auth\Actions;

use App\Application\Auth\Services\AccessLinks;
use App\Domain\Store\Repositories\StoreRepositoryInterface;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

final readonly class SendPasswordResetLinkAction
{
    public function __construct(
        private AccessLinks $links,
        private StoreRepositoryInterface $stores,
    ) {}

    /**
     * No devuelve nada a propósito: la respuesta es la misma exista o no el email (no sirve para averiguar cuentas).
     * Solo se envía a usuarios de una tienda activa, y como mucho un email por minuto por persona.
     */
    public function execute(string $email): void
    {
        $user = User::query()->where('email', $email)->first();

        if ($user === null || $user->store_id === null || $this->stores->findActiveById($user->store_id) === null) {
            return;
        }

        RateLimiter::attempt("password-reset-mail|{$user->id}", 1, fn () => $this->links->sendReset($user), 60);
    }
}
