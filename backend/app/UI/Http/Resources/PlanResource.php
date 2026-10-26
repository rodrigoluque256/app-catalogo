<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Billing\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Plan público (landing, registro, "Mi plan"). Precios en centavos ARS; `products` null = sin límite.
 *
 * @mixin Plan
 */
final class PlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'name' => $this->name,
            'monthly_price' => $this->monthly_price,
            'annual_price' => $this->annual_price,
            'limits' => [
                'products' => $this->max_products,
                'images_per_product' => $this->max_images_per_product,
                'users' => $this->max_users,
            ],
        ];
    }
}
