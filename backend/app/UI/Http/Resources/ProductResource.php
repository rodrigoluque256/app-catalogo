<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Product\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
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
            'sku' => $this->sku,
            'price_amount' => $this->price_amount,
            'compare_at_amount' => $this->compare_at_amount,
            'status' => $this->status->value,
            'is_featured' => $this->is_featured,
            'metadata' => (object) $this->metadata,
            'published_at' => $this->published_at?->toIso8601String(),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'images' => ProductImageResource::collection($this->whenLoaded('images')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
