<?php

declare(strict_types=1);

use App\Domain\Product\Enums\ProductStatus;
use App\Domain\Product\Models\Product;
use App\Domain\Product\Models\ProductVariant;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Models\Store;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('r2');

    $this->store = Store::factory()->create();
    $this->otherStore = Store::factory()->create();
    $this->headers = ['X-Store-Slug' => $this->store->slug];
});

function makeProduct(Store $store, array $attributes = []): Product
{
    return app(TenantContext::class)->runAs($store, fn (): Product => Product::factory()->create(
        array_merge(['store_id' => $store->id, 'status' => ProductStatus::Active], $attributes),
    ));
}

describe('tenant resolution', function (): void {
    it('returns 404 without the X-Store-Slug header', function (): void {
        $this->getJson('/api/v1/storefront/products')->assertNotFound();
    });

    it('returns 404 for an unknown or suspended store', function (): void {
        $suspended = Store::factory()->suspended()->create();

        $this->getJson('/api/v1/storefront/products', ['X-Store-Slug' => 'no-existe'])->assertNotFound();
        $this->getJson('/api/v1/storefront/products', ['X-Store-Slug' => $suspended->slug])->assertNotFound();
    });
});

describe('GET /api/v1/storefront/store', function (): void {
    it('returns the public store config', function (): void {
        $this->getJson('/api/v1/storefront/store', $this->headers)
            ->assertOk()
            ->assertJsonPath('data.slug', $this->store->slug)
            ->assertJsonPath('data.whatsapp.number', $this->store->whatsapp_number)
            ->assertJsonStructure(['data' => ['name', 'theme' => ['primary_color', 'accent_color'], 'currency']]);
    });
});

describe('GET /api/v1/storefront/products', function (): void {
    it('lists only active products of the store', function (): void {
        $visible = makeProduct($this->store);
        makeProduct($this->store, ['status' => ProductStatus::Draft]);
        makeProduct($this->store, ['status' => ProductStatus::Archived]);
        makeProduct($this->otherStore);

        $this->getJson('/api/v1/storefront/products', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    });

    it('ignores a status filter sent by the client', function (): void {
        makeProduct($this->store, ['status' => ProductStatus::Draft]);

        $this->getJson('/api/v1/storefront/products?status=draft', $this->headers)
            ->assertOk()
            ->assertJsonCount(0, 'data');
    });

    it('does not expose internal fields', function (): void {
        makeProduct($this->store, ['sku' => 'SECRETO-1']);

        $this->getJson('/api/v1/storefront/products', $this->headers)
            ->assertOk()
            ->assertJsonMissingPath('data.0.sku')
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.metadata');
    });
});

describe('GET /api/v1/storefront/products/{slug}', function (): void {
    it('shows an active product with only its active variants', function (): void {
        $product = makeProduct($this->store);
        ProductVariant::factory()->create(['store_id' => $this->store->id, 'product_id' => $product->id, 'is_active' => true]);
        ProductVariant::factory()->create(['store_id' => $this->store->id, 'product_id' => $product->id, 'is_active' => false]);

        $this->getJson("/api/v1/storefront/products/{$product->slug}", $this->headers)
            ->assertOk()
            ->assertJsonPath('data.slug', $product->slug)
            ->assertJsonCount(1, 'data.variants')
            ->assertJsonMissingPath('data.variants.0.stock')
            ->assertJsonMissingPath('data.variants.0.sku');
    });

    it('returns 404 for draft products and for products of another store', function (): void {
        $draft = makeProduct($this->store, ['status' => ProductStatus::Draft]);
        $foreign = makeProduct($this->otherStore);

        $this->getJson("/api/v1/storefront/products/{$draft->slug}", $this->headers)->assertNotFound();
        $this->getJson("/api/v1/storefront/products/{$foreign->slug}", $this->headers)->assertNotFound();
    });
});

describe('GET /api/v1/storefront/categories', function (): void {
    it('lists only active categories of the store', function (): void {
        $visible = App\Domain\Product\Models\Category::factory()->forStore($this->store)->create();
        App\Domain\Product\Models\Category::factory()->forStore($this->store)->create(['is_active' => false]);
        App\Domain\Product\Models\Category::factory()->forStore($this->otherStore)->create();

        $this->getJson('/api/v1/storefront/categories', $this->headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id);
    });
});
