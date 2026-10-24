<?php

declare(strict_types=1);

namespace App\Infrastructure\Storefront;

use App\Domain\Store\Contracts\SignedUpload;
use App\Domain\Store\Contracts\UploadUrlSigner;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;

final readonly class R2UploadUrlSigner implements UploadUrlSigner
{
    public function __construct(
        private Filesystems $filesystems,
    ) {}

    public function sign(string $path, int $ttlSeconds): SignedUpload
    {
        $disk = $this->filesystems->disk('r2');
        $expiresAt = now()->addSeconds($ttlSeconds);

        /** @var array{url: string, headers: array<string, mixed>} $signed */
        $signed = $disk->temporaryUploadUrl($path, $expiresAt);

        $headers = [];

        foreach ($signed['headers'] as $name => $value) {
            // Host lo pone el navegador.
            if (strtolower($name) !== 'host') {
                $headers[$name] = is_array($value) ? implode(',', $value) : (string) $value;
            }
        }

        return new SignedUpload($signed['url'], $headers, $disk->url($path), $expiresAt);
    }
}
