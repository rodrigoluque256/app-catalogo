<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions\Stores;

use App\Application\Billing\Services\PlanLimits;
use App\Application\Billing\Services\Subscriptions;
use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Billing\Enums\BillingCycle;
use App\Domain\Billing\Models\Plan;
use App\Domain\Platform\Exceptions\StoreManagementException;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Store\Models\Store;
use Illuminate\Support\Facades\DB;

final readonly class ChangeStorePlanAction
{
    public function __construct(
        private Subscriptions $subscriptions,
        private PlanLimits $limits,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * Cambio manual (ej. pagó por transferencia) hasta que Mercado Pago lo haga solo (P2.6).
     * Bajar de plan no borra nada: los límites solo frenan lo que se agregue.
     */
    public function execute(PlatformAdmin $admin, Store $store, Plan $plan, ?BillingCycle $cycle): void
    {
        if ($store->trashed()) {
            throw StoreManagementException::deleted();
        }

        $from = $this->limits->planOf($store);

        DB::transaction(function () use ($admin, $store, $plan, $cycle, $from): void {
            $this->subscriptions->switch($store, $plan, $cycle);
            $this->audit->record('plan_changed', $admin, $store->id, [
                'slug' => $store->slug,
                'from' => $from->code,
                'to' => $plan->code,
                'billing_cycle' => $plan->isFree() ? null : $cycle?->value,
            ]);
        });
    }
}
