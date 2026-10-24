<?php

declare(strict_types=1);

use App\Domain\Store\Contracts\UploadedObject;
use App\Domain\Store\Contracts\UploadedObjectInspector;
use App\Domain\Store\Models\Store;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('r2');

    $this->store = Store::factory()->create(['whatsapp_number' => '+5491122334455']);
    $this->otherStore = Store::factory()->create();
});

describe('GET /api/v1/admin/store', function (): void {
    it('returns the settings of the authenticated store', function (): void {
        actingAsStoreUser($this->store);

        $this->getJson('/api/v1/admin/store')
            ->assertOk()
            ->assertJsonPath('data.id', $this->store->id)
            ->assertJsonPath('data.whatsapp.number', '+5491122334455');
    });
});

describe('PATCH /api/v1/admin/store', function (): void {
    it('updates only the sent fields and normalizes colors and currency', function (): void {
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', [
            'name' => 'Mi Tienda',
            'primary_color' => '#ff0000',
            'currency' => 'usd',
            'whatsapp_message_template' => 'Hola, quiero {product}',
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Mi Tienda')
            ->assertJsonPath('data.theme.primary_color', '#FF0000')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.theme.accent_color', $this->store->accent_color)
            ->assertJsonPath('data.whatsapp.number', '+5491122334455');
    });

    it('can clear the WhatsApp number and the logo with null', function (): void {
        $this->store->update(['logo_path' => "stores/{$this->store->id}/logo/a.png"]);
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', ['whatsapp_number' => null, 'logo_path' => null])
            ->assertOk()
            ->assertJsonPath('data.whatsapp.number', null)
            ->assertJsonPath('data.logo_url', null);
    });

    it('sets the logo only from this store logo folder', function (): void {
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', ['logo_path' => "stores/{$this->otherStore->id}/logo/a.png"])
            ->assertUnprocessable()->assertJsonValidationErrors('logo_path');
        $this->patchJson('/api/v1/admin/store', ['logo_path' => "stores/{$this->store->id}/products/a.png"])
            ->assertUnprocessable()->assertJsonValidationErrors('logo_path');

        $this->patchJson('/api/v1/admin/store', ['logo_path' => "stores/{$this->store->id}/logo/a.png"])
            ->assertOk()
            ->assertJson(fn ($json) => $json->where('data.logo_url', fn (string $url): bool => str_ends_with($url, 'logo/a.png'))->etc());
    });

    it('rejects a logo that was never uploaded to R2', function (): void {
        app()->instance(UploadedObjectInspector::class, new class implements UploadedObjectInspector
        {
            public function inspect(string $path): ?UploadedObject
            {
                return null;
            }
        });
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', ['logo_path' => "stores/{$this->store->id}/logo/a.png"])
            ->assertUnprocessable()->assertJsonValidationErrors('logo_path');
    });

    it('validates colors, WhatsApp, currency and locale formats', function (): void {
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', [
            'primary_color' => 'rojo',
            'accent_color' => '#12345',
            'whatsapp_number' => '1122334455',
            'currency' => 'PESOS',
            'locale' => 'es_AR',
            'name' => '',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'primary_color', 'accent_color', 'whatsapp_number', 'currency', 'locale', 'name',
        ]);
    });

    it('ignores fields that are not configurable such as slug, status or store_id', function (): void {
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', ['slug' => 'hackeado', 'status' => 'suspended', 'name' => 'Ok'])->assertOk();

        $fresh = $this->store->fresh();
        expect($fresh->slug)->toBe($this->store->slug)->and($fresh->isActive())->toBeTrue()->and($fresh->name)->toBe('Ok');
    });

    it('never touches another store', function (): void {
        $before = $this->otherStore->name;
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', ['name' => 'Cambiado'])->assertOk();

        expect($this->otherStore->fresh()->name)->toBe($before);
    });

    it('requires store.manage: editors get 403', function (): void {
        actingAsStoreUser($this->store, 'editor');

        $this->getJson('/api/v1/admin/store')->assertForbidden();
        $this->patchJson('/api/v1/admin/store', ['name' => 'X'])->assertForbidden();
    });

    it('returns 401 when unauthenticated', function (): void {
        $this->patchJson('/api/v1/admin/store', ['name' => 'X'])->assertUnauthorized();
    });

    it('revalidates the store tag after updating', function (): void {
        config([
            'services.storefront.revalidate_url' => 'https://front.test/api/revalidate',
            'services.storefront.revalidate_secret' => 's3cret',
        ]);
        Http::fake(['front.test/*' => Http::response([])]);
        actingAsStoreUser($this->store);

        $this->patchJson('/api/v1/admin/store', ['primary_color' => '#000000'])->assertOk();

        Http::assertSent(fn (Request $request): bool => $request['tags'] === ["store:{$this->store->slug}"]);
    });
});
