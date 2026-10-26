<?php

declare(strict_types=1);

namespace App\Application\Billing\Services;

use App\Domain\Billing\Enums\BillingCycle;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Store\Models\Store;

final readonly class Subscriptions
{
    /** Toda tienda nueva arranca en Gratis (no hay prueba de planes pagos: el Gratis cumple ese papel). */
    public function startFree(Store $store): Subscription
    {
        return $this->switch($store, Plan::free(), null);
    }

    /**
     * Cambia el plan vigente (a mano desde el super panel hasta que llegue Mercado Pago).
     * Bajar de plan no borra nada: los límites solo frenan lo que se agrega.
     */
    public function switch(Store $store, Plan $plan, ?BillingCycle $cycle): Subscription
    {
        return Subscription::query()->updateOrCreate(
            ['store_id' => $store->id],
            [
                'plan_id' => $plan->id,
                'billing_cycle' => $plan->isFree() ? null : $cycle,
                'status' => SubscriptionStatus::Active,
            ],
        );
    }
}
