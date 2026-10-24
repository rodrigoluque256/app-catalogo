<?php

declare(strict_types=1);

use App\Domain\Store\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->store = Store::factory()->create();
    $this->user = User::factory()->forStore($this->store)->create(['must_change_password' => true]);
    setPermissionsTeamId($this->store->id);
    $this->user->assignRole('editor');
});

function loginToken(User $user): string
{
    return test()->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => 'password'])->json('data.token');
}

describe('POST /api/v1/auth/password', function (): void {
    it('changes the password, clears the flag and keeps only the current session', function (): void {
        $current = loginToken($this->user);
        $other = loginToken($this->user);

        $this->postJson('/api/v1/auth/password', [
            'current_password' => 'password', 'password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026',
        ], ['Authorization' => "Bearer {$current}"])->assertNoContent();

        $fresh = $this->user->fresh();
        expect(Hash::check('ClaveNueva2026', $fresh->password))->toBeTrue()
            ->and($fresh->must_change_password)->toBeFalse()
            ->and($fresh->tokens()->count())->toBe(1);

        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$other}"])->assertUnauthorized();
    });

    it('rejects a wrong current password, a weak password, a mismatch and an unchanged password', function (): void {
        $headers = ['Authorization' => 'Bearer '.loginToken($this->user)];
        $post = fn (array $data) => $this->postJson('/api/v1/auth/password', $data, $headers);

        $post(['current_password' => 'incorrecta', 'password' => 'ClaveNueva2026', 'password_confirmation' => 'ClaveNueva2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $post(['current_password' => 'password', 'password' => 'corta1', 'password_confirmation' => 'corta1'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $post(['current_password' => 'password', 'password' => 'soloLetrasLargas', 'password_confirmation' => 'soloLetrasLargas'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $post(['current_password' => 'password', 'password' => 'ClaveNueva2026', 'password_confirmation' => 'otra'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $post(['current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');

        expect(Hash::check('password', $this->user->fresh()->password))->toBeTrue();
    });

    it('requires authentication', function (): void {
        $this->postJson('/api/v1/auth/password', [])->assertUnauthorized();
    });
});
