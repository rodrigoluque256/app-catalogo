<?php

declare(strict_types=1);

use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Store\Contracts\UploadedObject;
use App\Domain\Store\Contracts\UploadedObjectInspector;
use App\Domain\Store\Models\Store;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('r2');

    $this->store = Store::factory()->create();
    $this->otherStore = Store::factory()->create();
});

function productPayload(Store $store, array $overrides = []): array
{
    return array_merge([
        'name' => 'Remera Oversize',
        'price_amount' => 1_850_000,
        'status' => 'active',
        'sku' => 'REM-001',
        'variants' => [
            ['options' => ['talle' => 'M', 'color' => 'Negro'], 'sku' => 'REM-001-M', 'stock' => 5],
            ['options' => ['talle' => 'L', 'color' => 'Negro'], 'sku' => 'REM-001-L'],
        ],
        'images' => [
            ['path' => "stores/{$store->id}/products/remera.webp", 'alt_text' => 'Remera negra'],
        ],
    ], $overrides);
}

describe('POST /api/v1/admin/products', function (): void {
    it('creates a product with variants and images for the current store', function (): void {
        actingAsStoreUser($this->store);

        $response = $this->postJson('/api/v1/admin/products', productPayload($this->store));

        $response->assertCreated()
            ->assertJsonPath('data.slug', 'remera-oversize')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonCount(2, 'data.variants')
            ->assertJsonPath('data.images.0.is_primary', true)
            ->assertJsonStructure(['data' => [
                'id', 'name', 'slug', 'price_amount', 'published_at',
                'variants' => [['id', 'sku', 'options', 'stock', 'in_stock']],
                'images' => [['id', 'url', 'alt_text', 'is_primary']],
            ]]);

        $this->assertDatabaseHas('products', [
            'id' => $response->json('data.id'),
            'store_id' => $this->store->id,
        ]);
        expect(ProductVariant::withoutGlobalScopes()->where('store_id', $this->store->id)->count())->toBe(2);
    });

    it('returns 422 when required fields are missing', function (): void {
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/products', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'price_amount']);
    });

    it('rejects a category that belongs to another store', function (): void {
        actingAsStoreUser($this->store);
        $foreignCategory = Category::factory()->forStore($this->otherStore)->create();

        $this->postJson('/api/v1/admin/products', productPayload($this->store, ['category_id' => $foreignCategory->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['category_id']);
    });

    it('allows the same SKU in different stores but not twice in the same one', function (): void {
        Product::factory()->forStore($this->otherStore)->create(['sku' => 'REM-001']);
        Product::factory()->forStore($this->store)->create(['sku' => 'TAKEN']);
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/products', productPayload($this->store))->assertCreated();

        $this->postJson('/api/v1/admin/products', productPayload($this->store, ['sku' => 'TAKEN', 'variants' => []]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['sku']);
    });
});

describe('image paths', function (): void {
    it('rejects a key that was never uploaded, or an oversized or non-image object', function (): void {
        actingAsStoreUser($this->store);
        $path = "stores/{$this->store->id}/products/x.webp";
        $images = fn () => productPayload($this->store, ['images' => [['path' => $path]]]);

        foreach ([null, new UploadedObject(5_242_881, 'image/webp'), new UploadedObject(1000, 'text/html')] as $object) {
            app()->instance(UploadedObjectInspector::class, new class($object) implements UploadedObjectInspector
            {
                public function __construct(private readonly ?UploadedObject $object) {}

                public function inspect(string $path): ?UploadedObject
                {
                    return $this->object;
                }
            });

            $this->postJson('/api/v1/admin/products', $images())
                ->assertUnprocessable()
                ->assertJsonValidationErrors('images.0.path');
        }
    });

    it('rejects an image key that belongs to another store', function (): void {
        actingAsStoreUser($this->store);

        $payload = productPayload($this->store, ['images' => [
            ['path' => "stores/{$this->otherStore->id}/products/robada.webp"],
        ]]);

        $this->postJson('/api/v1/admin/products', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('images.0.path');
    });
});

describe('tenant isolation', function (): void {
    it('only lists products of the authenticated user store', function (): void {
        Product::factory()->count(2)->forStore($this->store)->create();
        Product::factory()->count(3)->forStore($this->otherStore)->create();
        actingAsStoreUser($this->store);

        $this->getJson('/api/v1/admin/products')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    });

    it('returns 404 for show, update and delete of another store product', function (): void {
        $foreign = Product::factory()->forStore($this->otherStore)->create();
        actingAsStoreUser($this->store);

        $this->getJson("/api/v1/admin/products/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/admin/products/{$foreign->id}", productPayload($this->store))->assertNotFound();
        $this->deleteJson("/api/v1/admin/products/{$foreign->id}")->assertNotFound();

        expect(Product::withoutGlobalScopes()->find($foreign->id))->not->toBeNull();
    });

    it('returns 404 for an id that is not a valid UUID', function (): void {
        actingAsStoreUser($this->store);

        $this->getJson('/api/v1/admin/products/not-a-uuid')->assertNotFound();
    });
});

describe('authorization', function (): void {
    it('returns 401 when unauthenticated', function (): void {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();
    });

    it('returns 403 when the user has no role in the store', function (): void {
        actingAsStoreUser($this->store, role: null);

        $this->getJson('/api/v1/admin/products')->assertForbidden();
    });

    it('returns 403 when the store is suspended', function (): void {
        actingAsStoreUser(Store::factory()->suspended()->create());

        $this->getJson('/api/v1/admin/products')->assertForbidden();
    });
});
