<?php

declare(strict_types=1);

namespace App\Domain\Store\Contracts;

final readonly class UploadedObject
{
    public function __construct(
        public int $size,
        public string $contentType,
    ) {}
}
