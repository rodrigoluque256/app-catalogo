<?php

declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

use RuntimeException;

final class ProductNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Producto [%s] no encontrado.', $id));
    }

    public static function withSlug(string $slug): self
    {
        return new self(sprintf('Producto [%s] no encontrado.', $slug));
    }
}
