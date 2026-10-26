<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

/**
 * @extends Factory<PlatformAdmin>
 */
class PlatformAdminFactory extends Factory
{
    protected $model = PlatformAdmin::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }

    /** 2FA ya confirmado con un secreto conocido (para generar códigos válidos en los tests). */
    public function withTwoFactor(?string $secret = null): static
    {
        return $this->state(fn (): array => [
            'two_factor_secret' => $secret ?? (new Google2FA)->generateSecretKey(32),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [],
        ]);
    }
}
