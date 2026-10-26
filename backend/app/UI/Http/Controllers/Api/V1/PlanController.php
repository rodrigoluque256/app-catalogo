<?php

declare(strict_types=1);

namespace App\UI\Http\Controllers\Api\V1;

use App\Application\Billing\Services\PlanLimits;
use App\Domain\Billing\Models\Plan;
use App\Domain\Shared\Tenancy\TenantContext;
use App\UI\Http\Resources\PlanResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class PlanController
{
    /** Público: lo usan la landing, el registro y "Mi plan" (una sola fuente de precios y límites). */
    public function index(): AnonymousResourceCollection
    {
        return PlanResource::collection(Plan::query()->active()->get());
    }

    /** "Mi plan" del panel de la tienda: plan vigente, estado de la suscripción y uso. */
    public function current(Request $request, TenantContext $tenant, PlanLimits $limits): JsonResponse
    {
        $store = $tenant->store();
        $subscription = $store->subscription()->first();

        return response()->json(['data' => [
            'plan' => PlanResource::make($limits->planOf($store))->resolve($request),
            'subscription' => [
                'status' => $subscription?->status->value ?? 'active',
                'billing_cycle' => $subscription?->billing_cycle?->value,
                'current_period_end' => $subscription?->current_period_end?->toIso8601String(),
            ],
            'usage' => $limits->usage($store),
        ]]);
    }
}
