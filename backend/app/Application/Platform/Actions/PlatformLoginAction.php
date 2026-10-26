<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Domain\Auth\Exceptions\InvalidCredentialsException;
use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Contracts\Hashing\Hasher;

/**
 * Primer paso del login del super panel: la contraseña correcta solo da un token de desafío,
 * que sirve únicamente para completar (o configurar) la verificación en dos pasos.
 */
final readonly class PlatformLoginAction
{
    public const string CHALLENGE_TOKEN_NAME = 'platform-challenge';

    public const string CHALLENGE_ABILITY = 'platform:challenge';

    public const int CHALLENGE_TTL_MINUTES = 5;

    /** Hash válido (de "dummy") para gastar el mismo tiempo cuando el email no existe. */
    private const string DUMMY_HASH = '$2y$12$5JmYdf1gROt5YjDgStzgDu91P6ZUVmnHzBHT61M3iMrhzOqZHjwZC';

    public function __construct(
        private Hasher $hasher,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * @return array{status: 'two_factor_required'|'two_factor_setup_required', challenge_token: string}
     *
     * @throws InvalidCredentialsException
     */
    public function execute(string $email, string $password): array
    {
        $admin = PlatformAdmin::query()->where('email', $email)->first();

        $passwordMatches = $this->hasher->check($password, $admin->password ?? self::DUMMY_HASH);

        if ($admin === null || ! $passwordMatches) {
            $this->audit->record('login_failed', $admin, metadata: ['email' => $email]);

            throw new InvalidCredentialsException;
        }

        // Un solo desafío vivo por administrador.
        $admin->tokens()->where('name', self::CHALLENGE_TOKEN_NAME)->delete();

        $token = $admin->createToken(
            self::CHALLENGE_TOKEN_NAME,
            [self::CHALLENGE_ABILITY],
            now()->addMinutes(self::CHALLENGE_TTL_MINUTES),
        )->plainTextToken;

        return [
            'status' => $admin->hasTwoFactor() ? 'two_factor_required' : 'two_factor_setup_required',
            'challenge_token' => $token,
        ];
    }
}
