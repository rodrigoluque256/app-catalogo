<?php

declare(strict_types=1);

namespace App\Application\Platform\Services;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

/**
 * TOTP (RFC 6238, compatible con Google Authenticator, Authy, 1Password…) y códigos de recuperación.
 */
final readonly class TwoFactor
{
    public const int RECOVERY_CODES = 8;

    /** Tolerancia de ±1 paso (30 s) por desfase de reloj del teléfono. */
    private const int WINDOW = 1;

    public function __construct(
        private Google2FA $google2fa,
    ) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(string $email, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name').' Plataforma', $email, $secret);
    }

    public function qrSvg(string $otpauthUrl): string
    {
        return (new Writer(new ImageRenderer(new RendererStyle(200, 1), new SvgImageBackEnd)))->writeString($otpauthUrl);
    }

    /**
     * Paso de tiempo del código si es válido y posterior al último aceptado; null si no.
     * Guardar el paso devuelto impide usar dos veces el mismo código (aunque siga dentro de su ventana).
     */
    public function verify(string $secret, string $code, ?int $lastStep): ?int
    {
        $code = preg_replace('/\D/', '', $code) ?? '';

        if (strlen($code) !== 6) {
            return null;
        }

        $step = $this->google2fa->verifyKeyNewer($secret, $code, $lastStep ?? 0, self::WINDOW);

        return is_int($step) ? $step : null;
    }

    /**
     * @return array{plain: list<string>, hashed: list<string>}
     */
    public function generateRecoveryCodes(): array
    {
        $plain = [];

        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $plain[] = Str::lower(Str::random(5).'-'.Str::random(5));
        }

        return ['plain' => $plain, 'hashed' => array_map($this->hashRecoveryCode(...), $plain)];
    }

    /**
     * Los códigos son aleatorios y largos: un SHA-256 alcanza (no hace falta bcrypt) y permite buscarlos.
     */
    public function hashRecoveryCode(string $code): string
    {
        return hash('sha256', Str::lower(trim($code)));
    }
}
