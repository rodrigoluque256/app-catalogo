<?php

declare(strict_types=1);

use App\Domain\Platform\Models\PlatformAdmin;
use App\Domain\Platform\Models\PlatformAuditLog;
use Illuminate\Support\Facades\Hash;

describe('php artisan platform:admin', function (): void {
    it('creates an admin with a hashed password and no 2FA yet', function (): void {
        $this->artisan('platform:admin', ['email' => 'Nueva@Plataforma.test', '--name' => 'Nueva'])
            ->expectsQuestion('Contraseña (mínimo 10 caracteres, con letras y números)', 'clave-segura-123')
            ->expectsQuestion('Repetí la contraseña', 'clave-segura-123')
            ->assertSuccessful();

        $admin = PlatformAdmin::query()->where('email', 'nueva@plataforma.test')->sole();
        expect($admin->name)->toBe('Nueva');
        expect(Hash::check('clave-segura-123', $admin->password))->toBeTrue();
        expect($admin->hasTwoFactor())->toBeFalse();
        expect(PlatformAuditLog::query()->where('action', 'admin_created')->value('metadata'))->toBe(['via' => 'console']);
    });

    it('rejects a weak or mismatched password', function (): void {
        $this->artisan('platform:admin', ['email' => 'x@plataforma.test'])
            ->expectsQuestion('Contraseña (mínimo 10 caracteres, con letras y números)', 'corta')
            ->expectsQuestion('Repetí la contraseña', 'otra')
            ->assertFailed();

        expect(PlatformAdmin::query()->count())->toBe(0);
    });

    it('resets the password of an existing admin and closes their sessions', function (): void {
        $admin = PlatformAdmin::factory()->create(['email' => 'admin@plataforma.test']);
        $admin->createToken('platform', ['platform']);

        $this->artisan('platform:admin', ['email' => 'admin@plataforma.test'])
            ->expectsQuestion('Contraseña (mínimo 10 caracteres, con letras y números)', 'nueva-clave-456')
            ->expectsQuestion('Repetí la contraseña', 'nueva-clave-456')
            ->assertSuccessful();

        expect(Hash::check('nueva-clave-456', $admin->refresh()->password))->toBeTrue();
        expect($admin->tokens()->count())->toBe(0);
    });

    it('clears 2FA with --reset-2fa without asking for a password', function (): void {
        $admin = PlatformAdmin::factory()->withTwoFactor()->create(['email' => 'admin@plataforma.test']);
        $admin->createToken('platform', ['platform']);

        $this->artisan('platform:admin', ['email' => 'admin@plataforma.test', '--reset-2fa' => true])->assertSuccessful();

        $admin->refresh();
        expect($admin->hasTwoFactor())->toBeFalse();
        expect($admin->two_factor_secret)->toBeNull();
        expect($admin->tokens()->count())->toBe(0);
    });

    it('rejects an invalid email', function (): void {
        $this->artisan('platform:admin', ['email' => 'no-es-email'])->assertFailed();
    });
});
