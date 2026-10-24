<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Product;

use App\Application\Product\DTOs\ProductImageData;
use App\Application\Product\DTOs\ProductVariantData;
use App\Application\Upload\Enums\UploadPurpose;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Product\Enums\ProductStatus;
use App\UI\Http\Rules\UploadedImage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Reglas y mapeo a DTOs compartidos entre alta (POST) y edición (PUT).
 * La pertenencia de category_id a la tienda la valida el dominio (CategoryNotFoundException).
 */
abstract class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la ruta ya exige can:products.manage
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:180', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'uuid'],
            'sku' => ['nullable', 'string', 'max:64'],
            'price_amount' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'compare_at_amount' => ['nullable', 'integer', 'gt:price_amount'],
            'status' => ['sometimes', Rule::enum(ProductStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'metadata' => ['sometimes', 'array'],

            'variants' => ['sometimes', 'array', 'max:100'],
            'variants.*.options' => ['required', 'array', 'min:1', 'max:5'],
            'variants.*.options.*' => ['required', 'string', 'max:50'],
            'variants.*.sku' => ['nullable', 'string', 'max:64', 'distinct'],
            'variants.*.price_amount' => ['nullable', 'integer', 'min:0'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.is_active' => ['sometimes', 'boolean'],

            'images' => ['sometimes', 'array', 'max:20'],
            // Solo keys generadas por /uploads/presign para esta tienda: impide apuntar al archivo de otra.
            'images.*.path' => [
                'required', 'string', 'max:500',
                'starts_with:'.UploadPurpose::keyPrefix(app(TenantContext::class)->id(), UploadPurpose::ProductImage),
                app(UploadedImage::class),
            ],
            'images.*.alt_text' => ['nullable', 'string', 'max:160'],
            'images.*.width' => ['nullable', 'integer', 'min:1'],
            'images.*.height' => ['nullable', 'integer', 'min:1'],
            'images.*.is_primary' => ['sometimes', 'boolean'],
        ];
    }

    protected function nullableString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }

    protected function nullableInteger(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(): array
    {
        /** @var array<string, mixed> */
        return (array) $this->validated('metadata', []);
    }

    /**
     * @return list<ProductVariantData>|null null si el campo no vino en el request
     */
    protected function variantsData(): ?array
    {
        if (! $this->has('variants')) {
            return null;
        }

        /** @var list<array<string, mixed>> $variants */
        $variants = array_values((array) $this->validated('variants', []));

        return array_map(ProductVariantData::fromArray(...), $variants, array_keys($variants));
    }

    /**
     * @return list<ProductImageData>|null null si el campo no vino en el request
     */
    protected function imagesData(): ?array
    {
        if (! $this->has('images')) {
            return null;
        }

        /** @var list<array<string, mixed>> $images */
        $images = array_values((array) $this->validated('images', []));

        return array_map(ProductImageData::fromArray(...), $images, array_keys($images));
    }
}
