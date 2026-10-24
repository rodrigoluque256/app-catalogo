<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Application\Product\DTOs\CreateProductData;
use App\Application\Product\DTOs\ProductFilters;
use App\Application\Product\DTOs\ProductImageData;
use App\Application\Product\DTOs\ProductVariantData;
use App\Application\Product\DTOs\UpdateProductData;
use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Exceptions\ProductNotFoundException;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductImage;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Product\Repositories\ProductRepositoryInterface;
use App\Domain\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Doble barrera multi-tenant: el StoreScope global de los modelos y, además,
 * el filtro/asignación explícita de store_id en cada operación de este repositorio.
 */
final readonly class EloquentProductRepository implements ProductRepositoryInterface
{
    /** @var list<string> */
    private const array RELATIONS = ['category', 'variants', 'images'];

    public function __construct(
        private TenantContext $tenant,
    ) {}

    public function findById(string $id): ?Product
    {
        // Un id que no es UUID haría fallar a Postgres ("invalid input syntax for type uuid").
        if (! Str::isUuid($id)) {
            return null;
        }

        return $this->query()->with(self::RELATIONS)->whereKey($id)->first();
    }

    public function findBySlug(string $slug): ?Product
    {
        return $this->query()->with(self::RELATIONS)->where('slug', $slug)->first();
    }

    public function paginate(ProductFilters $filters): LengthAwarePaginator
    {
        [$column, $direction] = $filters->sortColumnAndDirection();

        return $this->query()
            ->with(self::RELATIONS)
            ->when($filters->search, function (Builder $query, string $term): void {
                $query->where(function (Builder $query) use ($term): void {
                    $query->whereRaw("search_vector @@ websearch_to_tsquery('spanish', ?)", [$term])
                        ->orWhere('name', 'ilike', '%'.addcslashes($term, '%_\\').'%');
                });
            })
            ->when($filters->categoryId, fn (Builder $query, string $categoryId) => $query->where('category_id', $categoryId))
            ->when($filters->status, fn (Builder $query, ProductStatus $status) => $query->where('status', $status->value))
            ->when($filters->featured !== null, fn (Builder $query) => $query->where('is_featured', $filters->featured))
            ->orderBy($column, $direction)
            ->orderBy('id')
            ->paginate($filters->perPage, ['*'], 'page', $filters->page);
    }

    public function slugExists(string $slug, ?string $ignoreProductId = null): bool
    {
        return $this->query()
            ->where('slug', $slug)
            ->when($ignoreProductId, fn (Builder $query, string $id) => $query->whereKeyNot($id))
            ->exists();
    }

    public function existingSkus(array $skus, ?string $ignoreProductId = null): array
    {
        if ($skus === []) {
            return [];
        }

        $productSkus = $this->query()
            ->whereIn('sku', $skus)
            ->when($ignoreProductId, fn (Builder $query, string $id) => $query->whereKeyNot($id))
            ->pluck('sku');

        $variantSkus = ProductVariant::query()
            ->where('store_id', $this->tenant->id())
            ->whereIn('sku', $skus)
            ->when($ignoreProductId, fn (Builder $query, string $id) => $query->where('product_id', '!=', $id))
            ->pluck('sku');

        /** @var list<string> */
        return $productSkus->merge($variantSkus)->unique()->values()->all();
    }

    public function create(CreateProductData $data): Product
    {
        $product = new Product;
        $product->forceFill([
            'store_id' => $this->tenant->id(),
            'category_id' => $data->categoryId,
            'name' => $data->name,
            'slug' => $data->slug ?? Str::slug($data->name),
            'description' => $data->description,
            'sku' => $data->sku,
            'price_amount' => $data->priceAmount,
            'compare_at_amount' => $data->compareAtAmount,
            'is_featured' => $data->isFeatured,
            'metadata' => $data->metadata,
        ]);
        $product->applyStatus($data->status);
        $product->save();

        $this->replaceVariants($product, $data->variants);
        $this->replaceImages($product, $data->images);

        return $product->load(self::RELATIONS);
    }

    public function update(Product $product, UpdateProductData $data): Product
    {
        $this->assertBelongsToTenant($product);

        $product->forceFill([
            'category_id' => $data->categoryId,
            'name' => $data->name,
            'slug' => $data->slug ?? $product->slug,
            'description' => $data->description,
            'sku' => $data->sku,
            'price_amount' => $data->priceAmount,
            'compare_at_amount' => $data->compareAtAmount,
            'is_featured' => $data->isFeatured,
            'metadata' => $data->metadata,
        ]);
        $product->applyStatus($data->status);
        $product->save();

        if ($data->variants !== null) {
            $this->replaceVariants($product, $data->variants);
        }

        if ($data->images !== null) {
            $this->replaceImages($product, $data->images);
        }

        return $product->load(self::RELATIONS);
    }

    public function delete(Product $product): void
    {
        $this->assertBelongsToTenant($product);

        $product->delete();
    }

    /**
     * @return Builder<Product>
     */
    private function query(): Builder
    {
        return Product::query()->where('products.store_id', $this->tenant->id());
    }

    private function assertBelongsToTenant(Product $product): void
    {
        if ($product->store_id !== $this->tenant->id()) {
            throw ProductNotFoundException::withId($product->id);
        }
    }

    /**
     * MVP: reemplazo total. Cuando haya carrito/órdenes se migrará a un sync que preserve ids.
     *
     * @param  list<ProductVariantData>  $variants
     */
    private function replaceVariants(Product $product, array $variants): void
    {
        ProductVariant::query()
            ->where('store_id', $product->store_id)
            ->where('product_id', $product->id)
            ->delete();

        foreach ($variants as $variant) {
            $product->variants()->forceCreate([
                'store_id' => $product->store_id,
                'sku' => $variant->sku,
                'options' => $variant->options,
                'price_amount' => $variant->priceAmount,
                'stock' => $variant->stock,
                'is_active' => $variant->isActive,
                'position' => $variant->position,
            ]);
        }
    }

    /**
     * Garantiza exactamente una imagen principal: la primera marcada, o la primera de la lista.
     *
     * @param  list<ProductImageData>  $images
     */
    private function replaceImages(Product $product, array $images): void
    {
        ProductImage::query()
            ->where('store_id', $product->store_id)
            ->where('product_id', $product->id)
            ->delete();

        $primaryIndex = 0;
        foreach ($images as $index => $image) {
            if ($image->isPrimary) {
                $primaryIndex = $index;
                break;
            }
        }

        foreach ($images as $index => $image) {
            $product->images()->forceCreate([
                'store_id' => $product->store_id,
                'disk' => $image->disk,
                'path' => $image->path,
                'alt_text' => $image->altText,
                'width' => $image->width,
                'height' => $image->height,
                'position' => $image->position,
                'is_primary' => $index === $primaryIndex,
            ]);
        }
    }
}
