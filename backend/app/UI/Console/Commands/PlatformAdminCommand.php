<?php

declare(strict_types=1);

namespace App\UI\Console\Commands;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * Única forma de crear administradores de la plataforma (no hay alta desde la web).
 * También resetea la contraseña o el 2FA de uno existente; en ambos casos cierra sus sesiones.
 */
final class PlatformAdminCommand extends Command
{
    protected $signature = 'platform:admin
        {email : Email del administrador}
        {--name= : Nombre (por defecto, la parte del email antes de la @)}
        {--reset-2fa : Borra el 2FA para que lo vuelva a configurar en el próximo login}';

    protected $description = 'Crea un administrador de la plataforma o resetea su contraseña o su 2FA';

    public function handle(PlatformAuditLogger $audit): int
    {
        $email = strtolower(trim((string) $this->argument('email')));

        if (Validator::make(['email' => $email], ['email' => ['required', 'email', 'max:255']])->fails()) {
            $this->error('El email no es válido.');

            return self::FAILURE;
        }

        $admin = PlatformAdmin::query()->where('email', $email)->first();
        $resetTwoFactor = (bool) $this->option('reset-2fa');

        if ($admin !== null && $resetTwoFactor) {
            $this->resetTwoFactor($admin);
            $audit->record('two_factor_reset', $admin, metadata: ['via' => 'console']);
            $this->info("2FA borrado para {$email}: lo va a configurar en el próximo login. Sesiones cerradas.");

            return self::SUCCESS;
        }

        $password = $this->askPassword();

        if ($password === null) {
            return self::FAILURE;
        }

        DB::transaction(function () use (&$admin, $email, $password, $audit): void {
            if ($admin === null) {
                $admin = new PlatformAdmin;
                $admin->forceFill([
                    'email' => $email,
                    'name' => (string) ($this->option('name') ?: strstr($email, '@', true)),
                ]);
                $admin->forceFill(['password' => $password])->save();
                $audit->record('admin_created', $admin, metadata: ['via' => 'console']);

                return;
            }

            if ($this->option('name')) {
                $admin->forceFill(['name' => (string) $this->option('name')]);
            }

            $admin->forceFill(['password' => $password])->save();
            $admin->tokens()->delete();
            $audit->record('password_reset', $admin, metadata: ['via' => 'console']);
        });

        $this->info("Listo: {$email} puede entrar en /platform/login (el primer ingreso pide configurar el 2FA).");

        return self::SUCCESS;
    }

    private function askPassword(): ?string
    {
        $password = (string) $this->secret('Contraseña (mínimo 10 caracteres, con letras y números)');
        $confirmation = (string) $this->secret('Repetí la contraseña');

        $validator = Validator::make(
            ['password' => $password, 'password_confirmation' => $confirmation],
            ['password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()]],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return null;
        }

        return $password;
    }

    private function resetTwoFactor(PlatformAdmin $admin): void
    {
        DB::transaction(function () use ($admin): void {
            $admin->forceFill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_last_step' => null,
            ])->save();
            $admin->tokens()->delete();
        });
    }
}
