<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

/**
 * Errores del segundo factor del super panel. Se muestran junto al campo del código (422).
 */
final class TwoFactorException extends DomainValidationException
{
    private function __construct(string $message, private readonly string $field = 'code')
    {
        parent::__construct($message);
    }

    public static function invalidCode(): self
    {
        return new self('El código no es válido o ya se usó.');
    }

    public static function invalidRecoveryCode(): self
    {
        return new self('El código de recuperación no es válido o ya se usó.', 'recovery_code');
    }

    public static function alreadyConfirmed(): self
    {
        return new self('La verificación en dos pasos ya está configurada.');
    }

    public static function setupNotStarted(): self
    {
        return new self('Primero generá el código QR para configurar la verificación en dos pasos.');
    }

    public static function notConfigured(): self
    {
        return new self('Todavía no configuraste la verificación en dos pasos.');
    }

    public function field(): string
    {
        return $this->field;
    }
}
