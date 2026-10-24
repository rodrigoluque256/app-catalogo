<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductVariant>
 */
class ProductVariantFactory extends Factory
{
    protected $model = ProductVariant::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            // Hereda la tienda del producto (fuera de un request no hay tenant: se saltea el scope).
            'store_id' => fn (array $attributes): string => Product::withoutGlobalScopes()
                ->findOrFail($attributes['product_id'])
                ->store_id,
            'sku' => null,
            'options' => [
                'talle' => fake()->randomElement(['XS', 'S', 'M', 'L', 'XL']),
                'color' => fake()->unique()->safeColorName(),
            ],
            'price_amount' => null,
            'stock' => null,
            'is_active' => true,
            'position' => 0,
        ];
    }
}
