<?php

declare(strict_types=1);

namespace App\Infrastructure\Storefront;

use App\Domain\Store\Contracts\UploadedObject;
use App\Domain\Store\Contracts\UploadedObjectInspector;
use Illuminate\Contracts\Filesystem\Factory as Filesystems;

final readonly class R2UploadedObjectInspector implements UploadedObjectInspector
{
    public function __construct(
        private Filesystems $filesystems,
    ) {}

    public function inspect(string $path): ?UploadedObject
    {
        $disk = $this->filesystems->disk('r2');

        if (! $disk->exists($path)) {
            return null;
        }

        $contentType = $disk->mimeType($path);

        return new UploadedObject((int) $disk->size($path), $contentType === false ? '' : $contentType);
    }
}
