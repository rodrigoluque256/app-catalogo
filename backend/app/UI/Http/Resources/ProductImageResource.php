<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Product\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ProductImage
 */
final class ProductImageResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => Storage::disk($this->disk)->url($this->path),
            'path' => $this->path,
            'alt_text' => $this->alt_text,
            'width' => $this->width,
            'height' => $this->height,
            'position' => $this->position,
            'is_primary' => $this->is_primary,
            'variant_id' => $this->variant_id,
        ];
    }
}
