<?php

declare(strict_types=1);

namespace App\Domain\Platform\Exceptions;

use RuntimeException;

final class StoreNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self("No existe la tienda {$id}.");
    }
}
