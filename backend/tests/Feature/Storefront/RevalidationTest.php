<?php

declare(strict_types=1);

use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Models\Store;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    config([
        'services.storefront.revalidate_url' => 'https://front.test/api/revalidate',
        'services.storefront.revalidate_secret' => 's3cret',
    ]);
    Http::fake(['front.test/*' => Http::response(['revalidated' => true])]);

    $this->store = Store::factory()->create();
});

function sentTags(): array
{
    return Http::recorded()->map(fn (array $pair): array => $pair[0]->data()['tags'])->all();
}

it('revalidates the list and the detail page when a product is created', function (): void {
    actingAsStoreUser($this->store);

    $slug = $this->postJson('/api/v1/admin/products', ['name' => 'Remera', 'price_amount' => 1000, 'status' => 'active'])
        ->assertCreated()
        ->json('data.slug');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://front.test/api/revalidate'
        && $request->hasHeader('x-revalidate-secret', 's3cret')
        && $request['tags'] === ["store:{$this->store->slug}:products", "store:{$this->store->slug}:product:{$slug}"]);
});

it('revalidates both the old and the new slug when a product slug changes', function (): void {
    $product = app(TenantContext::class)->runAs($this->store, fn (): Product => Product::factory()->create([
        'store_id' => $this->store->id, 'slug' => 'viejo',
    ]));
    actingAsStoreUser($this->store);
    $this->putJson("/api/v1/admin/products/{$product->id}", [
        'name' => $product->name, 'price_amount' => 1000, 'status' => 'active', 'slug' => 'nuevo',
    ])->assertOk();

    expect(sentTags()[0])->toContain(
        "store:{$this->store->slug}:product:viejo",
        "store:{$this->store->slug}:product:nuevo",
        "store:{$this->store->slug}:products",
    );
});

it('revalidates the product when it is deleted', function (): void {
    $product = app(TenantContext::class)->runAs($this->store, fn (): Product => Product::factory()->create([
        'store_id' => $this->store->id, 'slug' => 'borrame',
    ]));
    actingAsStoreUser($this->store);

    $this->deleteJson("/api/v1/admin/products/{$product->id}")->assertNoContent();

    expect(sentTags()[0])->toContain("store:{$this->store->slug}:product:borrame");
});

it('revalidates categories and products when a category changes', function (): void {
    actingAsStoreUser($this->store);

    $this->postJson('/api/v1/admin/categories', ['name' => 'Remeras'])->assertCreated();

    expect(sentTags()[0])->toBe(["store:{$this->store->slug}:categories", "store:{$this->store->slug}:products"]);
});

it('does not call the frontend when the revalidate url is not configured', function (): void {
    config(['services.storefront.revalidate_url' => null]);
    actingAsStoreUser($this->store);

    $this->postJson('/api/v1/admin/categories', ['name' => 'Remeras'])->assertCreated();

    Http::assertNothingSent();
});

it('does not revalidate for a suspended store', function (): void {
    $suspended = Store::factory()->suspended()->create();

    event(new App\Domain\Product\Events\CategoriesChanged($suspended->id));

    Http::assertNothingSent();
});

it('fails the job when the frontend answers with an error so the queue retries it', function (): void {
    // Los stubs de Http::fake se acumulan y gana el primero que coincide: se usa otro host para el error.
    config(['services.storefront.revalidate_url' => 'https://broken.test/api/revalidate']);
    Http::fake(['broken.test/*' => Http::response([], 500)]);

    expect(fn () => event(new App\Domain\Product\Events\CategoriesChanged($this->store->id)))
        ->toThrow(Illuminate\Http\Client\RequestException::class);
});
