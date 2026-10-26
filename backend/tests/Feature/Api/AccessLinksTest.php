<?php

declare(strict_types=1);

use App\Application\Auth\Notifications\ResetPasswordLink;
use App\Application\Auth\Notifications\UserInvitation;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    $this->seed(RolesAndPermissionsSeeder::class);
    Notification::fake();
    RateLimiter::clear('login');

    $this->store = Store::factory()->create(['name' => 'Almacén Luna']);
    $this->user = User::factory()->forStore($this->store)->create(['email' => 'ana@luna.test', 'name' => 'Ana']);
    setPermissionsTeamId($this->store->id);
    $this->user->assignRole('owner');
    setPermissionsTeamId(null);
});

function newPassword(array $extra = []): array
{
    return ['password' => 'ClaveNueva123', 'password_confirmation' => 'ClaveNueva123', ...$extra];
}

describe('POST /api/v1/auth/forgot-password', function (): void {
    it('answers the same for a real, an unknown and a suspended-store email, and only mails the real one', function (): void {
        $suspended = Store::factory()->suspended()->create();
        User::factory()->forStore($suspended)->create(['email' => 'susp@tienda.test']);

        foreach (['ANA@luna.test', 'nadie@luna.test', 'susp@tienda.test'] as $email) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => $email])
                ->assertAccepted()
                ->assertJsonPath('message', 'Si el email está registrado, te enviamos un link para restablecer la contraseña.');
        }

        Notification::assertSentTimes(ResetPasswordLink::class, 1);
        Notification::assertSentTo($this->user, ResetPasswordLink::class);
    });

    it('sends at most one email per minute to the same person', function (): void {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@luna.test'])->assertAccepted();
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@luna.test'])->assertAccepted();

        Notification::assertSentTimes(ResetPasswordLink::class, 1);
    });

    it('limits the requests per email and IP', function (): void {
        foreach (range(1, 3) as $_) {
            $this->postJson('/api/v1/auth/forgot-password', ['email' => 'otra@luna.test'])->assertAccepted();
        }

        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'otra@luna.test'])->assertTooManyRequests();
    });
});

describe('POST /api/v1/auth/reset-password', function (): void {
    it('sets the new password once, closes every session and lets them log in', function (): void {
        $this->user->createToken('vieja');
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@luna.test']);
        $token = sentLinkToken($this->user, ResetPasswordLink::class);

        $this->postJson('/api/v1/auth/reset-password', newPassword(['email' => 'ana@luna.test', 'token' => $token]))->assertNoContent();

        expect(Hash::check('ClaveNueva123', $this->user->fresh()->password))->toBeTrue()
            ->and($this->user->tokens()->count())->toBe(0);
        $this->postJson('/api/v1/auth/login', ['email' => 'ana@luna.test', 'password' => 'ClaveNueva123'])->assertOk();

        // El link sirve una sola vez.
        $this->postJson('/api/v1/auth/reset-password', newPassword(['email' => 'ana@luna.test', 'token' => $token]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'El link para restablecer la contraseña no es válido o venció. Pedí uno nuevo.');
    });

    it('expires after 60 minutes and validates the new password', function (): void {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'ana@luna.test']);
        $token = sentLinkToken($this->user, ResetPasswordLink::class);

        $this->postJson('/api/v1/auth/reset-password', ['email' => 'ana@luna.test', 'token' => $token, 'password' => 'corta', 'password_confirmation' => 'corta'])
            ->assertJsonPath('errors.password', ['La contraseña debe tener al menos 10 caracteres.', 'La contraseña debe tener al menos un número.']);
        $this->postJson('/api/v1/auth/reset-password', ['email' => 'ana@luna.test', 'token' => $token, 'password' => '1234567890', 'password_confirmation' => '1234567890'])
            ->assertJsonPath('errors.password', ['La contraseña debe tener al menos una letra.']);

        $this->travel(61)->minutes();
        $this->postJson('/api/v1/auth/reset-password', newPassword(['email' => 'ana@luna.test', 'token' => $token]))->assertUnprocessable();
    });
});

describe('invitations', function (): void {
    beforeEach(function (): void {
        actingAsStoreUser($this->store);
        $this->postJson('/api/v1/admin/users', ['name' => 'Eva', 'email' => 'eva@luna.test', 'role' => 'editor'])->assertCreated();
        app('auth')->forgetGuards();

        $this->invited = User::query()->where('email', 'eva@luna.test')->sole();
        $this->token = sentLinkToken($this->invited, UserInvitation::class);
    });

    it('cannot log in before accepting', function (): void {
        $this->postJson('/api/v1/auth/login', ['email' => 'eva@luna.test', 'password' => 'password'])->assertUnprocessable();
    });

    it('shows the store of a valid link without consuming it', function (): void {
        $this->getJson('/api/v1/auth/invitation?'.http_build_query(['email' => 'eva@luna.test', 'token' => $this->token]))
            ->assertOk()
            ->assertJsonPath('data.store', 'Almacén Luna');
        $this->getJson('/api/v1/auth/invitation?'.http_build_query(['email' => 'eva@luna.test', 'token' => 'otro']))
            ->assertUnprocessable();
    });

    it('accepts once: sets the password, verifies the email and returns a session token', function (): void {
        $session = $this->postJson('/api/v1/auth/invitation/accept', newPassword(['email' => 'eva@luna.test', 'token' => $this->token]))
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->json('data.token');

        withBearer($session, 'GET', '/api/v1/auth/me')->assertOk()->assertJsonPath('data.user.email', 'eva@luna.test');
        $fresh = $this->invited->fresh();
        expect($fresh->invitation_accepted_at)->not->toBeNull()
            ->and($fresh->email_verified_at)->not->toBeNull()
            ->and($fresh->must_change_password)->toBeFalse();

        $this->postJson('/api/v1/auth/invitation/accept', newPassword(['email' => 'eva@luna.test', 'token' => $this->token]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.token.0', 'El link de invitación no es válido o venció. Pedile a quien te invitó que te lo reenvíe.');
    });

    it('expires after 72 hours', function (): void {
        $this->travel(73)->hours();

        $this->postJson('/api/v1/auth/invitation/accept', newPassword(['email' => 'eva@luna.test', 'token' => $this->token]))->assertUnprocessable();
    });

    it('rejects invitations of a suspended store', function (): void {
        $this->store->forceFill(['status' => 'suspended'])->save();

        $this->postJson('/api/v1/auth/invitation/accept', newPassword(['email' => 'eva@luna.test', 'token' => $this->token]))->assertUnprocessable();
    });

    it('lets a pending invitee use "forgot password" instead (it counts as accepting)', function (): void {
        $this->postJson('/api/v1/auth/forgot-password', ['email' => 'eva@luna.test']);
        $reset = sentLinkToken($this->invited, ResetPasswordLink::class);

        $this->postJson('/api/v1/auth/reset-password', newPassword(['email' => 'eva@luna.test', 'token' => $reset]))->assertNoContent();

        expect($this->invited->fresh()->invitationPending())->toBeFalse();
    });
});

it('renders the emails in Spanish with links to the panel', function (): void {
    config(['services.panel.url' => 'https://panel.test']);
    app()->setLocale('es');

    $invitation = (new UserInvitation('tok123', 'Almacén Luna', 72))->toMail($this->user)->render();
    expect((string) $invitation)->toContain('Almacén Luna')
        ->toContain('Elegir mi contraseña')
        ->toContain('https://panel.test/admin/invitacion?token=tok123&amp;email=ana%40luna.test')
        ->toContain('copiá y pegá');

    $reset = (string) (new ResetPasswordLink('tok456', 60))->toMail($this->user)->render();
    expect($reset)->toContain('Restablecer contraseña')->toContain('https://panel.test/admin/restablecer?token=tok456');
});
