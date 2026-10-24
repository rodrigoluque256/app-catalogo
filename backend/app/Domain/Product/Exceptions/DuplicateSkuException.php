<?php

declare(strict_types=1);

namespace App\Domain\Product\Exceptions;

use App\Domain\Shared\Exceptions\DomainValidationException;

final class DuplicateSkuException extends DomainValidationException
{
    /**
     * @param  list<string>  $skus
     */
    public static function forSkus(array $skus): self
    {
        return new self(sprintf('Los siguientes SKU ya están en uso: %s.', implode(', ', $skus)));
    }

    public function field(): string
    {
        return 'sku';
    }
}
