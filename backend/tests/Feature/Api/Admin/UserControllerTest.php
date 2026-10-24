<?php

declare(strict_types=1);

use App\Domain\Store\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->store = Store::factory()->create();
    $this->otherStore = Store::factory()->create();
});

function userInStore(Store $store, string $role, array $attributes = []): User
{
    $user = User::factory()->forStore($store)->create($attributes);
    setPermissionsTeamId($store->id);
    $user->assignRole($role);

    return $user->fresh();
}

describe('GET /api/v1/admin/users', function (): void {
    it('lists only the users of the store with their role', function (): void {
        $owner = actingAsStoreUser($this->store, 'owner');
        $editor = userInStore($this->store, 'editor');
        userInStore($this->otherStore, 'owner');
        User::factory()->create(); // super admin, sin tienda

        setPermissionsTeamId($this->store->id);
        $response = $this->getJson('/api/v1/admin/users')->assertOk()->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('id');
        expect($rows[$owner->id]['role'])->toBe('owner')
            ->and($rows[$owner->id]['is_current_user'])->toBeTrue()
            ->and($rows[$editor->id]['role'])->toBe('editor')
            ->and($rows[$editor->id]['is_current_user'])->toBeFalse();
        $response->assertJsonMissingPath('data.0.password');
    });

    it('is restricted to owners', function (): void {
        $this->getJson('/api/v1/admin/users')->assertUnauthorized();

        actingAsStoreUser($this->store, 'editor');
        $this->getJson('/api/v1/admin/users')->assertForbidden();
    });
});

describe('POST /api/v1/admin/users', function (): void {
    it('creates a user in the store with a one-time temporary password that must be changed', function (): void {
        actingAsStoreUser($this->store, 'owner');

        $response = $this->postJson('/api/v1/admin/users', ['name' => 'Ana', 'email' => 'ANA@Tienda.test', 'role' => 'editor'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'ana@tienda.test')
            ->assertJsonPath('data.role', 'editor')
            ->assertJsonPath('data.must_change_password', true);

        $temporary = $response->json('data.temporary_password');
        $created = User::query()->where('email', 'ana@tienda.test')->firstOrFail();

        expect($temporary)->toBeString()->toHaveLength(14)
            ->and($created->store_id)->toBe($this->store->id)
            ->and(Hash::check($temporary, $created->password))->toBeTrue()
            ->and($created->password)->not->toBe($temporary);
    });

    it('rejects a duplicate email (even from another store) and invalid roles', function (): void {
        userInStore($this->otherStore, 'owner', ['email' => 'dup@tienda.test']);
        actingAsStoreUser($this->store, 'owner');

        $this->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'dup@tienda.test', 'role' => 'editor'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'x@tienda.test', 'role' => 'superadmin'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->postJson('/api/v1/admin/users', [])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'role']);
    });

    it('forbids editors', function (): void {
        actingAsStoreUser($this->store, 'editor');

        $this->postJson('/api/v1/admin/users', ['name' => 'X', 'email' => 'x@tienda.test', 'role' => 'owner'])->assertForbidden();
        expect(User::query()->where('email', 'x@tienda.test')->exists())->toBeFalse();
    });
});

describe('PUT /api/v1/admin/users/{id}', function (): void {
    it('changes name and role of another user', function (): void {
        actingAsStoreUser($this->store, 'owner');
        $editor = userInStore($this->store, 'editor');

        $this->putJson("/api/v1/admin/users/{$editor->id}", ['name' => 'Nuevo Nombre', 'role' => 'owner'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Nuevo Nombre')
            ->assertJsonPath('data.role', 'owner');

        setPermissionsTeamId($this->store->id);
        expect($editor->fresh()->hasRole('owner'))->toBeTrue()->and($editor->fresh()->hasRole('editor'))->toBeFalse();
    });

    it('does not let you change your own role but lets you rename yourself', function (): void {
        $me = actingAsStoreUser($this->store, 'owner');
        userInStore($this->store, 'owner'); // hay otro dueño: la restricción es por ser uno mismo

        $this->putJson("/api/v1/admin/users/{$me->id}", ['name' => $me->name, 'role' => 'editor'])
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        $this->putJson("/api/v1/admin/users/{$me->id}", ['name' => 'Mi Nuevo Nombre', 'role' => 'owner'])->assertOk();
    });
});

describe('DELETE /api/v1/admin/users/{id}', function (): void {
    it('deletes a user of the store and revokes their tokens', function (): void {
        actingAsStoreUser($this->store, 'owner');
        $editor = userInStore($this->store, 'editor');
        $editor->createToken('t');

        $this->deleteJson("/api/v1/admin/users/{$editor->id}")->assertNoContent();

        expect(User::query()->find($editor->id))->toBeNull()
            ->and(DB::table('personal_access_tokens')->where('tokenable_id', $editor->id)->count())->toBe(0);
    });

    it('refuses to delete yourself', function (): void {
        $me = actingAsStoreUser($this->store, 'owner');
        userInStore($this->store, 'owner');

        $this->deleteJson("/api/v1/admin/users/{$me->id}")->assertUnprocessable()->assertJsonValidationErrors('user');
        expect(User::query()->find($me->id))->not->toBeNull();
    });
});

describe('tenant isolation', function (): void {
    it('returns 404 for update, delete and reset of a user from another store, or a non-numeric id', function (): void {
        actingAsStoreUser($this->store, 'owner');
        $foreign = userInStore($this->otherStore, 'editor');

        $this->putJson("/api/v1/admin/users/{$foreign->id}", ['name' => 'X', 'role' => 'owner'])->assertNotFound();
        $this->deleteJson("/api/v1/admin/users/{$foreign->id}")->assertNotFound();
        $this->postJson("/api/v1/admin/users/{$foreign->id}/reset-password")->assertNotFound();
        $this->deleteJson('/api/v1/admin/users/abc')->assertNotFound();

        $fresh = $foreign->fresh();
        expect($fresh)->not->toBeNull()->and($fresh->name)->toBe($foreign->name);
        setPermissionsTeamId($this->otherStore->id);
        expect($fresh->hasRole('editor'))->toBeTrue();
    });
});

describe('POST /api/v1/admin/users/{id}/reset-password', function (): void {
    it('sets a new temporary password, forces a change and closes their sessions', function (): void {
        actingAsStoreUser($this->store, 'owner');
        $editor = userInStore($this->store, 'editor');
        $editor->createToken('t');

        $temporary = $this->postJson("/api/v1/admin/users/{$editor->id}/reset-password")
            ->assertOk()->json('data.temporary_password');

        $fresh = $editor->fresh();
        expect(Hash::check($temporary, $fresh->password))->toBeTrue()
            ->and($fresh->must_change_password)->toBeTrue()
            ->and($fresh->tokens()->count())->toBe(0);
    });
});

describe('forced password change', function (): void {
    it('blocks the admin API with a coded 403 until the password is changed', function (): void {
        $user = userInStore($this->store, 'owner', ['must_change_password' => true]);
        $token = $user->createToken('t')->plainTextToken;
        $headers = ['Authorization' => "Bearer {$token}"];

        $this->getJson('/api/v1/admin/products', $headers)
            ->assertForbidden()->assertJsonPath('code', 'password_change_required');
        $this->getJson('/api/v1/auth/me', $headers)->assertOk()->assertJsonPath('data.user.must_change_password', true);

        $this->postJson('/api/v1/auth/password', [
            'current_password' => 'password', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123',
        ], $headers)->assertNoContent();

        $this->getJson('/api/v1/admin/products', $headers)->assertOk();
    });
});

describe('last owner protection (defense in depth)', function (): void {
    // Por la API es inalcanzable (quien administra es siempre otro dueño), pero cubre carreras entre dos dueños.
    it('refuses to demote or delete the only owner', function (): void {
        $soleOwner = userInStore($this->store, 'owner');
        app(App\Domain\Shared\Tenancy\TenantContext::class)->set($this->store);
        $actorId = 999_999;

        expect(fn () => app(App\Application\User\Actions\UpdateUserAction::class)->execute($actorId, (string) $soleOwner->id, 'X', 'editor'))
            ->toThrow(App\Domain\Auth\Exceptions\UserManagementException::class, 'al menos un dueño');
        expect(fn () => app(App\Application\User\Actions\DeleteUserAction::class)->execute($actorId, (string) $soleOwner->id))
            ->toThrow(App\Domain\Auth\Exceptions\UserManagementException::class, 'al menos un dueño');

        expect(User::query()->find($soleOwner->id))->not->toBeNull();
    });

    it('allows it when another owner remains', function (): void {
        $first = userInStore($this->store, 'owner');
        userInStore($this->store, 'owner');
        app(App\Domain\Shared\Tenancy\TenantContext::class)->set($this->store);

        app(App\Application\User\Actions\DeleteUserAction::class)->execute(999_999, (string) $first->id);

        expect(User::query()->find($first->id))->toBeNull();
    });
});
