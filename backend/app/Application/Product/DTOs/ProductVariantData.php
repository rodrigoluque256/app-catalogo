<?php

declare(strict_types=1);

namespace App\Application\Product\DTOs;

final readonly class ProductVariantData
{
    /**
     * @param  array<string, string>  $options  ej. ['talle' => 'M', 'color' => 'Rojo']
     */
    public function __construct(
        public array $options,
        public ?string $sku = null,
        public ?int $priceAmount = null,
        public ?int $stock = null,
        public bool $isActive = true,
        public int $position = 0,
    ) {}

    /**
     * @param  array<string, mixed>  $data  payload ya validado (snake_case)
     */
    public static function fromArray(array $data, int $position = 0): self
    {
        /** @var array<string, string> $options */
        $options = array_map(strval(...), (array) $data['options']);

        return new self(
            options: $options,
            sku: isset($data['sku']) ? (string) $data['sku'] : null,
            priceAmount: isset($data['price_amount']) ? (int) $data['price_amount'] : null,
            stock: isset($data['stock']) ? (int) $data['stock'] : null,
            isActive: (bool) ($data['is_active'] ?? true),
            position: $position,
        );
    }
}
