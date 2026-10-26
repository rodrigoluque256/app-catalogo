<?php

declare(strict_types=1);

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Enums\BillingCycle;
use App\Domain\Billing\Enums\SubscriptionStatus;
use App\Domain\Store\Models\Store;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Suscripción vigente de una tienda (una por tienda).
 *
 * @property string $id
 * @property string $store_id
 * @property int $plan_id
 * @property BillingCycle|null $billing_cycle
 * @property SubscriptionStatus $status
 * @property Carbon|null $current_period_end
 * @property string|null $mercadopago_preapproval_id
 * @property-read Plan $plan
 */
class Subscription extends Model
{
    use HasUuids;

    protected $guarded = ['id'];

    protected $attributes = [
        'status' => 'active',
    ];

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<Store, $this>
     */
    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'billing_cycle' => BillingCycle::class,
            'status' => SubscriptionStatus::class,
            'current_period_end' => 'datetime',
        ];
    }
}
