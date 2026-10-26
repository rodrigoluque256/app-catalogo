<?php

declare(strict_types=1);

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use App\Domain\Store\Models\Store;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\RateLimiter;
use PragmaRX\Google2FA\Google2FA;

const PLATFORM_TOTP_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

/** Código TOTP del secreto de prueba, `offset` pasos (de 30 s) respecto de ahora. */
function totp(int $offset = 0): string
{
    return (new Google2FA)->oathTotp(PLATFORM_TOTP_SECRET, intdiv(time(), 30) + $offset);
}

function challengeFor(string $email = 'admin@plataforma.test'): string
{
    return test()->postJson('/api/v1/platform/auth/login', ['email' => $email, 'password' => 'password'])
        ->assertOk()
        ->json('data.challenge_token');
}

beforeEach(function (): void {
    RateLimiter::clear('platform-login');
    $this->admin = PlatformAdmin::factory()->create(['email' => 'admin@plataforma.test']);
});

describe('POST /api/v1/platform/auth/login', function (): void {
    it('gives the same generic 422 for a wrong password and an unknown email, and audits both', function (): void {
        foreach ([['admin@plataforma.test', 'incorrecta'], ['nadie@plataforma.test', 'password']] as [$email, $password]) {
            $this->postJson('/api/v1/platform/auth/login', compact('email', 'password'))
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', 'Email o contraseña incorrectos.');
        }

        expect(PlatformAuditLog::query()->where('action', 'login_failed')->count())->toBe(2);
        expect(PlatformAuditLog::query()->where('action', 'login_failed')->whereNotNull('platform_admin_id')->count())->toBe(1);
    });

    it('keeps store users and platform admins apart in both logins', function (): void {
        $this->seed(RolesAndPermissionsSeeder::class);
        RateLimiter::clear('login');
        User::factory()->forStore(Store::factory()->create())->create(['email' => 'dueno@tienda.test']);

        $this->postJson('/api/v1/platform/auth/login', ['email' => 'dueno@tienda.test', 'password' => 'password'])->assertUnprocessable();
        $this->postJson('/api/v1/auth/login', ['email' => 'admin@plataforma.test', 'password' => 'password'])->assertUnprocessable();
    });

    it('asks to set up 2FA first and returns a challenge token that cannot open the panel', function (): void {
        $response = $this->postJson('/api/v1/platform/auth/login', ['email' => 'Admin@Plataforma.test', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.status', 'two_factor_setup_required')
            ->assertJsonPath('data.expires_in', 300);

        withBearer($response->json('data.challenge_token'), 'GET', '/api/v1/platform/auth/me')->assertForbidden();
    });

    it('locks out after 5 attempts per email and IP', function (): void {
        foreach (range(1, 5) as $_) {
            $this->postJson('/api/v1/platform/auth/login', ['email' => 'admin@plataforma.test', 'password' => 'mala'])->assertUnprocessable();
        }

        $this->postJson('/api/v1/platform/auth/login', ['email' => 'admin@plataforma.test', 'password' => 'password'])->assertTooManyRequests();
    });
});

describe('2FA setup', function (): void {
    it('confirms with a valid code and returns the session token plus 8 one-time recovery codes', function (): void {
        $challenge = challengeFor();

        $setup = withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/setup')->assertOk();
        expect($setup->json('data.secret'))->toBeString()->toHaveLength(32);
        expect($setup->json('data.otpauth_url'))->toStartWith('otpauth://totp/');
        expect($setup->json('data.qr_svg'))->toContain('<svg');

        $this->admin->forceFill(['two_factor_secret' => PLATFORM_TOTP_SECRET])->save(); // secreto conocido para generar el código

        $confirm = withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/confirm', ['code' => totp()])
            ->assertOk()
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.expires_in', 28_800)
            ->assertJsonCount(8, 'data.recovery_codes');

        withBearer($confirm->json('data.token'), 'GET', '/api/v1/platform/auth/me')
            ->assertOk()
            ->assertJsonPath('data.admin.email', 'admin@plataforma.test');

        // El desafío se consumió: solo queda el token de sesión.
        expect($this->admin->tokens()->pluck('name')->all())->toBe(['platform']);
        expect(PlatformAuditLog::query()->pluck('action')->all())->toBe(['two_factor_enabled', 'login']);
        expect($this->admin->refresh()->hasTwoFactor())->toBeTrue();
        expect($this->admin->getRawOriginal('two_factor_secret'))->not->toContain(PLATFORM_TOTP_SECRET); // cifrado en la base
    });

    it('rejects a wrong code and a second setup once confirmed', function (): void {
        $challenge = challengeFor();
        withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/setup')->assertOk();

        withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/confirm', ['code' => '000000'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'El código no es válido o ya se usó.');

        $this->admin->forceFill(['two_factor_secret' => PLATFORM_TOTP_SECRET, 'two_factor_confirmed_at' => now()])->save();

        withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/setup')->assertUnprocessable();
    });
});

describe('2FA verification', function (): void {
    beforeEach(function (): void {
        $this->admin->delete();
        $this->admin = PlatformAdmin::factory()->withTwoFactor(PLATFORM_TOTP_SECRET)->create(['email' => 'admin@plataforma.test']);
    });

    it('logs in with a code from the app and never accepts the same code twice', function (): void {
        $first = challengeFor();
        $this->postJson('/api/v1/platform/auth/login', ['email' => 'admin@plataforma.test', 'password' => 'password'])
            ->assertJsonPath('data.status', 'two_factor_required');

        $code = totp();
        $token = withBearer(challengeFor(), 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => $code])
            ->assertOk()
            ->json('data.token');
        withBearer($token, 'GET', '/api/v1/platform/auth/me')->assertOk();

        withBearer(challengeFor(), 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => $code])
            ->assertUnprocessable()
            ->assertJsonPath('errors.code.0', 'El código no es válido o ya se usó.');
        expect(PlatformAuditLog::query()->where('action', 'two_factor_failed')->count())->toBe(1);

        // Un desafío anterior queda invalidado por el login siguiente.
        withBearer($first, 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => totp(1)])->assertUnauthorized();
    });

    it('accepts each recovery code only once', function (): void {
        $hash = hash('sha256', 'abcde-fghij');
        $this->admin->forceFill(['two_factor_recovery_codes' => [$hash, hash('sha256', 'otro0-codig')]])->save();

        withBearer(challengeFor(), 'POST', '/api/v1/platform/auth/two-factor/verify', ['recovery_code' => ' ABCDE-fghij '])->assertOk();
        withBearer(challengeFor(), 'POST', '/api/v1/platform/auth/two-factor/verify', ['recovery_code' => 'abcde-fghij'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.recovery_code.0', 'El código de recuperación no es válido o ya se usó.');

        expect($this->admin->refresh()->two_factor_recovery_codes)->toHaveCount(1);
        expect(PlatformAuditLog::query()->where('action', 'recovery_code_used')->value('metadata'))->toBe(['remaining' => 1]);
    });

    it('expires the challenge after 5 minutes', function (): void {
        $challenge = challengeFor();
        $this->travel(6)->minutes();

        withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => totp()])->assertUnauthorized();
    });

    it('validates that a code is sent', function (): void {
        withBearer(challengeFor(), 'POST', '/api/v1/platform/auth/two-factor/verify')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code', 'recovery_code']);
    });
});

describe('token separation', function (): void {
    beforeEach(function (): void {
        $this->admin->delete();
        $this->admin = PlatformAdmin::factory()->withTwoFactor(PLATFORM_TOTP_SECRET)->create(['email' => 'admin@plataforma.test']);
        $this->platformToken = withBearer(challengeFor(), 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => totp()])->json('data.token');
    });

    it('rejects a platform token on the store admin API', function (): void {
        withBearer($this->platformToken, 'GET', '/api/v1/admin/products')->assertForbidden();
        withBearer($this->platformToken, 'GET', '/api/v1/auth/me')->assertForbidden();
    });

    it('rejects a store token (abilities *) on the platform API', function (): void {
        $user = User::factory()->forStore(Store::factory()->create())->create();
        $storeToken = $user->createToken('admin', ['*'])->plainTextToken;

        withBearer($storeToken, 'GET', '/api/v1/platform/auth/me')->assertForbidden();
        withBearer($storeToken, 'POST', '/api/v1/platform/auth/two-factor/setup')->assertForbidden();
    });

    it('does not let the session token restart the 2FA flow', function (): void {
        withBearer($this->platformToken, 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => totp(1)])->assertForbidden();
    });

    it('logs out by revoking the token', function (): void {
        withBearer($this->platformToken, 'POST', '/api/v1/platform/auth/logout')->assertNoContent();
        withBearer($this->platformToken, 'GET', '/api/v1/platform/auth/me')->assertUnauthorized();
        expect(PlatformAuditLog::query()->where('action', 'logout')->count())->toBe(1);
    });
});

describe('2FA throttle', function (): void {
    it('allows 5 code attempts per minute and then answers 429', function (): void {
        $this->admin->delete();
        PlatformAdmin::factory()->withTwoFactor(PLATFORM_TOTP_SECRET)->create(['email' => 'admin@plataforma.test']);
        $challenge = challengeFor();

        foreach (range(1, 5) as $_) {
            withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => '000000'])->assertUnprocessable();
        }

        withBearer($challenge, 'POST', '/api/v1/platform/auth/two-factor/verify', ['code' => totp()])->assertTooManyRequests();
    });
});
