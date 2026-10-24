<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Store\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Configuración pública de la tienda que consume el storefront (branding + WhatsApp).
 *
 * @mixin Store
 */
final class StoreResource extends JsonResource
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
            'custom_domain' => $this->custom_domain,
            'logo_url' => $this->logo_path !== null ? Storage::disk('r2')->url($this->logo_path) : null,
            'theme' => [
                'primary_color' => $this->primary_color,
                'accent_color' => $this->accent_color,
            ],
            'whatsapp' => [
                'number' => $this->whatsapp_number,
                'message_template' => $this->whatsapp_message_template,
            ],
            'currency' => $this->currency,
            'locale' => $this->locale,
        ];
    }
}
