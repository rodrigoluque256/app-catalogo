<?php

declare(strict_types=1);

namespace App\Application\Platform\Actions;

use App\Application\Platform\Services\TwoFactor;
use App\Domain\Platform\Exceptions\TwoFactorException;
use App\Domain\Platform\Models\PlatformAdmin;

final readonly class StartTwoFactorSetupAction
{
    public function __construct(
        private TwoFactor $twoFactor,
    ) {}

    /**
     * Genera un secreto nuevo (sin confirmar) cada vez que se pide: si la persona cerró la pantalla
     * a mitad de camino, vuelve a escanear un QR nuevo.
     *
     * @return array{secret: string, otpauth_url: string, qr_svg: string}
     *
     * @throws TwoFactorException
     */
    public function execute(PlatformAdmin $admin): array
    {
        if ($admin->hasTwoFactor()) {
            throw TwoFactorException::alreadyConfirmed();
        }

        $secret = $this->twoFactor->generateSecret();
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        $url = $this->twoFactor->otpauthUrl($admin->email, $secret);

        return ['secret' => $secret, 'otpauth_url' => $url, 'qr_svg' => $this->twoFactor->qrSvg($url)];
    }
}
