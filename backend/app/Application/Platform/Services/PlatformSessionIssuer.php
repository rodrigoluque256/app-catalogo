<?php

declare(strict_types=1);

namespace App\Application\Platform\Services;

use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Support\Facades\DB;

/**
 * Cambia el token de desafío (contraseña correcta) por el token de sesión, una vez superado el segundo factor.
 */
final readonly class PlatformSessionIssuer
{
    public const string TOKEN_NAME = 'platform';

    public const string ABILITY = 'platform';

    public const int TOKEN_TTL_HOURS = 8;

    public function __construct(
        private PlatformAuditLogger $audit,
    ) {}

    public function issue(PlatformAdmin $admin, int $challengeTokenId): string
    {
        return DB::transaction(function () use ($admin, $challengeTokenId): string {
            $admin->tokens()->whereKey($challengeTokenId)->delete();
            $admin->forceFill(['last_login_at' => now()])->save();
            $this->audit->record('login', $admin);

            return $admin->createToken(self::TOKEN_NAME, [self::ABILITY], now()->addHours(self::TOKEN_TTL_HOURS))->plainTextToken;
        });
    }
}
