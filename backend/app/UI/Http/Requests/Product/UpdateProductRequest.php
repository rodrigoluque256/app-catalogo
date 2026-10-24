<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Product;

use App\Application\Product\DTOs\UpdateProductData;
use App\Domain\Product\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

final class UpdateProductRequest extends ProductRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'status' => ['required', Rule::enum(ProductStatus::class)],
        ];
    }

    public function toData(): UpdateProductData
    {
        return new UpdateProductData(
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
            variants: $this->variantsData(),
            images: $this->imagesData(),
        );
    }
}
