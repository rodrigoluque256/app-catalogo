<?php

declare(strict_types=1);

use App\Domain\Store\Contracts\SignedUpload;
use App\Domain\Store\Contracts\UploadUrlSigner;
use App\Domain\Store\Models\Store;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->store = Store::factory()->create();

    // R2 real no está disponible en tests: el firmador registra lo que se le pidió.
    $this->signer = new class implements UploadUrlSigner
    {
        /** @var list<array{path: string, ttl: int}> */
        public array $calls = [];

        public function sign(string $path, int $ttlSeconds): SignedUpload
        {
            $this->calls[] = ['path' => $path, 'ttl' => $ttlSeconds];

            return new SignedUpload("https://r2.test/{$path}?sig=x", [], "https://cdn.test/{$path}", now()->addSeconds($ttlSeconds));
        }
    };
    $this->app->instance(UploadUrlSigner::class, $this->signer);
});

function presign(array $overrides = []): array
{
    return array_merge(['purpose' => 'product_image', 'content_type' => 'image/webp', 'size' => 200_000], $overrides);
}

describe('POST /api/v1/admin/uploads/presign', function (): void {
    it('returns a signed PUT url under the store prefix with a server-generated key', function (): void {
        actingAsStoreUser($this->store);

        $response = $this->postJson('/api/v1/admin/uploads/presign', presign())->assertOk();

        $path = $response->json('data.path');
        expect($path)->toStartWith("stores/{$this->store->id}/products/")->toEndWith('.webp');
        $response->assertJsonPath('data.method', 'PUT')
            ->assertJsonPath('data.upload_url', "https://r2.test/{$path}?sig=x")
            ->assertJsonPath('data.public_url', "https://cdn.test/{$path}")
            ->assertJsonPath('data.expires_at', fn (string $at): bool => $at !== '');
        expect($this->signer->calls[0])->toMatchArray(['path' => $path, 'ttl' => 600]);
    });

    it('uses the logo folder for store logos', function (): void {
        actingAsStoreUser($this->store, 'owner');

        $this->postJson('/api/v1/admin/uploads/presign', presign(['purpose' => 'store_logo', 'content_type' => 'image/png']))
            ->assertOk()
            ->assertJsonPath('data.path', fn (string $path): bool => str_starts_with($path, "stores/{$this->store->id}/logo/") && str_ends_with($path, '.png'));
    });

    it('rejects unsupported types, oversized files and unknown purposes', function (): void {
        actingAsStoreUser($this->store);

        $this->postJson('/api/v1/admin/uploads/presign', presign(['content_type' => 'image/svg+xml']))
            ->assertUnprocessable()->assertJsonValidationErrors('content_type');
        $this->postJson('/api/v1/admin/uploads/presign', presign(['size' => 5_242_881]))
            ->assertUnprocessable()->assertJsonValidationErrors('size');
        $this->postJson('/api/v1/admin/uploads/presign', presign(['purpose' => 'otra-cosa']))
            ->assertUnprocessable()->assertJsonValidationErrors('purpose');
        expect($this->signer->calls)->toBeEmpty();
    });

    it('requires the permission of the purpose: editors cannot upload logos', function (): void {
        actingAsStoreUser($this->store, 'editor');

        $this->postJson('/api/v1/admin/uploads/presign', presign())->assertOk();
        $this->postJson('/api/v1/admin/uploads/presign', presign(['purpose' => 'store_logo']))->assertForbidden();
    });

    it('returns 401 when unauthenticated and 403 without a role', function (): void {
        $this->postJson('/api/v1/admin/uploads/presign', presign())->assertUnauthorized();

        actingAsStoreUser($this->store, role: null);
        $this->postJson('/api/v1/admin/uploads/presign', presign())->assertForbidden();
    });
});
