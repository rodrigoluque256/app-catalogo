<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Shared\Tenancy\BelongsToStore;
use Carbon\CarbonImmutable;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Aggregate root: el producto se persiste junto con sus variantes e imágenes.
 *
 * @property string $id
 * @property string $store_id
 * @property string|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $sku
 * @property int $price_amount
 * @property int|null $compare_at_amount
 * @property ProductStatus $status
 * @property bool $is_featured
 * @property array<string, mixed> $metadata
 * @property CarbonImmutable|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, ProductImage> $images
 */
class Product extends Model
{
    use BelongsToStore;

    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'sku',
        'price_amount',
        'compare_at_amount',
        'is_featured',
        'metadata',
    ];

    protected $hidden = ['search_vector'];

    protected $attributes = [
        'status' => 'draft',
        'is_featured' => false,
        'metadata' => '{}',
    ];

    /**
     * Cambia el estado y registra la primera fecha de publicación.
     */
    public function applyStatus(ProductStatus $status): void
    {
        $this->status = $status;

        if ($status === ProductStatus::Active && $this->published_at === null) {
            $this->published_at = CarbonImmutable::now();
        }
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    /**
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position');
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_amount' => 'integer',
            'compare_at_amount' => 'integer',
            'status' => ProductStatus::class,
            'is_featured' => 'boolean',
            'metadata' => 'array',
            'published_at' => 'immutable_datetime',
        ];
    }
}
