<?php

declare(strict_types=1);

namespace App\Domain\Product\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Archived = 'archived';

    public function isVisibleInStorefront(): bool
    {
        return $this === self::Active;
    }
}
