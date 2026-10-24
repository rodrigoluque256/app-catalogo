<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Product\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Vista pública del producto: sin SKU, stock exacto, estado ni metadata interna.
 *
 * @mixin Product
 */
final class StorefrontProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'price_amount' => $this->price_amount,
            'compare_at_amount' => $this->compare_at_amount,
            'is_featured' => $this->is_featured,
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'variants' => StorefrontVariantResource::collection(
                $this->whenLoaded('variants', fn () => $this->variants->where('is_active', true)->values()),
            ),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
        ];
    }
}
