<?php

declare(strict_types=1);

namespace App\UI\Http\Resources;

use App\Domain\Billing\Models\Plan;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Tienda vista desde el super panel (ver EloquentPlatformStoreRepository: dueños y conteos ya cargados).
 *
 * @mixin Store
 */
final class PlatformStoreResource extends JsonResource
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
            'status' => $this->trashed() ? 'deleted' : $this->status->value,
            'owners' => $this->owners->map(fn (User $owner): array => [
                'name' => $owner->name,
                'email' => $owner->email,
                'invitation_pending' => $owner->invitationPending(),
            ])->values(),
            // Sin suscripción cuenta como Gratis (ver PlanLimits::planOf).
            'plan' => [
                'code' => $this->subscription->plan->code ?? Plan::FREE,
                'name' => $this->subscription->plan->name ?? 'Gratis',
                'billing_cycle' => $this->subscription?->billing_cycle?->value,
            ],
            'subscription_status' => $this->subscription?->status->value ?? 'active',
            'products_count' => (int) ($this->products_count ?? 0),
            'users_count' => (int) ($this->users_count ?? 0),
            'created_at' => $this->created_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
