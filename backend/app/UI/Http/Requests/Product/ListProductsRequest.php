<?php

declare(strict_types=1);

namespace App\UI\Http\Requests\Product;

use App\Application\Product\DTOs\ProductFilters;
use App\Domain\Product\Enums\ProductStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListProductsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $sortable = array_merge(
            ProductFilters::SORTABLE,
            array_map(static fn (string $column): string => "-{$column}", ProductFilters::SORTABLE),
        );

        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'uuid'],
            'status' => ['nullable', Rule::enum(ProductStatus::class)],
            'featured' => ['nullable', 'in:0,1,true,false'],
            'sort' => ['nullable', Rule::in($sortable)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function toFilters(): ProductFilters
    {
        return new ProductFilters(
            search: $this->filled('search') ? $this->string('search')->trim()->toString() : null,
            categoryId: $this->filled('category_id') ? $this->string('category_id')->toString() : null,
            status: $this->enum('status', ProductStatus::class),
            featured: $this->filled('featured') ? $this->boolean('featured') : null,
            sort: $this->filled('sort') ? $this->string('sort')->toString() : '-created_at',
            perPage: $this->integer('per_page', 20),
            page: $this->integer('page', 1),
        );
    }
}
