<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

/**
 * Un único mensaje para email inexistente, contraseña errónea, usuario sin tienda o tienda suspendida:
 * así el login no sirve para averiguar qué emails existen.
 */
final class InvalidCredentialsException extends DomainValidationException
{
    public function __construct()
    {
        parent::__construct('Email o contraseña incorrectos.');
    }

    public function field(): string
    {
        return 'email';
    }
}
