<?php

declare(strict_types=1);

use App\Domain\Store\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    RateLimiter::clear('login');

    $this->store = Store::factory()->create();
    $this->user = User::factory()->forStore($this->store)->create(['email' => 'dueno@tienda.test']);
});

describe('POST /api/v1/auth/login', function (): void {
    it('returns a bearer token that works against the admin API', function (): void {
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'Dueno@Tienda.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 43_200)
            ->json('data.token');

        expect($token)->toBeString()->not->toBeEmpty();
        expect($this->user->tokens()->first()->expires_at)->not->toBeNull();
    });

    it('gives the same generic 422 for wrong password, unknown email, no store and suspended store', function (): void {
        $noStore = User::factory()->create(['email' => 'super@plataforma.test']);
        $suspended = Store::factory()->suspended()->create();
        User::factory()->forStore($suspended)->create(['email' => 'susp@tienda.test']);

        $cases = [
            ['dueno@tienda.test', 'incorrecta'],
            ['nadie@tienda.test', 'password'],
            [$noStore->email, 'password'],
            ['susp@tienda.test', 'password'],
        ];

        foreach ($cases as [$email, $password]) {
            $this->postJson('/api/v1/auth/login', compact('email', 'password'))
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', 'Email o contraseña incorrectos.');
        }
    });

    it('validates the payload', function (): void {
        $this->postJson('/api/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
    });

    it('throttles after 5 attempts for the same email', function (): void {
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/auth/login', ['email' => 'dueno@tienda.test', 'password' => 'mal'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/auth/login', ['email' => 'dueno@tienda.test', 'password' => 'password'])->assertStatus(429);
    });
});

describe('GET /api/v1/auth/me', function (): void {
    it('returns the user, the store and the permissions of the role', function (): void {
        actingAsStoreUser($this->store, 'editor');

        $this->getJson('/api/v1/auth/me')
            ->assertOk()
            ->assertJsonPath('data.store.slug', $this->store->slug)
            ->assertJsonPath('data.roles', ['editor'])
            ->assertJsonPath('data.permissions', ['products.manage']);
    });

    it('lists store.manage for owners and requires authentication', function (): void {
        $this->getJson('/api/v1/auth/me')->assertUnauthorized();

        actingAsStoreUser($this->store, 'owner');
        $this->getJson('/api/v1/auth/me')->assertOk()->assertJsonFragment(['store.manage']);
    });
});

describe('POST /api/v1/auth/logout', function (): void {
    it('revokes the current token', function (): void {
        $token = $this->postJson('/api/v1/auth/login', ['email' => 'dueno@tienda.test', 'password' => 'password'])->json('data.token');

        $this->postJson('/api/v1/auth/logout', [], ['Authorization' => "Bearer {$token}"])->assertNoContent();

        expect($this->user->tokens()->count())->toBe(0);
    });
});
