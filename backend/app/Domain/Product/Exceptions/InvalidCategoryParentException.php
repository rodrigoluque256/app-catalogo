<?php

declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

final class InvalidCategoryParentException extends DomainValidationException
{
    public static function missing(string $id): self
    {
        return new self(sprintf('La categoría padre [%s] no existe en esta tienda.', $id));
    }

    public static function cycle(): self
    {
        return new self('Una categoría no puede ser su propio padre ni descender de sí misma.');
    }

    public function field(): string
    {
        return 'parent_id';
    }
}
