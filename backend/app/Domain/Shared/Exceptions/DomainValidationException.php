<?php

declare(strict_types=1);

namespace App\Domain\Shared\Exceptions;

use DomainException;

/**
 * Regla de negocio violada por datos de entrada. La UI la traduce a un 422
 * con el mismo formato que la validación de Laravel (ver bootstrap/app.php).
 */
abstract class DomainValidationException extends DomainException
{
    abstract public function field(): string;
}
