<?php

declare(strict_types=1);

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Product\Models\Product;
use App\Domain\Store\Models\Store;
use Database\Seeders\RolesAndPermissionsSeeder;
use Laravel\Sanctum\Sanctum;

/** Payload mínimo de producto con N fotos (las rutas tienen el prefijo de la tienda, como las del presign). */
function productWithImages(Store $store, int $images, string $name = 'Remera'): array
{
    return [
        'name' => $name,
        'price_amount' => 100_000,
        'status' => 'active',
        'images' => array_map(fn (int $i): array => ['path' => "stores/{$store->id}/products/foto-{$i}.webp"], range(1, $images)),
    ];
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
});

describe('GET /api/v1/plans', function (): void {
    it('is public and lists the plans in order, with the annual price at -40 %', function (): void {
        $response = $this->getJson('/api/v1/plans')
            ->assertOk()
            ->assertJsonPath('data.*.code', ['free', 'basic', 'intermediate', 'advanced', 'unlimited'])
            ->assertJsonPath('data.1', [
                'code' => 'basic', 'name' => 'Basic', 'monthly_price' => 1_000_000, 'annual_price' => 7_200_000,
                'limits' => ['products' => 250, 'images_per_product' => 5, 'users' => 2],
            ])
            ->assertJsonPath('data.4.limits', ['products' => null, 'images_per_product' => 20, 'users' => 20]);

        foreach ($response->json('data') as $plan) {
            expect($plan['annual_price'])->toBe(intdiv($plan['monthly_price'] * 72, 10));
        }
    });

    it('hides inactive plans', function (): void {
        Plan::query()->where('code', 'advanced')->update(['is_active' => false]);

        expect($this->getJson('/api/v1/plans')->json('data.*.code'))->not->toContain('advanced');
    });
});

describe('limits of the Gratis plan', function (): void {
    beforeEach(function (): void {
        $this->store = Store::factory()->onPlan('free')->create();
        actingAsStoreUser($this->store);
    });

    it('allows 25 products and rejects the 26th with an upgrade message', function (): void {
        Product::factory()->count(25)->forStore($this->store)->create();

        $this->postJson('/api/v1/admin/products', productWithImages($this->store, 1))
            ->assertUnprocessable()
            ->assertJsonPath('errors.plan.0', 'Tu plan Gratis permite hasta 25 productos. Subí de plan para cargar más.');
    });

    it('counts only products that are not deleted', function (): void {
        Product::factory()->count(25)->forStore($this->store)->create()->first()->delete();

        $this->postJson('/api/v1/admin/products', productWithImages($this->store, 1))->assertCreated();
    });

    it('allows 3 photos per product and rejects 4', function (): void {
        $this->postJson('/api/v1/admin/products', productWithImages($this->store, 3))->assertCreated();

        $this->postJson('/api/v1/admin/products', productWithImages($this->store, 4, 'Otra'))
            ->assertUnprocessable()
            ->assertJsonPath('errors.images.0', 'Tu plan Gratis permite hasta 3 fotos por producto. Subí de plan para agregar más.');
    });

    it('keeps the photos of a product after a downgrade but does not let it add more', function (): void {
        Subscription::query()->where('store_id', $this->store->id)->update(['plan_id' => Plan::query()->where('code', 'unlimited')->value('id')]);
        $id = $this->postJson('/api/v1/admin/products', productWithImages($this->store, 5))->assertCreated()->json('data.id');
        Subscription::query()->where('store_id', $this->store->id)->update(['plan_id' => Plan::free()->id]);

        $this->putJson("/api/v1/admin/products/{$id}", [...productWithImages($this->store, 5), 'name' => 'Renombrada'])->assertOk();
        $this->putJson("/api/v1/admin/products/{$id}", productWithImages($this->store, 4))->assertOk();
        $this->putJson("/api/v1/admin/products/{$id}", productWithImages($this->store, 5))->assertUnprocessable();
        $this->putJson("/api/v1/admin/products/{$id}", ['name' => 'Sin tocar fotos', 'price_amount' => 1, 'status' => 'active'])->assertOk();
    });

    it('allows only the owner: a second user is rejected', function (): void {
        $this->postJson('/api/v1/admin/users', ['name' => 'Editora', 'email' => 'editora@tienda.test', 'role' => 'editor'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.plan.0', 'Tu plan Gratis permite hasta 1 usuario. Subí de plan para sumar más.');
    });

    it('reports the plan and usage in /admin/plan and the limits in /auth/me', function (): void {
        Product::factory()->count(2)->forStore($this->store)->create();

        $this->getJson('/api/v1/admin/plan')
            ->assertOk()
            ->assertJsonPath('data.plan.code', 'free')
            ->assertJsonPath('data.subscription', ['status' => 'active', 'billing_cycle' => null, 'current_period_end' => null])
            ->assertJsonPath('data.usage', ['products' => 2, 'users' => 1]);

        $this->getJson('/api/v1/auth/me')->assertJsonPath('data.plan.limits.users', 1);
    });
});

it('treats a store without subscription as Gratis', function (): void {
    $store = Store::factory()->create();
    Subscription::query()->where('store_id', $store->id)->delete();
    actingAsStoreUser($store);

    $this->getJson('/api/v1/admin/plan')->assertJsonPath('data.plan.code', 'free');
    $this->postJson('/api/v1/admin/products', productWithImages($store, 4))->assertUnprocessable();
});

it('lets an Intermediate store have 3 users', function (): void {
    $store = Store::factory()->onPlan('intermediate')->create();
    actingAsStoreUser($store);

    foreach (['a', 'b'] as $name) {
        $this->postJson('/api/v1/admin/users', ['name' => $name, 'email' => "{$name}@tienda.test", 'role' => 'editor'])->assertCreated();
    }

    $this->postJson('/api/v1/admin/users', ['name' => 'c', 'email' => 'c@tienda.test', 'role' => 'editor'])
        ->assertJsonPath('errors.plan.0', 'Tu plan Intermediate permite hasta 3 usuarios. Subí de plan para sumar más.');
});

describe('plans in the platform panel', function (): void {
    beforeEach(function (): void {
        $this->admin = PlatformAdmin::factory()->create();
        Sanctum::actingAs($this->admin, ['platform']);
    });

    it('starts stores created from the panel on Gratis and shows the plan in the list', function (): void {
        $this->postJson('/api/v1/platform/stores', ['name' => 'Nueva', 'slug' => 'nueva', 'owner_name' => 'Ana', 'owner_email' => 'ana@nueva.test'])
            ->assertCreated()
            ->assertJsonPath('data.plan', ['code' => 'free', 'name' => 'Gratis', 'billing_cycle' => null]);

        $this->getJson('/api/v1/platform/stores?search=nueva')->assertJsonPath('data.0.plan.code', 'free');
    });

    it('changes the plan by hand, requires the cycle for paid plans and audits it', function (): void {
        $store = Store::factory()->onPlan('free')->create();

        $this->patchJson("/api/v1/platform/stores/{$store->id}/plan", ['plan' => 'basic'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.billing_cycle.0', 'Elegí si el plan es mensual o anual.');
        $this->patchJson("/api/v1/platform/stores/{$store->id}/plan", ['plan' => 'premium', 'billing_cycle' => 'monthly'])
            ->assertJsonPath('errors.plan.0', 'Ese plan no existe.');

        $this->patchJson("/api/v1/platform/stores/{$store->id}/plan", ['plan' => 'advanced', 'billing_cycle' => 'annual'])
            ->assertOk()
            ->assertJsonPath('data.plan', ['code' => 'advanced', 'name' => 'Advanced', 'billing_cycle' => 'annual']);

        $this->patchJson("/api/v1/platform/stores/{$store->id}/plan", ['plan' => 'free', 'billing_cycle' => 'annual'])
            ->assertJsonPath('data.plan.billing_cycle', null);

        // jsonb reordena las claves: se compara sin importar el orden.
        expect(PlatformAuditLog::query()->where('action', 'plan_changed')->orderBy('id')->pluck('metadata')->all())->toEqual([
            ['slug' => $store->slug, 'from' => 'free', 'to' => 'advanced', 'billing_cycle' => 'annual'],
            ['slug' => $store->slug, 'from' => 'advanced', 'to' => 'free', 'billing_cycle' => null],
        ]);
    });
});
