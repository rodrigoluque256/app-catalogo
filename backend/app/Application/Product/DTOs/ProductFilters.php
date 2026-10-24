<?php

declare(strict_types=1);

namespace App\Application\Product\DTOs;

use App\Domain\Product\Enums\ProductStatus;

final readonly class ProductFilters
{
    /** @var list<string> */
    public const array SORTABLE = ['name', 'price_amount', 'created_at'];

    public function __construct(
        public ?string $search = null,
        public ?string $categoryId = null,
        public ?ProductStatus $status = null,
        public ?bool $featured = null,
        public string $sort = '-created_at',
        public int $perPage = 20,
        public int $page = 1,
    ) {}

    /**
     * "-price_amount" => ['price_amount', 'desc']. Columnas fuera de la whitelist caen al default.
     *
     * @return array{0: string, 1: 'asc'|'desc'}
     */
    public function sortColumnAndDirection(): array
    {
        $direction = str_starts_with($this->sort, '-') ? 'desc' : 'asc';
        $column = ltrim($this->sort, '-');

        return in_array($column, self::SORTABLE, true) ? [$column, $direction] : ['created_at', 'desc'];
    }
}
