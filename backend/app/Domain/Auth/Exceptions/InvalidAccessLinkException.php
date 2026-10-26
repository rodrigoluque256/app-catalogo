<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

/**
 * Link de invitación o de reseteo inválido, vencido o ya usado. Mismo mensaje para todos los casos.
 */
final class InvalidAccessLinkException extends DomainValidationException
{
    public static function invitation(): self
    {
        return new self('El link de invitación no es válido o venció. Pedile a quien te invitó que te lo reenvíe.');
    }

    public static function reset(): self
    {
        return new self('El link para restablecer la contraseña no es válido o venció. Pedí uno nuevo.');
    }

    public function field(): string
    {
        return 'token';
    }
}
