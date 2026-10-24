<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Product;

use App\Application\Product\DTOs\CreateProductData;
use App\Domain\Product\Enums\ProductStatus;

final class StoreProductRequest extends ProductRequest
{
    public function toData(): CreateProductData
    {
        return new CreateProductData(
            name: $this->string('name')->trim()->toString(),
            priceAmount: $this->integer('price_amount'),
            status: $this->enum('status', ProductStatus::class) ?? ProductStatus::Draft,
            slug: $this->nullableString('slug'),
            description: $this->nullableString('description'),
            categoryId: $this->nullableString('category_id'),
            sku: $this->nullableString('sku'),
            compareAtAmount: $this->nullableInteger('compare_at_amount'),
            isFeatured: $this->boolean('is_featured'),
            metadata: $this->metadata(),
            variants: $this->variantsData() ?? [],
            images: $this->imagesData() ?? [],
        );
    }
}
