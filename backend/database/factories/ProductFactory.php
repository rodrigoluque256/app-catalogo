<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Store\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = ucfirst((string) fake()->unique()->words(3, true));

        return [
            'store_id' => Store::factory(),
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->paragraph(),
            'sku' => null,
            'price_amount' => fake()->numberBetween(1_000, 500_000),
            'compare_at_amount' => null,
            'status' => ProductStatus::Active,
            'is_featured' => false,
            'metadata' => [],
            'published_at' => now(),
        ];
    }

    public function forStore(Store $store): static
    {
        return $this->state(fn (): array => ['store_id' => $store->id]);
    }

    public function inCategory(Category $category): static
    {
        return $this->state(fn (): array => [
            'store_id' => $category->store_id,
            'category_id' => $category->id,
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Draft, 'published_at' => null]);
    }
}
