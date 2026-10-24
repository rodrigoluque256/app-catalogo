<?php

declare(strict_types=1);

use App\Domain\Product\Models\Category;
use App\Domain\Product\Models\Product;
use App\Domain\Shared\Tenancy\TenantContext;
use App\Domain\Store\Models\Store;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->store = Store::factory()->create();
    $this->otherStore = Store::factory()->create();
});

function makeCategory(Store $store, array $attributes = []): Category
{
    return Category::factory()->forStore($store)->create($attributes);
}

describe('POST /api/v1/admin/categories', function (): void {
    it('creates a category with a generated slug, suffixed when taken', function (): void {
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Remeras'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'remeras');

        $this->postJson('/api/v1/admin/categories', ['name' => 'Remeras'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'remeras-2');
    });

    it('allows the same slug in another store', function (): void {
        makeCategory($this->otherStore, ['name' => 'Remeras', 'slug' => 'remeras']);
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Remeras'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'remeras');
    });

    it('creates a subcategory under a parent of the same store', function (): void {
        $parent = makeCategory($this->store);
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/categories', ['name' => 'Manga larga', 'parent_id' => $parent->id])
            ->assertCreated()
            ->assertJsonPath('data.parent_id', $parent->id);
    });

    it('rejects a parent from another store', function (): void {
        $foreign = makeCategory($this->otherStore);
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/categories', ['name' => 'X', 'parent_id' => $foreign->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('parent_id');
    });

    it('returns 422 when the name is missing', function (): void {
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/categories', [])->assertUnprocessable()->assertJsonValidationErrors('name');
    });
});

describe('PUT /api/v1/admin/categories/{id}', function (): void {
    it('updates a category and keeps its slug when none is sent', function (): void {
        $category = makeCategory($this->store, ['name' => 'Viejo', 'slug' => 'viejo']);
        actingAsStoreUser($this->store);

        $this->putJson("/api/v1/admin/categories/{$category->id}", ['name' => 'Nuevo', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nuevo')
            ->assertJsonPath('data.slug', 'viejo')
            ->assertJsonPath('data.is_active', false);
    });

    it('rejects making a category its own parent or a descendant of itself', function (): void {
        $root = makeCategory($this->store);
        $child = makeCategory($this->store, ['parent_id' => $root->id]);
        actingAsStoreUser($this->store);

        $this->putJson("/api/v1/admin/categories/{$root->id}", ['name' => 'R', 'parent_id' => $root->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');

        $this->putJson("/api/v1/admin/categories/{$root->id}", ['name' => 'R', 'parent_id' => $child->id])
            ->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    });
});

describe('DELETE /api/v1/admin/categories/{id}', function (): void {
    it('deletes the category and detaches its products and children', function (): void {
        $category = makeCategory($this->store);
        $child = makeCategory($this->store, ['parent_id' => $category->id]);
        $product = app(TenantContext::class)->runAs($this->store, fn (): Product => Product::factory()->create([
            'store_id' => $this->store->id,
            'category_id' => $category->id,
        ]));
        actingAsStoreUser($this->store);

        $this->deleteJson("/api/v1/admin/categories/{$category->id}")->assertNoContent();

        expect(Category::withoutGlobalScopes()->find($category->id)?->trashed())->toBeTrue()
            ->and(Category::withoutGlobalScopes()->find($child->id)?->parent_id)->toBeNull()
            ->and(Product::withoutGlobalScopes()->find($product->id)?->category_id)->toBeNull();
    });
});

describe('tenant isolation and authorization', function (): void {
    it('lists only the categories of the authenticated store', function (): void {
        $mine = makeCategory($this->store);
        makeCategory($this->otherStore);
        actingAsStoreUser($this->store);

        $this->getJson('/api/v1/admin/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    });

    it('returns 404 for show, update and delete of another store category or a non-UUID', function (): void {
        $foreign = makeCategory($this->otherStore);
        actingAsStoreUser($this->store);

        $this->getJson("/api/v1/admin/categories/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/admin/categories/{$foreign->id}", ['name' => 'X'])->assertNotFound();
        $this->deleteJson("/api/v1/admin/categories/{$foreign->id}")->assertNotFound();
        $this->getJson('/api/v1/admin/categories/no-es-uuid')->assertNotFound();
        expect(Category::withoutGlobalScopes()->find($foreign->id)?->trashed())->toBeFalse();
    });

    it('returns 401 when unauthenticated and 403 without a role', function (): void {
        $this->getJson('/api/v1/admin/categories')->assertUnauthorized();

        actingAsStoreUser($this->store, role: null);
        $this->getJson('/api/v1/admin/categories')->assertForbidden();
    });
});
