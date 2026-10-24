<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Product\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductVariant
 */
final class StorefrontVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'options' => (object) $this->options,
            'price_amount' => $this->price_amount,
            'in_stock' => $this->isInStock(),
            'position' => $this->position,
        ];
    }
}
