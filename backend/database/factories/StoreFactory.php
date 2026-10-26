<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Application\Billing\Services\Subscriptions;
use App\Domain\Billing\Enums\BillingCycle;
use App\Domain\Billing\Models\Plan;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Store>
 */
class StoreFactory extends Factory
{
    protected $model = Store::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::limit(Str::slug($name), 50, '').'-'.Str::lower(Str::random(6)),
            'primary_color' => '#18181B',
            'accent_color' => '#2563EB',
            'whatsapp_number' => '+54911'.fake()->numerify('########'),
            'whatsapp_message_template' => 'Hola! Me interesa {product}.',
            'currency' => 'ARS',
            'locale' => 'es-AR',
            'status' => StoreStatus::Active,
            'settings' => [],
        ];
    }

    /**
     * Por defecto los tests corren en Unlimited: los límites de plan se prueban aparte (onPlan('free')).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Store $store): void {
            if (! $store->subscription()->exists()) {
                app(Subscriptions::class)->switch($store, Plan::query()->where('code', 'unlimited')->firstOrFail(), BillingCycle::Monthly);
            }
        });
    }

    public function onPlan(string $code): static
    {
        return $this->afterCreating(function (Store $store) use ($code): void {
            app(Subscriptions::class)->switch($store, Plan::query()->where('code', $code)->firstOrFail(), BillingCycle::Monthly);
        });
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => StoreStatus::Suspended]);
    }
}
