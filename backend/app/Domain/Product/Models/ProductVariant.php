<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Shared\Tenancy\BelongsToStore;
use Database\Factories\ProductVariantFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $product_id
 * @property string|null $sku
 * @property array<string, string> $options
 * @property int|null $price_amount
 * @property int|null $stock
 * @property bool $is_active
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Product $product
 */
class ProductVariant extends Model
{
    use BelongsToStore;

    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    use HasUuids;

    protected $fillable = [
        'sku',
        'options',
        'price_amount',
        'stock',
        'is_active',
        'position',
    ];

    public function isInStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'array',
            'price_amount' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
