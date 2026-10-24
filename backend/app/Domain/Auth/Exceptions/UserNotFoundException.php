<?php

declare(strict_types=1);

namespace App\Domain\Auth\Exceptions;

use RuntimeException;

final class UserNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Usuario [%s] no encontrado.', $id));
    }
}
