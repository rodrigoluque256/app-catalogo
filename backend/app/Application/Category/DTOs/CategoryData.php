<?php

declare(strict_types=1);

namespace App\Application\Category\DTOs;

final readonly class CategoryData
{
    public function __construct(
        public string $name,
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $parentId = null,
        public int $position = 0,
        public bool $isActive = true,
    ) {}
}
