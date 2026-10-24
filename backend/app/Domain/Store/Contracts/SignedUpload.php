<?php

declare(strict_types=1);

namespace App\Domain\Store\Contracts;

use DateTimeInterface;

final readonly class SignedUpload
{
    /**
     * @param  array<string, string>  $headers  cabeceras que el cliente debe enviar tal cual en el PUT
     */
    public function __construct(
        public string $url,
        public array $headers,
        public string $publicUrl,
        public DateTimeInterface $expiresAt,
    ) {}
}
