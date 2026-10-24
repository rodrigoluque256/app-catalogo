<?php

declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

final class CategoryNotFoundException extends DomainValidationException
{
    public static function withId(string $id): self
    {
        return new self(sprintf('La categoría [%s] no existe en esta tienda.', $id));
    }

    public function field(): string
    {
        return 'category_id';
    }
}
