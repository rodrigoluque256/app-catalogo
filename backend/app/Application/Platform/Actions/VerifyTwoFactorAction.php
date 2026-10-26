<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions;

use App\Application\Platform\Services\PlatformAuditLogger;
use App\Application\Platform\Services\PlatformSessionIssuer;
use App\Application\Platform\Services\TwoFactor;
use App\Domain\Platform\Exceptions\TwoFactorException;
use App\Domain\Platform\Models\PlatformAdmin;
use Illuminate\Support\Facades\DB;

final readonly class VerifyTwoFactorAction
{
    public function __construct(
        private TwoFactor $twoFactor,
        private PlatformSessionIssuer $sessions,
        private PlatformAuditLogger $audit,
    ) {}

    /**
     * Segundo paso del login: un código de la app o, si se perdió el teléfono, un código de recuperación (un solo uso).
     *
     * @throws TwoFactorException
     */
    public function execute(PlatformAdmin $admin, ?string $code, ?string $recoveryCode, int $challengeTokenId): string
    {
        if (! $admin->hasTwoFactor()) {
            throw TwoFactorException::notConfigured();
        }

        try {
            return $this->verifyAndIssue($admin, $code, $recoveryCode, $challengeTokenId);
        } catch (TwoFactorException $e) {
            // Fuera de la transacción: el intento fallido queda registrado aunque se deshaga todo lo demás.
            $this->audit->record('two_factor_failed', $admin, metadata: ['method' => $recoveryCode !== null ? 'recovery_code' : 'totp']);

            throw $e;
        }
    }

    private function verifyAndIssue(PlatformAdmin $admin, ?string $code, ?string $recoveryCode, int $challengeTokenId): string
    {
        return DB::transaction(function () use ($admin, $code, $recoveryCode, $challengeTokenId): string {
            // Bloquea la fila: dos pedidos simultáneos no pueden usar el mismo código.
            $admin = PlatformAdmin::query()->lockForUpdate()->findOrFail($admin->id);

            if ($recoveryCode !== null) {
                $this->consumeRecoveryCode($admin, $recoveryCode);
            } else {
                $step = $this->twoFactor->verify((string) $admin->two_factor_secret, (string) $code, $admin->two_factor_last_step)
                    ?? throw TwoFactorException::invalidCode();
                $admin->forceFill(['two_factor_last_step' => $step])->save();
            }

            return $this->sessions->issue($admin, $challengeTokenId);
        });
    }

    private function consumeRecoveryCode(PlatformAdmin $admin, string $recoveryCode): void
    {
        $hash = $this->twoFactor->hashRecoveryCode($recoveryCode);
        $codes = $admin->two_factor_recovery_codes ?? [];

        if (! in_array($hash, $codes, true)) {
            throw TwoFactorException::invalidRecoveryCode();
        }

        $remaining = array_values(array_filter($codes, fn (string $stored): bool => ! hash_equals($stored, $hash)));
        $admin->forceFill(['two_factor_recovery_codes' => $remaining])->save();
        $this->audit->record('recovery_code_used', $admin, metadata: ['remaining' => count($remaining)]);
    }
}
