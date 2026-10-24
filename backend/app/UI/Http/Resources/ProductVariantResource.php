<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Product\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
final class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'options' => (object) $this->options,
            'price_amount' => $this->price_amount,
            'stock' => $this->stock,
            'in_stock' => $this->isInStock(),
            'is_active' => $this->is_active,
            'position' => $this->position,
        ];
    }
}
