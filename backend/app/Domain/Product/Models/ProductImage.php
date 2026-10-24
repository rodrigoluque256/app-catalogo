<?php

declare(strict_types=1);

namespace App\Domain\Product\Models;

use App\Domain\Shared\Tenancy\BelongsToStore;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $store_id
 * @property string $product_id
 * @property string|null $variant_id
 * @property string $disk
 * @property string $path
 * @property string|null $alt_text
 * @property int|null $width
 * @property int|null $height
 * @property int $position
 * @property bool $is_primary
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProductImage extends Model
{
    use BelongsToStore;
    use HasUuids;

    protected $fillable = [
        'variant_id',
        'disk',
        'path',
        'alt_text',
        'width',
        'height',
        'position',
        'is_primary',
    ];

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
            'is_primary' => 'boolean',
        ];
    }
}
