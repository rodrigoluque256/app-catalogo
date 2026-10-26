<?php

declare(strict_types=1);

namespace App\Application\Billing\Services;

use App\Domain\Billing\Contracts\PlanLimitGuard;
use App\Domain\Billing\Exceptions\PlanLimitExceededException;
use App\Domain\Billing\Models\Plan;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Models\Store;
use App\Models\User;

final readonly class PlanLimits implements PlanLimitGuard
{
    public function __construct(
        private TenantContext $tenant,
    ) {}

    /**
     * Sin suscripción la tienda se trata como Gratis: si algo falló al crearla, no gana límites de más.
     */
    public function planOf(Store $store): Plan
    {
        return $store->subscription()->with('plan')->first()->plan ?? Plan::free();
    }

    /**
     * @return array{products: int, users: int}
     */
    public function usage(Store $store): array
    {
        return [
            'products' => $this->tenant->runAs($store, fn (): int => Product::query()->count()),
            'users' => User::query()->where('store_id', $store->id)->count(),
        ];
    }

    public function ensureCanAddProduct(): void
    {
        $store = $this->tenant->store();
        $plan = $this->planOf($store);

        if ($plan->max_products !== null && $this->usage($store)['products'] >= $plan->max_products) {
            throw PlanLimitExceededException::products($plan->name, $plan->max_products);
        }
    }

    public function ensureImagesAllowed(int $new, int $current = 0): void
    {
        $plan = $this->planOf($this->tenant->store());

        if ($new > $plan->max_images_per_product && $new > $current) {
            throw PlanLimitExceededException::images($plan->name, $plan->max_images_per_product);
        }
    }

    public function ensureCanAddUser(): void
    {
        $store = $this->tenant->store();
        $plan = $this->planOf($store);

        if ($this->usage($store)['users'] >= $plan->max_users) {
            throw PlanLimitExceededException::users($plan->name, $plan->max_users);
        }
    }
}
