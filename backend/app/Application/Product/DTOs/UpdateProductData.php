<?php

declare(strict_types=1);

namespace App\Application\Product\DTOs;

use App\Domain\Product\Enums\ProductStatus;

/**
 * Reemplazo completo de los campos del producto (PUT).
 * variants/images en null = no se tocan; un array (incluso vacío) = se reemplazan.
 */
final readonly class UpdateProductData
{
    /**
     * @param  array<string, mixed>  $metadata
     * @param  list<ProductVariantData>|null  $variants
     * @param  list<ProductImageData>|null  $images
     */
    public function __construct(
        public string $name,
        public int $priceAmount,
        public ProductStatus $status,
        public ?string $slug = null,
        public ?string $description = null,
        public ?string $categoryId = null,
        public ?string $sku = null,
        public ?int $compareAtAmount = null,
        public bool $isFeatured = false,
        public array $metadata = [],
        public ?array $variants = null,
        public ?array $images = null,
    ) {}

    public function withSlug(string $slug): self
    {
        return new self(
            name: $this->name,
            priceAmount: $this->priceAmount,
            status: $this->status,
            slug: $slug,
            description: $this->description,
            categoryId: $this->categoryId,
            sku: $this->sku,
            compareAtAmount: $this->compareAtAmount,
            isFeatured: $this->isFeatured,
            metadata: $this->metadata,
            variants: $this->variants,
            images: $this->images,
        );
    }

    /**
     * @return list<string>
     */
    public function skus(): array
    {
        return array_values(array_filter(
            [$this->sku, ...array_map(static fn (ProductVariantData $v): ?string => $v->sku, $this->variants ?? [])],
            static fn (?string $sku): bool => $sku !== null && $sku !== '',
        ));
    }
}
