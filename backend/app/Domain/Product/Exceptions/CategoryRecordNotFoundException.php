<?php

declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

use RuntimeException;

/**
 * Categoría pedida por id en un endpoint propio (show/update/delete): responde 404.
 * Distinta de CategoryNotFoundException, que es un 422 sobre un campo de otro recurso.
 */
final class CategoryRecordNotFoundException extends RuntimeException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('Categoría [%s] no encontrada.', $id));
    }
}
