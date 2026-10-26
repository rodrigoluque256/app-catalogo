<?php

declare(strict_types=1);

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Enums\StoreStatus;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

/** Tienda con un dueño (rol owner en esa tienda) y, opcionalmente, un editor. */
function storeWithOwner(array $attributes = [], string $ownerEmail = 'dueno@tienda.test'): Store
{
    $store = Store::factory()->create($attributes);
    $owner = User::factory()->forStore($store)->create(['email' => $ownerEmail, 'name' => 'Dueño']);
    setPermissionsTeamId($store->id);
    $owner->assignRole('owner');
    setPermissionsTeamId(null);

    return $store;
}

function revalidatedTags(): array
{
    return Http::recorded()->map(fn (array $pair): array => $pair[0]->data()['tags'])->all();
}

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    RateLimiter::clear('login');
    config([
        'services.storefront.revalidate_url' => 'https://front.test/api/revalidate',
        'services.storefront.revalidate_secret' => 's3cret',
    ]);
    Http::fake(['front.test/*' => Http::response(['revalidated' => true])]);

    $this->admin = PlatformAdmin::factory()->create();
});

describe('access', function (): void {
    it('requires a platform session token', function (): void {
        $this->getJson('/api/v1/platform/stores')->assertUnauthorized();

        actingAsStoreUser(Store::factory()->create());
        $this->getJson('/api/v1/platform/stores')->assertForbidden();

        app('auth')->forgetGuards();
        Sanctum::actingAs($this->admin, ['platform:challenge']);
        $this->getJson('/api/v1/platform/stores')->assertForbidden();
    });
});

describe('GET /api/v1/platform/stores', function (): void {
    beforeEach(fn () => Sanctum::actingAs($this->admin, ['platform']));

    it('lists stores with owners and counts, newest first', function (): void {
        $old = storeWithOwner(['name' => 'Vieja', 'created_at' => now()->subDay()]);
        $new = storeWithOwner(['name' => 'Nueva'], 'otra@tienda.test');
        User::factory()->forStore($new)->create(); // sin rol owner: cuenta como usuario, no como dueño
        app(TenantContext::class)->runAs($new, fn () => Product::factory()->count(2)->create(['store_id' => $new->id]));

        $this->getJson('/api/v1/platform/stores')
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.id', $new->id)
            ->assertJsonPath('data.0.products_count', 2)
            ->assertJsonPath('data.0.users_count', 2)
            ->assertJsonPath('data.0.owners', [['name' => 'Dueño', 'email' => 'otra@tienda.test']])
            ->assertJsonPath('data.1.id', $old->id)
            ->assertJsonPath('data.1.status', 'active');
    });

    it('filters by status (deleted only on request) and searches by name, slug or owner email', function (): void {
        $active = storeWithOwner(['name' => 'Almacén Luna', 'slug' => 'luna']);
        $suspended = storeWithOwner(['status' => StoreStatus::Suspended], 'suspendida@tienda.test');
        $deleted = storeWithOwner([], 'borrada@tienda.test');
        $deleted->delete();

        expect($this->getJson('/api/v1/platform/stores')->json('data.*.id'))->toEqualCanonicalizing([$active->id, $suspended->id]);
        expect($this->getJson('/api/v1/platform/stores?status=suspended')->json('data.*.id'))->toBe([$suspended->id]);
        $this->getJson('/api/v1/platform/stores?status=deleted')
            ->assertJsonPath('data.0.id', $deleted->id)
            ->assertJsonPath('data.0.status', 'deleted');

        expect($this->getJson('/api/v1/platform/stores?search=LUNA')->json('data.*.id'))->toBe([$active->id]);
        expect($this->getJson('/api/v1/platform/stores?search=suspendida@')->json('data.*.id'))->toBe([$suspended->id]);
        $this->getJson('/api/v1/platform/stores?status=otro')->assertUnprocessable();
    });

    it('shows one store, including deleted ones, and 404s on unknown or malformed ids', function (): void {
        $store = storeWithOwner();
        $store->delete();

        $this->getJson("/api/v1/platform/stores/{$store->id}")->assertOk()->assertJsonPath('data.status', 'deleted');
        $this->getJson('/api/v1/platform/stores/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/v1/platform/stores/no-es-uuid')->assertNotFound();
    });
});

describe('POST /api/v1/platform/stores', function (): void {
    beforeEach(fn () => Sanctum::actingAs($this->admin, ['platform']));

    it('creates the store and its owner with a one-time temporary password', function (): void {
        $response = $this->postJson('/api/v1/platform/stores', [
            'name' => 'Ferretería Sol', 'slug' => 'Ferreteria-Sol', 'owner_name' => 'Ana', 'owner_email' => 'Ana@Sol.test',
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'ferreteria-sol')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.owners', [['name' => 'Ana', 'email' => 'ana@sol.test']]);

        $store = Store::query()->where('slug', 'ferreteria-sol')->sole();
        expect($store->currency)->toBe('ARS'); // valores por defecto de la base
        expect(PlatformAuditLog::query()->where('action', 'store_created')->value('store_id'))->toBe($store->id);

        // El dueño entra con la temporal y la API le exige cambiarla.
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'ana@sol.test', 'password' => $response->json('data.temporary_password')])
            ->assertOk()
            ->json('data.token');
        withBearer($token, 'GET', '/api/v1/admin/products')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        withBearer($token, 'GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('data.store.id', $store->id)->assertJsonPath('data.roles', ['owner']);
    });

    it('rejects reserved, taken (also by deleted stores) and malformed slugs, and a used email', function (): void {
        storeWithOwner(['slug' => 'ocupado']);
        $deleted = storeWithOwner(['slug' => 'era-de-otra'], 'x@tienda.test');
        $deleted->delete();

        $cases = [
            ['platform', 'Ese subdominio está reservado por la plataforma.'],
            ['ocupado', 'Ese subdominio ya está en uso.'],
            ['era-de-otra', 'Ese subdominio ya está en uso.'],
            ['-mal-', 'Solo minúsculas, números y guiones (sin guion al principio ni al final).'],
            ['ab', 'El subdominio debe tener al menos 3 caracteres.'],
        ];

        foreach ($cases as [$slug, $message]) {
            $this->postJson('/api/v1/platform/stores', ['name' => 'X', 'slug' => $slug, 'owner_name' => 'X', 'owner_email' => 'nuevo@x.test'])
                ->assertUnprocessable()
                ->assertJsonPath('errors.slug.0', $message);
        }

        $this->postJson('/api/v1/platform/stores', ['name' => 'X', 'slug' => 'libre', 'owner_name' => 'X', 'owner_email' => 'DUENO@tienda.test'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.owner_email.0', 'Ya existe un usuario con ese email.');

        expect(Store::query()->where('slug', 'libre')->exists())->toBeFalse();
    });
});

describe('suspend, reactivate, delete and restore', function (): void {
    beforeEach(function (): void {
        Sanctum::actingAs($this->admin, ['platform']);
        $this->store = storeWithOwner(['slug' => 'mi-tienda']);
        $this->ownerToken = User::query()->where('email', 'dueno@tienda.test')->sole()->createToken('admin')->plainTextToken;
    });

    it('suspends: the storefront 404s, the owner cannot log in and their sessions are closed', function (): void {
        $this->patchJson("/api/v1/platform/stores/{$this->store->id}", ['status' => 'suspended'])
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');

        expect(revalidatedTags())->toBe([['store:mi-tienda']]);
        Http::assertSent(fn (Request $request): bool => $request->hasHeader('x-revalidate-secret', 's3cret'));
        expect(PlatformAuditLog::query()->where('action', 'store_suspended')->value('metadata'))->toBe(['slug' => 'mi-tienda']);

        $this->getJson('/api/v1/storefront/store', ['X-Store-Slug' => 'mi-tienda'])->assertNotFound();
        $this->postJson('/api/v1/auth/login', ['email' => 'dueno@tienda.test', 'password' => 'password'])->assertUnprocessable();
        expect(DB::table('personal_access_tokens')->where('tokenable_type', (new User)->getMorphClass())->count())->toBe(0);
    });

    it('reactivates and does nothing when the status does not change', function (): void {
        $this->patchJson("/api/v1/platform/stores/{$this->store->id}", ['status' => 'active'])->assertOk();
        expect(Http::recorded())->toHaveCount(0);

        $this->store->forceFill(['status' => StoreStatus::Suspended])->save();
        $this->patchJson("/api/v1/platform/stores/{$this->store->id}", ['status' => 'active'])->assertJsonPath('data.status', 'active');

        expect(revalidatedTags())->toBe([['store:mi-tienda']]);
        $this->getJson('/api/v1/storefront/store', ['X-Store-Slug' => 'mi-tienda'])->assertOk();
    });

    it('deletes only after typing the exact slug, keeps the slug taken and can restore as suspended', function (): void {
        $this->deleteJson("/api/v1/platform/stores/{$this->store->id}", ['confirm_slug' => 'otra'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.confirm_slug.0', 'Escribí el subdominio exacto de la tienda para confirmar el borrado.');
        $this->deleteJson("/api/v1/platform/stores/{$this->store->id}")->assertUnprocessable();

        $this->deleteJson("/api/v1/platform/stores/{$this->store->id}", ['confirm_slug' => ' Mi-Tienda '])->assertNoContent();

        expect($this->store->refresh()->trashed())->toBeTrue();
        expect(revalidatedTags())->toBe([['store:mi-tienda']]);
        $this->getJson('/api/v1/storefront/store', ['X-Store-Slug' => 'mi-tienda'])->assertNotFound();
        $this->patchJson("/api/v1/platform/stores/{$this->store->id}", ['status' => 'active'])->assertUnprocessable();
        $this->postJson('/api/v1/platform/stores', ['name' => 'X', 'slug' => 'mi-tienda', 'owner_name' => 'X', 'owner_email' => 'n@x.test'])
            ->assertJsonPath('errors.slug.0', 'Ese subdominio ya está en uso.');

        $this->postJson("/api/v1/platform/stores/{$this->store->id}/restore")
            ->assertOk()
            ->assertJsonPath('data.status', 'suspended');
        $this->postJson("/api/v1/platform/stores/{$this->store->id}/restore")->assertUnprocessable();

        expect(PlatformAuditLog::query()->pluck('action')->all())->toBe(['store_deleted', 'store_restored']);
    });
});
