<?php

declare(strict_types=1);

namespace App\Domain\Store;

/**
 * Subdominios que nunca pueden ser una tienda: los usa (o puede usar) la propia plataforma.
 */
final class ReservedSlugs
{
    public const array LIST = [
        'admin', 'api', 'app', 'assets', 'blog', 'cdn', 'checkout', 'dashboard', 'dev', 'docs', 'ftp', 'help',
        'img', 'login', 'mail', 'media', 'panel', 'platform', 'plataforma', 'registro', 'register', 's',
        'smtp', 'soporte', 'static', 'staging', 'status', 'support', 'test', 'www',
    ];

    public static function isReserved(string $slug): bool
    {
        return in_array(strtolower($slug), self::LIST, true);
    }
}
