<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Application\Platform\Services\PlatformSessionIssuer;
use App\Application\Platform\Services\TwoFactor;
use App\Domain\Platform\Exceptions\TwoFactorException;
use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Support\Facades\DB;

final readonly class ConfirmTwoFactorAction
{
    public function __construct(
        private TwoFactor $twoFactor,
        private PlatformSessionIssuer $sessions,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * El primer código válido confirma el secreto. Los códigos de recuperación se devuelven UNA sola vez.
     *
     * @return array{token: string, recovery_codes: list<string>}
     *
     * @throws TwoFactorException
     */
    public function execute(PlatformAdmin $admin, string $code, int $challengeTokenId): array
    {
        if ($admin->hasTwoFactor()) {
            throw TwoFactorException::alreadyConfirmed();
        }

        if ($admin->two_factor_secret === null) {
            throw TwoFactorException::setupNotStarted();
        }

        $step = $this->twoFactor->verify($admin->two_factor_secret, $code, $admin->two_factor_last_step)
            ?? throw TwoFactorException::invalidCode();

        $codes = $this->twoFactor->generateRecoveryCodes();

        $token = DB::transaction(function () use ($admin, $step, $codes, $challengeTokenId): string {
            $admin->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_last_step' => $step,
                'two_factor_recovery_codes' => $codes['hashed'],
            ])->save();
            $this->audit->record('two_factor_enabled', $admin);

            return $this->sessions->issue($admin, $challengeTokenId);
        });

        return ['token' => $token, 'recovery_codes' => $codes['plain']];
    }
}
